<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_returns', function (Blueprint $table) {
            // Kolom perantara: Apa yang dikirim gudang pusat ke supplier
            $table->integer('qty_to_supplier_butir')->nullable()->after('qty_butir');
            $table->decimal('qty_to_supplier_kg', 8, 3)->nullable()->after('qty_kg');
        });
    }

    public function down(): void
    {
        Schema::table('product_returns', function (Blueprint $table) {
            $table->dropColumn(['qty_to_supplier_butir', 'qty_to_supplier_kg']);
        });
    }
};