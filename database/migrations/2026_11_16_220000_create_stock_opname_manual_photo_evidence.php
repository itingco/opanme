<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('stock_opname_manual_photo_evidence', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('scan_transaction_id')->unique();
            $table->unsignedBigInteger('cycle_id')->index();
            $table->unsignedBigInteger('warehouse_id')->index();
            $table->unsignedBigInteger('checker_id');
            $table->string('file_path', 500);
            $table->timestamp('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_opname_manual_photo_evidence');
    }
};
