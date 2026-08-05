<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_items', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('sku')->nullable();
            $table->string('category')->default('lainnya');
            $table->string('unit')->default('pcs');
            $table->foreignId('durian_variety_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('default_unit_cost', 15, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::table('purchases', function (Blueprint $table) {
            $table->foreignId('inventory_item_id')->nullable()->after('id')->constrained()->nullOnDelete();
            $table->string('purchase_mode')->default('durian')->after('inventory_item_id');
            $table->decimal('generic_qty', 12, 3)->default(0)->after('qty_kg');
            $table->string('generic_unit')->nullable()->after('generic_qty');
            $table->decimal('generic_unit_cost', 15, 2)->default(0)->after('generic_unit');
            $table->decimal('generic_total_amount', 15, 2)->default(0)->after('generic_unit_cost');
        });

        Schema::table('shipments', function (Blueprint $table) {
            $table->foreignId('inventory_item_id')->nullable()->after('outlet_id')->constrained()->nullOnDelete();
            $table->string('shipment_mode')->default('durian')->after('inventory_item_id');
            $table->decimal('generic_qty_sent', 12, 3)->default(0)->after('qty_sent_kg');
            $table->decimal('generic_qty_received', 12, 3)->default(0)->after('generic_qty_sent');
            $table->string('generic_unit')->nullable()->after('generic_qty_received');
            $table->decimal('generic_unit_cost', 15, 2)->default(0)->after('generic_unit');
            $table->decimal('generic_total_amount', 15, 2)->default(0)->after('generic_unit_cost');
        });

        Schema::table('stock_opnames', function (Blueprint $table) {
            $table->foreignId('inventory_item_id')->nullable()->after('outlet_id')->constrained()->nullOnDelete();
            $table->string('generic_unit')->nullable()->after('difference_qty_kg');
            $table->decimal('generic_consumed_qty', 12, 3)->default(0)->after('generic_unit');
            $table->decimal('generic_unit_cost', 15, 2)->default(0)->after('generic_consumed_qty');
            $table->decimal('generic_consumed_amount', 15, 2)->default(0)->after('generic_unit_cost');
        });

        $this->relaxLegacyDurianColumns();
    }

    public function down(): void
    {
        Schema::table('stock_opnames', function (Blueprint $table) {
            $table->dropConstrainedForeignId('inventory_item_id');
            $table->dropColumn(['generic_unit', 'generic_consumed_qty', 'generic_unit_cost', 'generic_consumed_amount']);
        });

        Schema::table('shipments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('inventory_item_id');
            $table->dropColumn([
                'shipment_mode',
                'generic_qty_sent',
                'generic_qty_received',
                'generic_unit',
                'generic_unit_cost',
                'generic_total_amount',
            ]);
        });

        Schema::table('purchases', function (Blueprint $table) {
            $table->dropConstrainedForeignId('inventory_item_id');
            $table->dropColumn([
                'purchase_mode',
                'generic_qty',
                'generic_unit',
                'generic_unit_cost',
                'generic_total_amount',
            ]);
        });

        Schema::dropIfExists('inventory_items');
    }

    private function relaxLegacyDurianColumns(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        Schema::table('purchases', fn (Blueprint $table) => $table->dropForeign(['durian_variety_id']));
        Schema::table('shipments', fn (Blueprint $table) => $table->dropForeign(['durian_variety_id']));
        Schema::table('stock_opnames', fn (Blueprint $table) => $table->dropForeign(['durian_variety_id']));

        DB::statement('ALTER TABLE purchases MODIFY durian_variety_id BIGINT UNSIGNED NULL');
        DB::statement('ALTER TABLE purchases MODIFY qty_kg DECIMAL(8,3) NOT NULL DEFAULT 0');
        DB::statement('ALTER TABLE purchases MODIFY price_per_kg DECIMAL(15,2) NOT NULL DEFAULT 0');
        DB::statement('ALTER TABLE purchases MODIFY total_amount DECIMAL(15,2) NOT NULL DEFAULT 0');

        DB::statement('ALTER TABLE shipments MODIFY durian_variety_id BIGINT UNSIGNED NULL');
        DB::statement('ALTER TABLE shipments MODIFY modal_price DECIMAL(15,2) NOT NULL DEFAULT 0');
        DB::statement('ALTER TABLE shipments MODIFY qty_sent_butir INT NOT NULL DEFAULT 0');
        DB::statement('ALTER TABLE shipments MODIFY qty_received_butir INT NOT NULL DEFAULT 0');
        DB::statement('ALTER TABLE shipments MODIFY qty_sent_kg DECIMAL(8,3) NOT NULL DEFAULT 0');
        DB::statement('ALTER TABLE shipments MODIFY average_weight DECIMAL(8,3) NOT NULL DEFAULT 0');
        DB::statement('ALTER TABLE shipments MODIFY value_purchase DECIMAL(15,2) NOT NULL DEFAULT 0');

        DB::statement('ALTER TABLE stock_opnames MODIFY durian_variety_id BIGINT UNSIGNED NULL');
        DB::statement('ALTER TABLE stock_opnames MODIFY system_qty_kg DECIMAL(8,3) NOT NULL DEFAULT 0');
        DB::statement('ALTER TABLE stock_opnames MODIFY physical_qty_kg DECIMAL(8,3) NOT NULL DEFAULT 0');
        DB::statement('ALTER TABLE stock_opnames MODIFY difference_qty_kg DECIMAL(8,3) NOT NULL DEFAULT 0');

        Schema::table('purchases', fn (Blueprint $table) => $table->foreign('durian_variety_id')->references('id')->on('durian_varieties')->nullOnDelete());
        Schema::table('shipments', fn (Blueprint $table) => $table->foreign('durian_variety_id')->references('id')->on('durian_varieties')->nullOnDelete());
        Schema::table('stock_opnames', fn (Blueprint $table) => $table->foreign('durian_variety_id')->references('id')->on('durian_varieties')->nullOnDelete());
    }
};
