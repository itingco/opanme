<?php

namespace App\Http\Controllers\Label;

use App\Http\Controllers\Controller;
use App\Label\Repositories\SqlServerItemRepository;
use App\Models\ItemBarcode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class ItemLookupController extends Controller
{
    public function __construct(private readonly SqlServerItemRepository $items) {}

    public function __invoke(Request $request): JsonResponse
    {
        $data = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'item_code' => ['nullable', 'string', 'max:100'],
        ]);

        // Dipanggil setelah user memilih barang. Jangan gabungkan hasil antar barang.
        if (filled($data['item_code'] ?? null)) {
            $code = trim($data['item_code']);
            try {
                $rows = ItemBarcode::forItemCode($code);

                return response()->json(['barcodes' => $rows->map(static fn ($row): array => [
                    'id' => (int) $row->id,
                    'barcode' => trim((string) $row->barcode),
                    'uom' => trim((string) $row->uom_code),
                ])->values()->all()]);
            } catch (Throwable $e) {
                report($e);
                return response()->json(['message' => 'Gagal membaca master barcode DB_AppHub.'], 503);
            }
        }

        $term = trim((string) ($data['q'] ?? ''));
        if ($term === '') return response()->json(['data' => []]);

        try {
            $items = $this->items->search($term, 10);
        } catch (Throwable $e) {
            report($e);
            return response()->json(['message' => 'Koneksi ke database barang gagal.', 'data' => []], 503);
        }

        return response()->json(['data' => array_map(static fn ($item): array => $item->toArray(), $items)]);
    }
}
