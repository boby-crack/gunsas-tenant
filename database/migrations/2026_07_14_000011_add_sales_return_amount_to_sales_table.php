<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->decimal('sales_return_amount', 15, 2)
                ->default(0)
                ->after('discount_amount');
        });

        $expression = DB::connection()->getDriverName() === 'sqlite'
            ? 'MAX(grand_total_revenue - discount_amount - net_sales, 0)'
            : 'GREATEST(grand_total_revenue - discount_amount - net_sales, 0)';

        DB::table('sales')
            ->where('net_sales', '>', 0)
            ->update([
                'sales_return_amount' => DB::raw($expression),
            ]);
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropColumn('sales_return_amount');
        });
    }
};
