<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\UomRatio;
use App\Services\RatioImportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

class RatioController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->input('q', ''));
        $sort = (string) $request->input('sort', 'item_code');
        $direction = strtolower((string) $request->input('direction', 'asc')) === 'desc' ? 'desc' : 'asc';
        $perPage = (int) $request->input('per_page', 25);

        if (! in_array($perPage, [10, 25, 50, 100], true)) {
            $perPage = 25;
        }

        $sortColumns = [
            'item_code' => 'item_code',
            'uom_code' => 'uom_code',
            'ratio' => 'ratio',
            'updated_at' => 'updated_at',
        ];

        if (! array_key_exists($sort, $sortColumns)) {
            $sort = 'item_code';
        }

        $ratios = UomRatio::query()
            ->when($search !== '', function ($query) use ($search) {
                $operator = $query->getConnection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';

                $query->where(function ($builder) use ($search, $operator) {
                    $builder
                        ->where('item_code', $operator, "%{$search}%")
                        ->orWhere('uom_code', $operator, "%{$search}%");
                });
            })
            ->orderBy($sortColumns[$sort], $direction)
            ->orderBy('id')
            ->paginate($perPage)
            ->withQueryString();

        return view('admin.ratios.index', [
            'ratios' => $ratios,
            'q' => $search,
            'sort' => $sort,
            'direction' => $direction,
            'perPage' => $perPage,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'item_code' => ['required', 'string', 'max:100'],
            'uom_code' => ['required', 'string', 'max:50'],
            'ratio' => ['required', 'numeric', 'gt:0'],
        ]);

        $itemCode = strtoupper(trim($data['item_code']));
        $uomCode = strtoupper(trim($data['uom_code']));

        UomRatio::updateOrCreate(
            [
                'item_code' => $itemCode,
                'uom_code' => $uomCode,
            ],
            [
                'ratio' => $data['ratio'],
            ]
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
}
