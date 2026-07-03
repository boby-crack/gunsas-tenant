<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_returns', function (Blueprint $table) {
            // Kolom untuk mencatat berapa yang benar-benar diterima supplier
            $table->integer('supplier_accepted_qty_butir')->nullable()->after('qty_butir');
            $table->decimal('supplier_accepted_qty_kg', 8, 3)->nullable()->after('qty_kg');
            
            // Kolom untuk mencatat nominal uang yang dikembalikan supplier
            $table->decimal('refund_amount', 15, 2)->default(0)->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('product_returns', function (Blueprint $table) {
            $table->dropColumn(['supplier_accepted_qty_butir', 'supplier_accepted_qty_kg', 'refund_amount']);
        });
    }
};