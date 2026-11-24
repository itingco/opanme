<?php

namespace App\Services;

use App\Models\StockOpnameCycle;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CombinedManualAllocationService
{
    // Exact arithmetic in 1/10000 UOM; compatible with the existing allocator's limit.
    public static function units(mixed $qty): int
    {
        if (is_float($qty)) $qty = number_format($qty, 4, '.', '');
        $qty = trim((string) $qty);
        if (!preg_match('/^(\d{1,12})(?:\.(\d{1,4}))?$/D', $qty, $m)) {
            throw ValidationException::withMessages(['allocation_qty'=>'Qty harus nonnegatif, maksimal 12 digit dan 4 desimal.']);
        }
        return (int)$m[1] * 10000 + (int)str_pad($m[2] ?? '', 4, '0');
    }

    public static function decimal(int $units): string
    {
        return intdiv($units, 10000).'.'.str_pad((string)($units % 10000), 4, '0', STR_PAD_LEFT);
    }

    public static function validateQuantities(array $submitted, array $sourceIds, mixed $physical): array
    {
        $expected = self::units($physical);
        $sourceIds = array_map('intval', $sourceIds);
        if (!$sourceIds || count($submitted) !== count($sourceIds)) {
            throw ValidationException::withMessages(['allocation_qty'=>'Daftar gudang berubah atau belum lengkap. Muat ulang halaman.']);
        }
        $sum = 0; $result = [];
        foreach ($sourceIds as $id) {
            if (!array_key_exists($id, $submitted)) {
                throw ValidationException::withMessages(['allocation_qty'=>'Gudang alokasi tidak valid. Muat ulang halaman.']);
            }
            $qty = self::units($submitted[$id]);
            if ($expected % 10000 === 0 && $qty % 10000 !== 0) {
                throw ValidationException::withMessages(['allocation_qty'=>'Total fisik bulat: qty per gudang wajib bilangan bulat.']);
            }
            $sum += $qty;
            $result[$id] = self::decimal($qty);
        }
        if ($sum !== $expected) {
            throw ValidationException::withMessages(['allocation_qty'=>'Total alokasi '.self::decimal($sum).' harus sama dengan Final Fisik '.self::decimal($expected).'.']);
        }
        return $result;
    }

    public function staleItems(StockOpnameCycle $cycle, CycleSummaryService $summary): array
    {
        $manual = DB::table('cycle_combined_manual_allocations as m')
            ->join('cycle_combined_items as i','i.id','=','m.combined_item_id')
            ->where('i.cycle_id',$cycle->id);
        return DB::query()->fromSub($manual->select('m.combined_item_id','m.physical_qty','i.item_code'), 'manual')
            ->leftJoinSub($summary->query($cycle)->reorder()->where('summary_rows.row_type','ERP'), 'actual',
                'actual.item_id','=','manual.combined_item_id')
            ->where(fn($q)=>$q->whereNull('actual.item_id')->orWhereColumn('actual.final_physical_qty','<>','manual.physical_qty'))
            ->limit(10)->pluck('manual.item_code')->all();
    }
}
