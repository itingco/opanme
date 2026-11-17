<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\StockOpnameCycle;
use App\Services\BulkOverrideExcelReader;
use App\Services\ErpConnectionResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

class BulkCycleOverrideController extends Controller
{
    private const MAX_ROWS = 2000;

    public function store(Request $request, StockOpnameCycle $cycle, BulkOverrideExcelReader $reader, ErpConnectionResolver $resolver): RedirectResponse
    {
        $data = $request->validate([
            'bulk_warehouse_id' => ['required', 'integer'],
            'override_mode' => ['required', 'in:add,replace'],
            'bulk_data' => ['nullable', 'string', 'max:600000', 'required_without:bulk_file'],
            'bulk_file' => ['nullable', 'file', 'mimes:xlsx', 'max:10240', 'required_without:bulk_data'],
        ]);
        $warehouseId = (int) $data['bulk_warehouse_id'];
        $mode = $data['override_mode'];
        $cycle->warehouses()->findOrFail($warehouseId);
        $rows = [];
        try {
            if ($request->hasFile('bulk_file')) {
                $rows = $reader->read($request->file('bulk_file')->getRealPath());
            } else {
                foreach (preg_split('/\r\n|\n|\r/', (string) ($data['bulk_data'] ?? '')) as $i => $line) {
                    if (trim($line) === '') continue;
                    $rows[] = ['line' => $i + 1, 'cols' => str_contains($line, "\t") ? explode("\t", $line) : str_getcsv($line, ';')];
                    if (count($rows) > self::MAX_ROWS + 2) {
                        throw ValidationException::withMessages(['bulk_data' => 'Maksimal 2.000 item per batch.']);
                    }
                }
            }
            $entries = $this->parseRows($rows);
            $erpConnection = $resolver->connectionName((string) $cycle->source_database);
            $items = $this->findMasterItems($erpConnection, array_keys($entries));
            // Kode yang tidak dikenal di database pilihan tidak boleh menghasilkan override.
            // Warehouse hanya menentukan lokasi override, bukan kriteria keberadaan item.
            $updates = [];
            foreach ($entries as $code => $entry) {
                $item = $items[strtoupper($code)] ?? null;
                if (!$item) {
                    throw ValidationException::withMessages(['bulk_data' => "Kode {$code} tidak ditemukan pada master IC_Items database {$cycle->source_database}."]);
                }
                $itemId = (int) $item['id'];
                if (isset($updates[$itemId])) {
                    throw ValidationException::withMessages(['bulk_data' => "Kode {$code} mengarah ke item yang sudah ada dalam batch. Hapus duplikat."]);
                }
                $updates[$itemId] = ['item' => $item, 'qty' => $entry['qty'], 'remark' => $entry['remark']];
            }
            DB::transaction(function () use ($cycle, $warehouseId, $request, $updates, $mode): void {
                $locked = StockOpnameCycle::query()->lockForUpdate()->findOrFail($cycle->id);
                if (!in_array($locked->status, [StockOpnameCycle::STATUS_OPEN, StockOpnameCycle::STATUS_CLOSED], true)) {
                    throw ValidationException::withMessages(['bulk_data' => 'Override hanya dapat dilakukan ketika cycle OPEN atau CLOSED. FINALIZED tetap terkunci.']);
                }
                $locked->warehouses()->findOrFail($warehouseId);
                $now = now();
                $overrideRows = [];
                $detailRows = [];
                $historyRows = [];
                // Lock semua override lama agar dua admin tidak saling menimpa.
                $previous = [];
                foreach (array_chunk(array_keys($updates), 300) as $ids) {
                    $existing = DB::table('stock_opname_overrides')
                        ->where('cycle_id', $locked->id)
                        ->where('warehouse_id', $warehouseId)
                        ->whereIn('item_id', $ids)
                        ->lockForUpdate()
                        ->get(['item_id', 'override_qty']);
                    foreach ($existing as $old) $previous[(int) $old->item_id] = (string) $old->override_qty;
                }
                $batchId = (string) \Illuminate\Support\Str::uuid();
                foreach ($updates as $id => $entry) {
                    $before = $previous[$id] ?? null;
                    $qty = (string) $entry['qty'];
                    // Qty tambahan dihitung dari override, tidak dari scan fisik.
                    $final = $mode === 'add' ? number_format(round((float) ($before ?? 0) + (float) $qty, 4), 4, '.', '') : $qty;
                    $overrideRows[] = [
                        'cycle_id' => $locked->id, 'warehouse_id' => $warehouseId, 'item_id' => $id,
                        'override_qty' => $final, 'comment' => $entry['remark'], 'updated_by' => $request->user()->id,
                        'created_at' => $now, 'updated_at' => $now,
                    ];
                    $historyRows[] = [
                        'cycle_id' => $locked->id, 'warehouse_id' => $warehouseId, 'item_id' => $id,
                        'action' => $mode, 'source' => 'bulk', 'batch_id' => $batchId,
                        'previous_qty' => $before, 'input_qty' => $qty, 'result_qty' => $final,
                        'comment' => $entry['remark'], 'updated_by' => $request->user()->id, 'created_at' => $now,
                    ];
                    $detailRows[] = [
                        'cycle_id' => $locked->id, 'warehouse_id' => $warehouseId, 'item_id' => $id,
                        'item_code' => $entry['item']['code'], 'item_name' => $entry['item']['name'],
                        'created_at' => $now, 'updated_at' => $now,
                    ];
                }
                foreach (array_chunk($detailRows, 100) as $chunk) {
                    DB::table('stock_opname_override_item_details')->upsert($chunk, ['cycle_id', 'warehouse_id', 'item_id'], ['item_code', 'item_name', 'updated_at']);
                }
                foreach (array_chunk($overrideRows, 100) as $chunk) {
                    DB::table('stock_opname_overrides')->upsert($chunk, ['cycle_id', 'warehouse_id', 'item_id'], ['override_qty', 'comment', 'updated_by', 'updated_at']);
                }
                foreach (array_chunk($historyRows, 100) as $chunk) {
                    DB::table('stock_opname_override_history')->insert($chunk);
                }
            }, 3);
            return redirect()->route('admin.cycles.summary', ['cycle' => $cycle->id, 'warehouse_id' => $warehouseId])
                ->with('success', count($updates).' item berhasil diproses ('.($mode === 'add' ? 'Tambah Qty' : 'Ganti Qty').'). Riwayat penambahan tersimpan.');
        } catch (ValidationException $e) {
            throw $e;
        } catch (Throwable $e) {
            Log::error('Bulk override gagal', ['cycle_id' => $cycle->id, 'warehouse_id' => $warehouseId, 'error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            throw ValidationException::withMessages(['bulk_data' => 'Proses batch gagal. Data dibatalkan; cek storage/logs/laravel.log untuk detail. Pastikan php artisan migrate sudah dijalankan.']);
        }
    }

    private function parseRows(array $rows): array
    {
        $entries = []; $errors = [];
        foreach ($rows as $row) {
            $line = (int) $row['line'];
            $cols = array_map(static fn ($s) => trim((string) $s), $row['cols']);
            $header = strtolower(preg_replace('/[^a-z0-9]/', '', $cols[0] ?? ''));
            if ($line <= 2 && in_array($header, ['itemcode', 'qrcode', 'qr', 'kodebarang', 'kodeitem'], true)) continue;
            $first = $cols[0] ?? '';
            if (str_contains($first, '~')) {
                if (substr_count($first, '~') !== 1) { $errors[] = "Baris {$line}: QR harus KODE~QTY."; continue; }
                [$code, $qty] = array_map('trim', explode('~', $first, 2));
                $remark = $cols[2] ?? ($cols[1] ?? '');
                if (($cols[2] ?? '') === '' && ($cols[1] ?? '') !== '' && ($cols[1] !== $qty)) $remark = $cols[1];
                if (($cols[1] ?? '') !== '' && ($cols[2] ?? '') !== '' && $cols[1] !== $qty) {
                    $errors[] = "Baris {$line}: Qty terpisah tidak sama dengan Qty QR."; continue;
                }
            } else {
                if (count($cols) < 3) { $errors[] = "Baris {$line}: perlu kolom Item Code, Qty, Remarks."; continue; }
                [$code, $qty, $remark] = $cols;
            }
            $code = strtoupper(trim($code));
            if ($code === '' || strlen($code) > 100 || !preg_match('/^\d+(?:\.\d{1,4})?$/', $qty) || trim($remark) === '' || mb_strlen($remark) > 2000) {
                $errors[] = "Baris {$line}: Item Code, Qty (maks 4 desimal), atau Remarks tidak valid."; continue;
            }
            if (isset($entries[$code])) { $errors[] = "Baris {$line}: kode {$code} duplikat."; continue; }
            $entries[$code] = ['qty' => $qty, 'remark' => trim($remark)];
            if (count($entries) > self::MAX_ROWS) { $errors[] = 'Maksimal 2.000 item per batch.'; break; }
        }
        if (!$entries) $errors[] = 'Tidak ada item yang dapat diproses.';
        if ($errors) throw ValidationException::withMessages(['bulk_data' => implode(' ', array_slice($errors, 0, 12))]);
        return $entries;
    }

    /** Cari IC_Items dari AS_INGCO / AS_SMI tanpa filter warehouse atau stok. */
    private function findMasterItems(string $connection, array $codes): array
    {
        $result = []; $numericMissing = [];
        foreach (array_chunk($codes, 150) as $chunk) {
            $normalized = array_map('strtoupper', $chunk);
            $placeholders = implode(',', array_fill(0, count($chunk), '?'));
            $sql = "SELECT ItemID, ItemCode, ItemName FROM IC_Items WHERE UPPER(LTRIM(RTRIM(ItemCode))) IN ({$placeholders})";
            foreach (DB::connection($connection)->select($sql, $normalized) as $item) {
                $key = strtoupper(trim((string) $item->ItemCode));
                if (isset($result[$key]) && $result[$key]['id'] !== (int) $item->ItemID) {
                    throw ValidationException::withMessages(['bulk_data' => "Item Code {$key} tidak unik di master ERP."]);
                }
                $result[$key] = ['id' => (int) $item->ItemID, 'code' => trim((string) $item->ItemCode), 'name' => (string) $item->ItemName];
            }
        }
        foreach ($codes as $code) if (!isset($result[strtoupper($code)]) && ctype_digit($code)) $numericMissing[] = $code;
        foreach (array_chunk($numericMissing, 150) as $chunk) {
            $placeholders = implode(',', array_fill(0, count($chunk), '?'));
            $sql = "SELECT ItemID, ItemCode, ItemName FROM IC_Items WHERE TRY_CONVERT(decimal(38,0), LTRIM(RTRIM(ItemCode))) IN ({$placeholders})";
            $values = array_map(static fn ($c) => ltrim($c, '0') ?: '0', $chunk);
            $candidates = [];
            foreach (DB::connection($connection)->select($sql, $values) as $item) {
                $key = ltrim(trim((string) $item->ItemCode), '0') ?: '0';
                $candidates[$key][] = $item;
            }
            foreach ($chunk as $code) {
                $key = ltrim($code, '0') ?: '0';
                $matches = $candidates[$key] ?? [];
                if (count($matches) > 1) throw ValidationException::withMessages(['bulk_data' => "Kode {$code} ambigu (lebih dari satu ItemCode numerik). Gunakan kode ERP persis."]);
                if ($matches) {
                    $item = $matches[0];
                    $result[strtoupper($code)] = ['id' => (int) $item->ItemID, 'code' => trim((string) $item->ItemCode), 'name' => (string) $item->ItemName];
                }
            }
        }
        return $result;
    }
}
