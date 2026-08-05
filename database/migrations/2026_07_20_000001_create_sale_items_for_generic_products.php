<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_items', function (Blueprint $table) {
            $table->boolean('is_sellable')->default(false)->after('is_active');
        });

        if (DB::connection()->getDriverName() === 'mysql') {
            Schema::table('sales', function (Blueprint $table) {
                $table->dropForeign(['durian_variety_id']);
            });

            DB::statement('ALTER TABLE sales MODIFY durian_variety_id BIGINT UNSIGNED NULL');

            Schema::table('sales', function (Blueprint $table) {
                $table->foreign('durian_variety_id')->references('id')->on('durian_varieties')->nullOnDelete();
            });
        }

        Schema::create('sale_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')->constrained()->cascadeOnDelete();
            $table->foreignId('inventory_item_id')->nullable()->constrained()->nullOnDelete();
            $table->string('item_name');
            $table->string('category')->default('produk_jualan');
            $table->string('unit')->default('pcs');
            $table->decimal('quantity', 12, 3)->default(0);
            $table->decimal('unit_price', 15, 2)->default(0);
            $table->decimal('gross_sales', 15, 2)->default(0);
            $table->decimal('discount_amount', 15, 2)->default(0);
            $table->decimal('sales_return_amount', 15, 2)->default(0);
            $table->decimal('net_sales', 15, 2)->default(0);
            $table->decimal('unit_cost', 15, 2)->default(0);
            $table->decimal('total_cost', 15, 2)->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['inventory_item_id', 'category']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sale_items');

        if (DB::connection()->getDriverName() === 'mysql') {
            Schema::table('sales', function (Blueprint $table) {
                $table->dropForeign(['durian_variety_id']);
            });

            DB::statement('ALTER TABLE sales MODIFY durian_variety_id BIGINT UNSIGNED NOT NULL');

            Schema::table('sales', function (Blueprint $table) {
                $table->foreign('durian_variety_id')->references('id')->on('durian_varieties')->cascadeOnDelete();
            });
        }

        Schema::table('inventory_items', function (Blueprint $table) {
            $table->dropColumn('is_sellable');
        });
    }
};
