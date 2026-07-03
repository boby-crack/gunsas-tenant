<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_returns', function (Blueprint $table) {
            // Menghubungkan retur ke ID Pengiriman tertentu
            $table->foreignId('shipment_id')->nullable()->constrained('shipments')->onDelete('set null')->after('durian_variety_id');
        });
    }

    public function down(): void
    {
        Schema::table('product_returns', function (Blueprint $table) {
            $table->dropForeign(['shipment_id']);
            $table->dropColumn('shipment_id');
        });
    }
};