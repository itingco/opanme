<?php

namespace App\Label\Repositories;

use App\Label\Data\ItemData;
use Illuminate\Support\Facades\DB;

final class SqlServerItemRepository
{
    public function findByCode(string $itemCode): ?ItemData
    {
        $row = DB::connection('erp_smi')
            ->table('IC_Items')
            ->select(['ItemCode', 'ItemName'])
            ->where('ItemCode', trim($itemCode))
            ->first();

        if ($row === null) {
            return null;
        }

        return new ItemData(
            code: trim((string) $row->ItemCode),
            name: trim((string) $row->ItemName),
        );
    }

    public function search(string $term, int $limit = 10): array
    {
        $term = trim($term);
        $limit = max(1, min($limit, 25));

        if ($term === '') {
            return [];
        }

        return DB::connection('erp_smi')
            ->table('IC_Items')
            ->select(['ItemCode', 'ItemName'])
            ->where(function ($query) use ($term): void {
                $query->where('ItemCode', 'like', "%{$term}%")
                    ->orWhere('ItemName', 'like', "%{$term}%");
            })
            ->orderByRaw('CASE WHEN ItemCode = ? THEN 0 WHEN ItemCode LIKE ? THEN 1 ELSE 2 END', [
                $term,
                $term.'%',
            ])
            ->orderBy('ItemCode')
            ->limit($limit)
            ->get()
            ->map(static fn ($row): ItemData => new ItemData(
                code: trim((string) $row->ItemCode),
                name: trim((string) $row->ItemName),
            ))
            ->all();
    }
}
