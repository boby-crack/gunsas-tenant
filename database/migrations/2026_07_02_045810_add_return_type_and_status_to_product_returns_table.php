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
        Schema::table('product_returns', function (Blueprint $table) {
            // Menambahkan kolom tipe retur dan status persetujuan supplier
            $table->string('return_type')->default('outlet_to_gudang')->after('id');
            $table->string('status')->default('pending')->after('return_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('product_returns', function (Blueprint $table) {
            $table->dropColumn(['return_type', 'status']);
        });
    }
};