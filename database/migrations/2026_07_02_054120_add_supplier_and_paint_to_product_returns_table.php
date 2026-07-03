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
        $table->string('supplier_code')->nullable()->after('shipment_id');
        $table->string('paint_color')->nullable()->after('supplier_code');
    });
}

public function down(): void
{
    Schema::table('product_returns', function (Blueprint $table) {
        $table->dropColumn(['supplier_code', 'paint_color']);
    });
}
};
