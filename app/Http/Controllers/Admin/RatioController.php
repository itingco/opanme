<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\StockOpnameCycle;
use App\Models\UomRatio;
use App\Services\RatioImportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

class RatioController extends Controller
{
    public function index(Request $request): View
    {
        $q = UomRatio::query()
            ->orderBy('source_database')
            ->orderBy('item_code')
            ->orderBy('uom_level');

        if ($request->filled('source_database')) {
            $q->where('source_database', $request->string('source_database'));
        }

        if ($request->filled('q')) {
            $q->where(fn ($x) => $x
                ->where('item_code', 'ilike', '%'.$request->string('q').'%')
                ->orWhere('item_name', 'ilike', '%'.$request->string('q').'%'));
        }

        return view('admin.ratios.index', [
            'ratios' => $q->paginate(40)->withQueryString(),
            'databases' => [StockOpnameCycle::DB_INGCO, StockOpnameCycle::DB_SMI],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        UomRatio::updateOrCreate(
            [
                'source_database' => $data['source_database'],
                'item_id' => $data['item_id'],
                'uom_level' => $data['uom_level'],
            ],
            $data
        );

        return back()->with('success', 'Master ratio tersimpan.');
    }

    public function import(Request $request, RatioImportService $importer): RedirectResponse
    {
        $request->validate([
            'ratio_file' => ['required', 'file', 'max:10240'],
        ], [
            'ratio_file.required' => 'Pilih file Excel terlebih dahulu.',
            'ratio_file.max' => 'Ukuran file import maksimal 10 MB.',
        ]);

        try {
            $result = $importer->import($request->file('ratio_file'));
        } catch (RuntimeException $e) {
            return back()->withErrors(['ratio_file' => $e->getMessage()]);
        } catch (Throwable $e) {
            report($e);

            return back()->withErrors(['ratio_file' => 'Import gagal: '.$e->getMessage()]);
        }

        $success = $result['created'] + $result['updated'];

        return back()
            ->with('success', "Import selesai. {$success} ratio berhasil diproses.")
            ->with('ratio_import_result', $result);
    }

    public function template(): BinaryFileResponse
    {
        $path = resource_path('templates/uom-ratio-import-template.xlsx');
        abort_unless(is_file($path), 404, 'Template import tidak ditemukan.');

        return response()->download(
            $path,
            'Template-Import-UOM-Ratio.xlsx',
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']
        );
    }

    public function destroy(UomRatio $ratio): RedirectResponse
    {
        $ratio->delete();

        return back()->with('success', 'Master ratio dihapus.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'source_database' => ['required', Rule::in([StockOpnameCycle::DB_INGCO, StockOpnameCycle::DB_SMI])],
            'item_id' => ['required', 'integer', 'min:1'],
            'item_code' => ['required', 'string', 'max:100'],
            'item_name' => ['nullable', 'string', 'max:255'],
            'uom_level' => ['required', 'integer', 'between:1,4'],
            'uom_code' => ['required', 'string', 'max:50'],
            'ratio' => ['required', 'numeric', 'gt:0'],
        ]);
    }
}
