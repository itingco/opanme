<?php

namespace App\Services;

use App\Models\StockOpnameCycle;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/** One physical scan area; source warehouses and ERP identities remain separate. */
class CombinedCycleSnapshotService extends CycleSnapshotService
{
    public const MODE = 'COMBINED';

    public function __construct(
        private readonly ErpStockService $stock,
        private readonly CombinedCycleCatalogService $catalog
    ) {
        parent::__construct($stock);
    }

    public function captureOpening(StockOpnameCycle $cycle, CarbonInterface $snapshotAt): int
    {
        if ($cycle->source_database !== self::MODE) return parent::captureOpening($cycle, $snapshotAt);
        return $this->capture($cycle, $snapshotAt, false);
    }

    public function captureClosing(StockOpnameCycle $cycle, CarbonInterface $snapshotAt): int
    {
        if ($cycle->source_database !== self::MODE) return parent::captureClosing($cycle, $snapshotAt);
        return $this->capture($cycle, $snapshotAt, true);
    }

    public function sources(StockOpnameCycle $cycle)
    {
        return DB::table('cycle_combined_sources')->where('cycle_id', $cycle->id)->orderBy('id')->get();
    }

    private function capture(StockOpnameCycle $cycle, CarbonInterface $at, bool $closing): int
    {
        $sources = $this->sources($cycle);
        if ($sources->count() < 2) throw new RuntimeException('Gudang sumber gabungan tidak lengkap.');
        $date = $closing ? $at->toDateString() : $cycle->cutoff_date;
        $codes = []; $quantities = []; $erpIds = [];
        // One uncached ERP call per selected database; fail the entire capture if one fails.
        foreach ($sources->groupBy('source_database') as $database => $warehouses) {
            $byWarehouse = $warehouses->keyBy('erp_warehouse_id');
            foreach ($this->stock->snapshot((string) $database, $date) as $row) {
                $source = $byWarehouse->get((int) $row['warehouse_id']);
                if (!$source) continue;
                $code = mb_strtoupper(trim((string) $row['item_code']));
                if ($code === '') throw new RuntimeException('Item Code kosong pada snapshot ERP.');
                $id = (int) $row['item_id'];
                if (isset($erpIds[$database][$code]) && $erpIds[$database][$code] !== $id) {
                    throw new RuntimeException("Item Code {$code} tidak unik pada {$database}.");
                }
                $erpIds[$database][$code] = $id;
                $codes[$code] = $code;
                if (isset($quantities[$source->id][$code])) throw new RuntimeException("Snapshot ERP duplikat {$database}/{$source->warehouse_code}/{$code}.");
                $quantities[$source->id][$code] = round((float) $row['smallest_on_hand'], 4);
            }
        }
        // Closing also verifies the existing item identities and retains zero closing balances.
        if ($closing) {
            foreach (DB::table('cycle_combined_items')->where('cycle_id', $cycle->id)->pluck('item_code') as $code) $codes[$code] = $code;
        }
        $masters = $this->masters($sources, array_values($codes));
        foreach ($erpIds as $database => $items) {
            foreach ($items as $code => $id) {
                if ((int) ($masters[$database][$code]['item_id'] ?? 0) !== $id) {
                    throw new RuntimeException("Identitas ERP {$database}/{$code} berbeda dengan snapshot.");
                }
            }
        }
        return DB::transaction(function () use ($cycle, $at, $closing, $sources, $codes, $masters, $quantities): int {
            $locked = StockOpnameCycle::query()->lockForUpdate()->findOrFail($cycle->id);
            $expected = $closing ? StockOpnameCycle::STATUS_CLOSED : StockOpnameCycle::STATUS_DRAFT;
            if ($locked->status !== $expected) throw new RuntimeException('Status cycle berubah. Snapshot dibatalkan.');
            if (!$closing) {
                DB::table('stock_snapshots')->where('cycle_id', $cycle->id)->delete();
                DB::table('cycle_combined_items')->where('cycle_id', $cycle->id)->delete();
            } else {
                DB::table('cycle_combined_stocks')->whereIn('combined_item_id', DB::table('cycle_combined_items')->where('cycle_id', $cycle->id)->select('id'))->update(['closing_qty' => 0]);
                DB::table('stock_snapshots')->where('cycle_id', $cycle->id)->update(['closing_system_qty' => 0, 'closing_snapshot_at' => $at]);
            }
            $this->materialize($locked, $sources, array_values($codes), $masters, $quantities, $at, $closing ? 'closing' : 'opening');
            return count($codes);
        });
    }

    /** Resolve exact codes in the selected databases, validating shared smallest UOM. */
    private function masters($sources, array $codes): array
    {
        $masters = [];
        foreach ($sources->groupBy('source_database') as $database => $ignored) {
            foreach ($this->catalog->findItemsByCodes((string) $database, $codes) as $item) {
                $code = mb_strtoupper(trim((string) $item['item_code']));
                if (isset($masters[$database][$code]) && (int) $masters[$database][$code]['item_id'] !== (int) $item['item_id']) {
                    throw new RuntimeException("Item Code {$code} ambigu pada {$database}.");
                }
                $masters[$database][$code] = $item;
            }
        }
        return $masters;
    }

