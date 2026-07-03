<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
{
    Schema::table('purchases', function (Blueprint $table) {
        // Menambahkan kode supplier di nota pembelian durian dari kebun/supplier
        $table->string('supplier_code')->nullable()->after('id');
    });
}

public function down(): void
{
    Schema::table('purchases', function (Blueprint $table) {
        $table->dropColumn('supplier_code');
    });
}
};
