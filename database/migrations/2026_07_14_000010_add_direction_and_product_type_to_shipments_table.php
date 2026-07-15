<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            $table->string('shipment_direction')->default('warehouse_to_outlet')->after('shipment_mode');
            $table->string('product_type')->default('Buah Utuh')->after('shipment_direction');
            $table->decimal('qty_received_kg', 12, 3)->default(0)->after('qty_sent_kg');
        });

        DB::table('shipments')->update([
            'shipment_direction' => 'warehouse_to_outlet',
            'product_type' => 'Buah Utuh',
            'qty_received_kg' => DB::raw('qty_sent_kg'),
        ]);
    }

    public function down(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            $table->dropColumn(['shipment_direction', 'product_type', 'qty_received_kg']);
        });
    }
};