    /** Called inside the bulk override transaction; allows zero-stock ERP items. */
    public function ensureCodes(StockOpnameCycle $cycle, array $codes): array
    {
        $sources = $this->sources($cycle);
        $codes = array_values(array_unique(array_map(fn ($code) => mb_strtoupper(trim((string)$code)), $codes)));
        $existing = DB::table('cycle_combined_items')->where('cycle_id', $cycle->id)->whereIn('item_code', $codes)->get()->keyBy('item_code');
        $missing = array_values(array_filter($codes, fn ($code) => !$existing->has($code)));
        if ($missing) {
            $this->materialize($cycle, $sources, $missing, $this->masters($sources, $missing), [], now(), 'extra');
        }
        return DB::table('cycle_combined_items')->where('cycle_id', $cycle->id)->whereIn('item_code', $codes)->get()->keyBy('item_code')->all();
    }

    private function materialize(StockOpnameCycle $cycle, $sources, array $codes, array $masters, array $quantities, CarbonInterface $at, string $phase): void
    {
        if (!$codes) return;
        $pool = $cycle->warehouses()->sole();
        foreach (array_chunk($codes, 250) as $chunk) {
            $inserts = []; $definitions = [];
            foreach ($chunk as $code) {
                $matching = [];
                foreach ($masters as $database => $items) if (isset($items[$code])) $matching[$database] = $items[$code];
                if (!$matching) {
                    if ($phase === 'closing' && DB::table('cycle_combined_items')->where('cycle_id',$cycle->id)->where('item_code',$code)->exists()) continue;
                    throw new RuntimeException("Item Code {$code} tidak ditemukan pada master database sumber.");
                }
                $uoms = array_unique(array_map(fn ($item) => mb_strtoupper(trim((string)($item['uom_code'] ?? ''))), $matching));
                if (count($uoms) !== 1 || reset($uoms) === '') {
                    $details = [];
                    foreach ($matching as $database => $master) {
                        $details[] = $database.': '.(trim((string) ($master['uom_code'] ?? '')) ?: '(tidak valid)');
                    }
                    throw new RuntimeException("Smallest UOM {$code} berbeda/tidak valid antar database (".implode(', ', $details)."). Periksa master ERP.");
                }
                $first = reset($matching);
                $definitions[$code] = ['matches' => $matching, 'uom' => reset($uoms)];
                $inserts[] = ['cycle_id'=>$cycle->id,'item_code'=>(string)$code,'item_name'=>$first['item_name'],'uom_code'=>reset($uoms)];
            }
            if ($inserts) DB::table('cycle_combined_items')->insertOrIgnore($inserts);
            $items = DB::table('cycle_combined_items')->where('cycle_id',$cycle->id)->whereIn('item_code',$chunk)->get()->keyBy('item_code');
            $stocks = []; $snapshots = [];
            $oldStocks = DB::table('cycle_combined_stocks')->whereIn('combined_item_id',$items->pluck('id')->all())->get()->keyBy(fn($s)=>$s->combined_item_id.':'.$s->combined_source_id);
            foreach ($definitions as $code => $def) {
                $item = $items->get($code);
                if ($item->uom_code !== $def['uom']) throw new RuntimeException("Smallest UOM {$code} berubah sejak cutoff.");
                $sum = 0.0;
                foreach ($sources as $source) {
                    $master = $def['matches'][$source->source_database] ?? null;
                    if (!$master) continue;
                    $old = $oldStocks->get($item->id.':'.$source->id);
                    if ($old && (int)$old->erp_item_id !== (int)$master['item_id']) throw new RuntimeException("ERP ItemID {$code} berubah sejak cutoff.");
                    $qty = $quantities[$source->id][$code] ?? 0;
                    $sum += $qty;
                    $stocks[] = [
                        'combined_item_id'=>$item->id,'combined_source_id'=>$source->id,'erp_item_id'=>$master['item_id'],
                        'opening_qty'=>$phase==='opening' ? $qty : ($old->opening_qty ?? 0),
                        'closing_qty'=>$phase==='closing' ? $qty : ($phase==='extra' && $cycle->closing_snapshot_at ? 0 : null),
                    ];
                }
                $snapshot = [
                    'cycle_id'=>$cycle->id,'warehouse_id'=>$pool->id,'erp_warehouse_id'=>0,'warehouse_code'=>$pool->warehouse_code,
                    'item_id'=>$item->id,'item_code'=>(string)$code,'item_name'=>$item->item_name,
                    'opening_system_qty'=>$phase==='opening' ? round($sum,4) : 0,
                    'opening_snapshot_at'=>$phase==='opening' ? $at : $cycle->started_at,
                    'closing_system_qty'=>$phase==='closing' ? round($sum,4) : ($phase==='extra' && $cycle->closing_snapshot_at ? 0 : null),
                    'closing_snapshot_at'=>$phase==='closing' ? $at : ($phase==='extra' ? $cycle->closing_snapshot_at : null),
                ];
                $snapshots[] = $snapshot;
            }
            foreach (array_chunk($stocks, 100) as $batch) {
                if ($phase==='extra') DB::table('cycle_combined_stocks')->insertOrIgnore($batch);
                else DB::table('cycle_combined_stocks')->upsert($batch,['combined_item_id','combined_source_id'], $phase==='closing' ? ['closing_qty'] : ['opening_qty']);
            }
            foreach (array_chunk($snapshots, 100) as $batch) {
                if ($phase==='extra') DB::table('stock_snapshots')->insertOrIgnore($batch);
                else DB::table('stock_snapshots')->upsert($batch,['cycle_id','warehouse_id','item_id'], $phase==='closing' ? ['closing_system_qty','closing_snapshot_at'] : ['opening_system_qty','opening_snapshot_at']);
            }
        }
    }
}
