<?php

namespace App\Http\Controllers\Label;

use App\Http\Controllers\Controller;
use App\Label\Repositories\SqlServerItemRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class ItemLookupController extends Controller
{
    public function __construct(private readonly SqlServerItemRepository $items)
    {
    }

    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
        ]);

        $term = trim((string) ($validated['q'] ?? ''));

        if ($term === '') {
            return response()->json(['data' => []]);
        }

        try {
            $items = $this->items->search($term, 10);
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'message' => 'Koneksi ke database barang gagal.',
                'data' => [],
            ], 503);
        }

        return response()->json([
            'data' => array_map(
                static fn ($item): array => $item->toArray(),
                $items,
            ),
        ]);
    }
}
