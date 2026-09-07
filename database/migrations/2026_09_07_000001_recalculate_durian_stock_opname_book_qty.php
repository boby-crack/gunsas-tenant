<?php

use App\Services\StockSnapshotCalculator;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('stock_opnames')) {
            return;
        }

        $calculator = app(StockSnapshotCalculator::class);

        DB::table('stock_opnames')
            ->where('product_type', '<>', 'Inventory Item')
            ->whereNotNull('durian_variety_id')
            ->orderBy('date')
            ->orderBy('id')
            ->select(['id', 'outlet_id', 'durian_variety_id', 'date', 'product_type', 'physical_qty_kg'])
            ->chunk(200, function ($opnames) use ($calculator): void {
                foreach ($opnames as $opname) {
                    $systemQty = round($calculator->durianStockForOpnameDate(
                        (string) $opname->date,
                        (int) $opname->outlet_id,
                        (int) $opname->durian_variety_id,
                        (string) $opname->product_type,
                    ), 3);
                    $differenceQty = round((float) $opname->physical_qty_kg - $systemQty, 3);

                    DB::table('stock_opnames')
                        ->where('id', $opname->id)
                        ->update([
                            'system_qty_kg' => $systemQty,
                            'difference_qty_kg' => $differenceQty,
                        ]);
                }
            });
    }

    public function down(): void
    {
        //
    }
};
