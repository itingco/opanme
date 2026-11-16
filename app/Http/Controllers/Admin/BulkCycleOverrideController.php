<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\StockOpnameCycle;
use App\Models\StockOpnameOverride;
use App\Services\BulkOverrideExcelReader;
use App\Services\CycleSummaryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BulkCycleOverrideController extends Controller
{
    public function store(Request $request, StockOpnameCycle $cycle, CycleSummaryService $summary, BulkOverrideExcelReader $reader): RedirectResponse
    {
        $data = $request->validate([
            'bulk_warehouse_id' => ['required', 'integer'],
            'bulk_data' => ['nullable', 'string', 'max:150000', 'required_without:bulk_file'],
            'bulk_file' => ['nullable', 'file', 'mimes:xlsx', 'max:2048', 'required_without:bulk_data'],
        ]);
        $warehouseId = (int) $data['bulk_warehouse_id'];
        $cycle->warehouses()->findOrFail($warehouseId);
        $rows = [];
        if ($request->hasFile('bulk_file')) {
            $rows = $reader->read($request->file('bulk_file')->getRealPath());
        } else {
            foreach (preg_split('/\r\n|\n|\r/', (string) ($data['bulk_data'] ?? '')) as $i => $line) {
                if (trim($line) === '') continue;
                $columns = str_contains($line, "\t") ? explode("\t", $line) : str_getcsv($line, ';');
                $rows[] = ['line' => $i + 1, 'cols' => $columns];
            }
        }
        $entries = [];
        $errors = [];
        foreach ($rows as $row) {
            $line = $row['line'];
            $columns = array_map('trim', $row['cols']);
            $header = strtolower(preg_replace('/[^a-z0-9]/', '', $columns[0] ?? ''));
            if ($header === 'itemcode' && $line <= 2) continue;
            if (count($columns) !== 3) {
                $errors[] = "Baris $line: wajib tepat tiga kolom.";
                continue;
            }
            [$code, $qty, $remark] = $columns;
            if ($code === '' || strlen($code) > 100 || !preg_match('/^\d+(?:\.\d{1,4})?$/', $qty) || $remark === '' || strlen($remark) > 2000) {
                $errors[] = "Baris $line: Item Code, Qty (0 atau positif, maks 4 desimal), atau Remarks tidak valid.";
                continue;
            }
            if (array_key_exists($code, $entries)) {
                $errors[] = "Baris $line: kode $code duplikat.";
                continue;
            }
            $entries[$code] = ['qty' => $qty, 'remark' => $remark];
        }
        if (count($entries) > 500) $errors[] = 'Maksimal 500 item per penyimpanan.';
        if (!$entries) $errors[] = 'Tidak ada baris item yang dapat diproses.';
        if ($errors) throw ValidationException::withMessages(['bulk_data' => implode(' ', array_slice($errors, 0, 15))]);

        DB::transaction(function () use ($cycle, $warehouseId, $summary, $request, $entries): void {
            $locked = StockOpnameCycle::query()->lockForUpdate()->findOrFail($cycle->id);
            if (!in_array($locked->status, [StockOpnameCycle::STATUS_OPEN, StockOpnameCycle::STATUS_CLOSED], true)) {
                throw ValidationException::withMessages(['bulk_data' => 'Override hanya diizinkan saat OPEN atau CLOSED; FINALIZED terkunci.']);
            }
            $locked->warehouses()->findOrFail($warehouseId);
            // Compare codes using the repository's existing full summary (not just current page).
            $matches = $summary->query($locked, $warehouseId, false)
                ->where('summary_rows.row_type', 'ERP')
                ->get(['summary_rows.item_code', 'summary_rows.item_id']);
            $found = [];
            $normalized = [];
            foreach ($matches as $match) {
                $code = (string) $match->item_code;
                $found[$code] ??= [];
                $found[$code][] = (int) $match->item_id;
                // Excel often stores 00072 as numeric 72: permit only unambiguous normalization.
                $key = ctype_digit($code) ? ltrim($code, '0') : $code;
                if ($key === '') $key = '0';
                $normalized[$key] ??= [];
                $normalized[$key][] = (int) $match->item_id;
            }
            $updates = [];
            foreach ($entries as $code => $entry) {
                $ids = $found[$code] ?? null;
                if ($ids === null && ctype_digit($code)) {
                    $key = ltrim($code, '0');
                    $ids = $normalized[$key === '' ? '0' : $key] ?? null;
                }
                if ($ids === null) {
                    throw ValidationException::withMessages(['bulk_data' => 'Kode tidak ada di ERP pada warehouse ini: '.$code]);
                }
                $ids = array_unique($ids);
                if (count($ids) !== 1) {
                    throw ValidationException::withMessages(['bulk_data' => 'Kode tidak unik di warehouse: '.$code]);
                }
                $itemId = reset($ids);
                if (isset($updates[$itemId])) {
                    throw ValidationException::withMessages(['bulk_data' => 'Dua baris mengarah ke item yang sama: '.$code]);
                }
                $updates[$itemId] = $entry;
            }
            foreach ($updates as $itemId => $entry) {
                StockOpnameOverride::query()->updateOrCreate(
                    ['cycle_id' => $locked->id, 'warehouse_id' => $warehouseId, 'item_id' => $itemId],
                    ['override_qty' => $entry['qty'], 'comment' => $entry['remark'], 'updated_by' => $request->user()->id]
                );
            }
        });

        return redirect()->route('admin.cycles.summary', ['cycle' => $cycle->id, 'warehouse_id' => $warehouseId])
            ->with('success', count($entries).' override tersimpan. Scan fisik asli tetap tersimpan; perubahan hasil scan saat OPEN tidak otomatis mengganti override.');
    }
}
