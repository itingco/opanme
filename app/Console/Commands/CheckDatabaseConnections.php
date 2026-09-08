<?php

namespace App\Console\Commands;

use App\Models\ItemBarcode;
use App\Models\UomRatio;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class CheckDatabaseConnections extends Command
{
    protected $signature = 'app:check-databases {--cleanup-local-masters : Drop obsolete PostgreSQL item_barcodes/uom_ratios after all checks pass}';
    protected $description = 'Check PostgreSQL, DB_AppHub, AS_INGCO, and AS_SMI connections used by Stock Opname.';

    public function handle(): int
    {
        $checks = [
            'pgsql' => 'SELECT 1 AS ok',
            'apphub' => <<<'SQL'
                SELECT
                    DB_NAME() AS db_name,
                    OBJECT_ID('dbo.ItemBarcode','U') AS barcode_table,
                    OBJECT_ID('dbo.ItemRatio','U') AS ratio_table,
                    COL_LENGTH('dbo.ItemBarcode','ItemCode') AS barcode_itemcode,
                    COL_LENGTH('dbo.ItemBarcode','Barcode') AS barcode_barcode,
                    COL_LENGTH('dbo.ItemBarcode','UOM') AS barcode_uom,
                    COL_LENGTH('dbo.ItemRatio','ItemCode') AS ratio_itemcode,
                    COL_LENGTH('dbo.ItemRatio','UOM') AS ratio_uom,
                    COL_LENGTH('dbo.ItemRatio','Ratio') AS ratio_ratio
            SQL,
            'erp_ingco' => "SELECT DB_NAME() AS db_name, OBJECT_ID('dbo.USP_Dashboard_SisaStokWarehouse','P') AS stock_proc",
            'erp_smi' => "SELECT DB_NAME() AS db_name, OBJECT_ID('dbo.USP_Dashboard_SisaStokWarehouse','P') AS stock_proc",
        ];

        $failed = false;
        foreach ($checks as $name => $sql) {
            try {
                $row = (array) DB::connection($name)->selectOne($sql);
                $this->info(sprintf('%-12s OK  %s', $name, json_encode($row, JSON_UNESCAPED_SLASHES)));

                if ($name === 'apphub') {
                    $required = [
                        'barcode_table', 'ratio_table',
                        'barcode_itemcode', 'barcode_barcode', 'barcode_uom',
                        'ratio_itemcode', 'ratio_uom', 'ratio_ratio',
                    ];
                    foreach ($required as $field) {
                        if (empty($row[$field])) {
                            $this->error("DB_AppHub: struktur {$field} tidak ditemukan.");
                            $failed = true;
                        }
                    }
                }

                if (str_starts_with($name, 'erp_') && empty($row['stock_proc'])) {
                    $this->error("{$name}: USP_Dashboard_SisaStokWarehouse tidak ditemukan.");
                    $failed = true;
                }
            } catch (Throwable $e) {
                $this->error(sprintf('%-12s FAIL %s', $name, $e->getMessage()));
                $failed = true;
            }
        }

        if (! $failed) {
            try {
                $barcode = ItemBarcode::query()
                    ->orderBy('item_code')
                    ->orderBy('id')
                    ->first();
                $ratio = UomRatio::query()
                    ->orderBy('item_code')
                    ->orderBy('uom_code')
                    ->orderBy('id')
                    ->first();

                $this->info('apphub-model  OK  ItemBarcode='.json_encode($barcode ? [
                    'id' => $barcode->id,
                    'item_code' => $barcode->item_code,
                    'barcode' => $barcode->barcode,
                    'uom_code' => $barcode->uom_code,
                ] : null, JSON_UNESCAPED_SLASHES));

                $this->info('apphub-model  OK  ItemRatio='.json_encode($ratio ? [
                    'id' => $ratio->id,
                    'item_code' => $ratio->item_code,
                    'uom_code' => $ratio->uom_code,
                    'ratio' => $ratio->ratio,
                ] : null, JSON_UNESCAPED_SLASHES));
            } catch (Throwable $e) {
                $this->error('apphub-model FAIL '.$e->getMessage());
                $failed = true;
            }
        }

        if (! $failed && $this->option('cleanup-local-masters')) {
            Schema::dropIfExists('item_barcodes');
            Schema::dropIfExists('uom_ratios');
            $this->warn('Obsolete PostgreSQL master tables item_barcodes/uom_ratios sudah dihapus.');
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
