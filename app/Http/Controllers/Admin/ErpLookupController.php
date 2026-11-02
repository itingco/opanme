<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\StockOpnameCycle;
use App\Services\ErpCatalogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Throwable;
use UnexpectedValueException;

class ErpLookupController extends Controller
{
    public function warehouses(Request $request, ErpCatalogService $erp): JsonResponse
    {
        $data = $request->validate([
            'source_database' => ['required', Rule::in([StockOpnameCycle::DB_INGCO, StockOpnameCycle::DB_SMI])],
        ]);

        try {
            return response()->json(['data' => $erp->warehouses($data['source_database'])]);
        } catch (Throwable $exception) {
            $reference = 'ERP-WH-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));
            Log::error('Gagal membaca warehouse ERP untuk Buat Cycle', [
                'reference' => $reference,
                'source_database' => $data['source_database'],
                'user_id' => $request->user()?->id,
                'exception' => $exception,
            ]);
            return response()->json([
                'message' => 'Daftar warehouse tidak dapat dibaca dari ERP. Periksa koneksi SQL Server, izin akses IC_Warehouses, dan konfigurasi database.',
                'reference' => $reference,
            ], 503);
        }
    }

    public function barcode(Request $request, ErpCatalogService $erp): JsonResponse
    {
        $data = $request->validate([
            'source_database' => ['required', Rule::in([StockOpnameCycle::DB_INGCO, StockOpnameCycle::DB_SMI])],
            'barcode' => ['required', 'string', 'max:150'],
        ]);
        try {
            $item = $erp->findBarcode($data['source_database'], $data['barcode']);
        } catch (UnexpectedValueException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }
        if (! $item) {
            return response()->json(['message' => 'Barcode tidak ditemukan.'], 404);
        }
        return response()->json(['data' => $item]);
    }
}
