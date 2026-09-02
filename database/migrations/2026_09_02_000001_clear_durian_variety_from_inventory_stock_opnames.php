<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('stock_opnames')
            ->where('product_type', 'Inventory Item')
            ->whereNotNull('durian_variety_id')
            ->update(['durian_variety_id' => null]);
    }

    public function down(): void
    {
        //
    }
};
