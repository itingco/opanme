<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ItemBarcode;
use App\Services\BarcodeExportService;
use App\Services\BarcodeImportService;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

class BarcodeController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->input('q', ''));
        $sort = (string) $request->input('sort', 'item_code');
        $direction = strtolower((string) $request->input('direction', 'asc')) === 'desc' ? 'desc' : 'asc';
        $perPage = (int) $request->input('per_page', 25);
        if (! in_array($perPage, [10, 25, 50, 100], true)) $perPage = 25;
        $sortColumns = ['item_code' => 'item_code', 'barcode' => 'barcode', 'uom_code' => 'uom_code', 'updated_at' => 'updated_at'];
        if (! array_key_exists($sort, $sortColumns)) $sort = 'item_code';
        $barcodes = ItemBarcode::query()
            ->when($search !== '', function ($query) use ($search) {
                $operator = $query->getConnection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';
                $query->where(function ($builder) use ($search, $operator) {
                    $builder->where('item_code', $operator, "%{$search}%")
                        ->orWhere('barcode', $operator, "%{$search}%")
                        ->orWhere('uom_code', $operator, "%{$search}%");
                });
            })->orderBy($sortColumns[$sort], $direction)->orderBy('id')
            ->paginate($perPage)->withQueryString();
        return view('admin.barcodes.index', ['barcodes' => $barcodes, 'q' => $search,
            'sort' => $sort, 'direction' => $direction, 'perPage' => $perPage]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'edit_id' => ['nullable', 'integer', 'min:1'],
            'item_code' => ['required', 'string', 'max:100'],
            'barcode' => ['required', 'string', 'max:150'],
            'uom_code' => ['required', 'string', 'max:50'],
        ]);
        $barcode = trim($data['barcode']);
        $itemCode = strtoupper(trim($data['item_code']));
        $uom = strtoupper(trim($data['uom_code']));
        if ($barcode === '' || $itemCode === '' || $uom === '') {
            throw ValidationException::withMessages(['barcode' => 'Barcode, ItemCode dan UOM tidak boleh kosong.']);
        }
        $editId = ! empty($data['edit_id']) ? (int) $data['edit_id'] : null;
        if ($editId !== null) ItemBarcode::query()->findOrFail($editId);
        if (ItemBarcode::query()->where('barcode', $barcode)
            ->when($editId !== null, fn ($query) => $query->where('id', '<>', $editId))->exists()) {
            throw ValidationException::withMessages(['barcode' => "Barcode {$barcode} sudah digunakan oleh data lain."]);
        }
        try {
            if ($editId !== null) {
                // Update by immutable ID, not by old/new barcode.
                ItemBarcode::query()->findOrFail($editId)->update([
                    'item_code' => $itemCode, 'barcode' => $barcode, 'uom_code' => $uom,
                ]);
            } else {
                ItemBarcode::query()->create(['item_code' => $itemCode,
                    'barcode' => $barcode, 'uom_code' => $uom]);
            }
        } catch (QueryException $e) {
            if (in_array((string) $e->getCode(), ['23000', '23505', '2627', '2601'], true)
                || in_array((int) ($e->errorInfo[1] ?? 0), [2601, 2627], true)) {
                throw ValidationException::withMessages(['barcode' => 'Nomor barcode sudah terdaftar. Tidak ada perubahan disimpan.']);
            }
            throw $e;
        }
        return back()->with('success', $editId !== null ? 'Barcode berhasil diperbarui.' : 'Barcode berhasil ditambahkan.');
    }

    public function import(Request $request, BarcodeImportService $importer): RedirectResponse
    {
        $request->validate(['barcode_file' => ['required', 'file', 'max:10240']], [
            'barcode_file.required' => 'Pilih file Excel terlebih dahulu.',
            'barcode_file.max' => 'Ukuran file import maksimal 10 MB.',
        ]);
        try {
            $result = $importer->import($request->file('barcode_file'));
        } catch (RuntimeException $e) {
            return back()->withErrors(['barcode_file' => $e->getMessage()]);
        } catch (Throwable $e) {
            report($e);
            return back()->withErrors(['barcode_file' => 'Import gagal: '.$e->getMessage()]);
        }
        $success = $result['created'] + $result['updated'];
        return back()->with('success', "Import selesai. {$success} barcode berhasil diproses.")
            ->with('barcode_import_result', $result);
    }

    public function template(): BinaryFileResponse
    {
        $path = resource_path('templates/item-barcode-import-template.xlsx');
        abort_unless(is_file($path), 404, 'Template import tidak ditemukan.');
        return response()->download($path, 'Template-Import-Master-Barcode.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    public function export(BarcodeExportService $exporter): BinaryFileResponse
    {
        $directory = storage_path('app/exports');
        if (! is_dir($directory)) mkdir($directory, 0775, true);
        $path = $directory.'/Master-Barcode-'.now()->format('Ymd-His').'.xlsx';
        $exporter->export($path);
        return response()->download($path, basename($path), [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }

    public function destroy(ItemBarcode $barcode): RedirectResponse
    {
        $barcode->delete();
        return back()->with('success', 'Master barcode dihapus.');
    }
}
