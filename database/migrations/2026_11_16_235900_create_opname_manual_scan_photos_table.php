<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('opname_manual_scan_photos')) return;
        Schema::create('opname_manual_scan_photos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('scan_transaction_id')->unique();
            $table->unsignedBigInteger('cycle_id')->index();
            $table->unsignedBigInteger('warehouse_id');
            $table->unsignedBigInteger('checker_id');
            $table->string('photo_path', 500);
            $table->timestamp('created_at');
        });
    }
    public function down(): void { Schema::dropIfExists('opname_manual_scan_photos'); }
};
