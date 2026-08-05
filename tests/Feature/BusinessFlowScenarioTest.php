<?php

namespace Tests\Feature;

use App\Models\DurianVariety;
use App\Models\Expense;
use App\Models\InventoryItem;
use App\Models\Outlet;
use App\Models\ProductConversion;
use App\Models\ProductReturn;
use App\Models\Production;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Shipment;
use App\Models\StockOpname;
use App\Services\BusinessInsightsCalculator;
use App\Services\StockSnapshotCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class BusinessFlowScenarioTest extends TestCase
{
    use RefreshDatabase;

    public function test_complete_tiptop_flow_matches_stock_and_profit_logic(): void
    {
        $this->registerSqliteCompatibilityFunctions();

        $outlet = Outlet::create([
            'name' => 'TIPTOP RAWAMANGUN',
            'group_name' => 'tiptop',
            'partner_share_percent' => 15,
        ]);

        $variety = DurianVariety::create(['name' => 'MONTHONG']);

        $thinwall = InventoryItem::create([
            'name' => 'Thinwall',
            'category' => 'packaging',
            'unit' => 'pcs',
            'default_unit_cost' => 5000,
            'is_active' => true,
            'is_sellable' => false,
        ]);

        $pancake = InventoryItem::create([
            'name' => 'Pancake isi 8',
            'category' => 'produk_jualan',
            'unit' => 'unit',
            'default_unit_cost' => 20000,
            'is_active' => true,
            'is_sellable' => true,
        ]);

        $purchase = Purchase::create([
            'supplier_code' => 'bb',
            'date' => '2026-07-01',
            'durian_variety_id' => $variety->id,
            'supplier_name' => 'Pak Bambang',
            'qty_butir' => 44,
            'qty_kg' => 110,
            'price_per_kg' => 50000,
            'total_amount' => 5500000,
        ]);

        $this->assertSame('BB', $purchase->supplier_code);

        Shipment::create([
            'outlet_id' => $outlet->id,
            'shipment_mode' => 'durian',
            'shipment_direction' => 'warehouse_to_outlet',
            'product_type' => 'Buah Utuh',
            'durian_variety_id' => $variety->id,
            'date' => '2026-07-01',
            'modal_price' => 50000,
            'qty_sent_butir' => 40,
            'qty_received_butir' => 40,
            'qty_sent_kg' => 100,
            'qty_received_kg' => 100,
            'average_weight' => 2.5,
            'value_purchase' => 5000000,
        ]);

        Shipment::create([
            'outlet_id' => $outlet->id,
            'inventory_item_id' => $thinwall->id,
            'shipment_mode' => 'inventory',
            'shipment_direction' => 'warehouse_to_outlet',
            'product_type' => 'Inventory Item',
            'durian_variety_id' => $variety->id,
            'date' => '2026-07-01',
            'modal_price' => 0,
            'qty_sent_butir' => 0,
            'qty_received_butir' => 0,
            'qty_sent_kg' => 0,
            'qty_received_kg' => 0,
            'average_weight' => 0,
            'value_purchase' => 0,
            'generic_qty_sent' => 10,
            'generic_qty_received' => 10,
            'generic_unit' => 'pcs',
            'generic_unit_cost' => 5000,
            'generic_total_amount' => 50000,
        ]);

        Production::create([
            'outlet_id' => $outlet->id,
            'durian_variety_id' => $variety->id,
            'date' => '2026-07-02',
            'source_type' => Production::SOURCE_NORMAL,
            'qty_buah_butir' => 12,
            'qty_buah_kg' => 30,
            'qty_kupas_pack' => 10,
            'qty_kupas_kg' => 10,
            'qty_olahan_pack' => 0,
            'qty_olahan_kg' => 0,
            'total_usable_meat_kg' => 10,
            'shrinkage_percentage' => 66.67,
            'multiplier_factor' => 3,
        ]);

        $return = ProductReturn::create([
            'outlet_id' => $outlet->id,
            'durian_variety_id' => $variety->id,
            'return_type' => 'supplier',
            'supplier_code' => 'bb',
            'date' => '2026-07-02',
            'return_reason_type' => 'Tidak Manis',
            'qty_butir' => 4,
            'qty_kg' => 10,
            'detailed_reason' => 'Contoh retur supplier',
            'status' => 'accepted',
            'supplier_accepted_qty_butir' => 3,
            'supplier_accepted_qty_kg' => 8,
            'refund_amount' => 200000,
        ]);

        $this->assertSame('BB', $return->supplier_code);

        Production::create([
            'outlet_id' => $outlet->id,
            'durian_variety_id' => $variety->id,
            'date' => '2026-07-02',
            'source_type' => Production::SOURCE_RETURN,
            'qty_buah_butir' => 4,
            'qty_buah_kg' => 10,
            'qty_kupas_pack' => 2,
            'qty_kupas_kg' => 2,
            'qty_olahan_pack' => 0,
            'qty_olahan_kg' => 4,
            'total_usable_meat_kg' => 6,
            'shrinkage_percentage' => 40,
            'multiplier_factor' => 5,
        ]);

        ProductConversion::create([
            'outlet_id' => $outlet->id,
            'durian_variety_id' => $variety->id,
            'date' => '2026-07-02',
            'conversion_type' => ProductConversion::TYPE_FRESH_TO_FROZEN,
            'from_qty_pack' => 2,
            'from_qty_kg' => 2,
            'to_qty_pack' => 2,
            'to_qty_kg' => 2,
        ]);

        Sale::create([
            'outlet_id' => $outlet->id,
            'durian_variety_id' => $variety->id,
            'date' => '2026-07-01',
            'buah_sold_kg' => 30,
            'buah_sold_butir' => 12,
            'buah_price_per_kg' => 100000,
            'buah_subtotal' => 3000000,
            'fresh_sold_kg' => 0,
            'fresh_sold_pack' => 0,
            'fresh_price_per_kg' => 0,
            'fresh_subtotal' => 0,
            'frozen_sold_kg' => 0,
            'frozen_sold_pack' => 0,
            'frozen_price_per_kg' => 0,
            'frozen_subtotal' => 0,
            'grand_total_revenue' => 3000000,
            'discount_amount' => 100000,
            'sales_return_amount' => 50000,
            'net_sales' => 2850000,
        ]);

        Sale::create([
            'outlet_id' => $outlet->id,
            'durian_variety_id' => $variety->id,
            'date' => '2026-07-03',
            'buah_sold_kg' => 0,
            'buah_sold_butir' => 0,
            'buah_price_per_kg' => 0,
            'buah_subtotal' => 0,
            'fresh_sold_kg' => 6,
            'fresh_sold_pack' => 6,
            'fresh_price_per_kg' => 300000,
            'fresh_subtotal' => 1800000,
            'frozen_sold_kg' => 0,
            'frozen_sold_pack' => 0,
            'frozen_price_per_kg' => 0,
            'frozen_subtotal' => 0,
            'grand_total_revenue' => 1800000,
            'discount_amount' => 0,
            'sales_return_amount' => 0,
            'net_sales' => 1800000,
        ]);

        Sale::create([
            'outlet_id' => $outlet->id,
            'durian_variety_id' => $variety->id,
            'date' => '2026-07-03',
            'buah_sold_kg' => 0,
            'buah_sold_butir' => 0,
            'buah_price_per_kg' => 0,
            'buah_subtotal' => 0,
            'fresh_sold_kg' => 0,
            'fresh_sold_pack' => 0,
            'fresh_price_per_kg' => 0,
            'fresh_subtotal' => 0,
            'frozen_sold_kg' => 1,
            'frozen_sold_pack' => 1,
            'frozen_price_per_kg' => 250000,
            'frozen_subtotal' => 250000,
            'grand_total_revenue' => 250000,
            'discount_amount' => 0,
            'sales_return_amount' => 0,
            'net_sales' => 250000,
        ]);

        $nonDurianSale = Sale::create([
            'outlet_id' => $outlet->id,
            'durian_variety_id' => $variety->id,
            'date' => '2026-07-03',
            'buah_sold_kg' => 0,
            'buah_sold_butir' => 0,
            'buah_price_per_kg' => 0,
            'buah_subtotal' => 0,
            'fresh_sold_kg' => 0,
            'fresh_sold_pack' => 0,
            'fresh_price_per_kg' => 0,
            'fresh_subtotal' => 0,
            'frozen_sold_kg' => 0,
            'frozen_sold_pack' => 0,
            'frozen_price_per_kg' => 0,
            'frozen_subtotal' => 0,
            'grand_total_revenue' => 96000,
            'discount_amount' => 0,
            'sales_return_amount' => 0,
            'net_sales' => 96000,
        ]);

        SaleItem::create([
            'sale_id' => $nonDurianSale->id,
            'inventory_item_id' => $pancake->id,
            'item_name' => 'Pancake isi 8',
            'category' => 'produk_jualan',
            'unit' => 'unit',
            'quantity' => 3,
            'unit_price' => 32000,
            'gross_sales' => 96000,
            'discount_amount' => 0,
            'sales_return_amount' => 0,
            'net_sales' => 96000,
            'unit_cost' => 20000,
            'total_cost' => 60000,
        ]);

        Expense::create([
            'date' => '2026-07-02',
            'outlet_id' => $outlet->id,
            'allocation_scope' => 'outlet',
            'category' => 'Bensin & Tol',
            'amount' => 100000,
        ]);

        StockOpname::create([
            'outlet_id' => $outlet->id,
            'durian_variety_id' => $variety->id,
            'date' => '2026-07-03',
            'product_type' => 'Buah Utuh',
            'system_qty_kg' => 30,
            'physical_qty_kg' => 10,
            'difference_qty_kg' => -20,
        ]);

        StockOpname::create([
            'outlet_id' => $outlet->id,
            'durian_variety_id' => $variety->id,
            'date' => '2026-07-03',
            'product_type' => 'Daging Fresh',
            'system_qty_kg' => 4,
            'physical_qty_kg' => 1,
            'difference_qty_kg' => -3,
        ]);

        StockOpname::create([
            'outlet_id' => $outlet->id,
            'durian_variety_id' => $variety->id,
            'date' => '2026-07-03',
            'product_type' => 'Daging Frozen',
            'system_qty_kg' => 1,
            'physical_qty_kg' => 1,
            'difference_qty_kg' => 0,
        ]);

        StockOpname::create([
            'outlet_id' => $outlet->id,
            'inventory_item_id' => $thinwall->id,
            'durian_variety_id' => $variety->id,
            'date' => '2026-07-03',
            'product_type' => 'Inventory Item',
            'system_qty_kg' => 0,
            'physical_qty_kg' => 0,
            'difference_qty_kg' => 0,
            'generic_unit' => 'pcs',
            'generic_consumed_qty' => 6,
            'generic_unit_cost' => 5000,
            'generic_consumed_amount' => 30000,
        ]);

        $stock = app(StockSnapshotCalculator::class);

        $this->assertFloatEquals(30, $stock->durianStockForOpnameDate('2026-07-03', $outlet->id, $variety->id, 'Buah Utuh'));
        $this->assertFloatEquals(4, $stock->durianStockForOpnameDate('2026-07-03', $outlet->id, $variety->id, 'Daging Fresh'));
        $this->assertFloatEquals(1, $stock->durianStockForOpnameDate('2026-07-03', $outlet->id, $variety->id, 'Daging Frozen'));

        $this->assertFloatEquals(10, $stock->durianStockForDate('2026-07-03', $outlet->id, $variety->id, 'Buah Utuh'));
        $this->assertFloatEquals(1, $stock->durianStockForDate('2026-07-03', $outlet->id, $variety->id, 'Daging Fresh'));
        $this->assertFloatEquals(1, $stock->durianStockForDate('2026-07-03', $outlet->id, $variety->id, 'Daging Frozen'));

        $insights = app(BusinessInsightsCalculator::class)->calculate([
            'date_from' => '2026-07-01',
            'date_until' => '2026-07-03',
            'outlet_id' => $outlet->id,
        ], true);

        $this->assertFloatEquals(4996000, $insights['sales']['net_sales']);
        $this->assertFloatEquals(4246600, $insights['sales']['gunsas_revenue']);
        $this->assertFloatEquals(50000, $insights['costs']['avg_modal_buah']);
        $this->assertFloatEquals(150000, $insights['costs']['avg_modal_fresh']);
        $this->assertFloatEquals(150000, $insights['costs']['avg_modal_frozen']);
        $this->assertFloatEquals(2310000, $insights['costs']['hpp_sales']);
        $this->assertFloatEquals(2, $insights['returns']['recovery']['sold_kg']);
        $this->assertFloatEquals(300000, $insights['returns']['recovery']['hpp_saved_amount']);
        $this->assertFloatEquals(300000, $insights['profit']['return_recovery_hpp_saved']);
        $this->assertFloatEquals(100000, $insights['costs']['expenses']);
        $this->assertFloatEquals(30000, $insights['costs']['inventory_usage']);
        $this->assertFloatEquals(300000, $insights['returns']['loss_final']);
        $this->assertFloatEquals(1450000, $insights['costs']['opname_loss']);
        $this->assertFloatEquals(56600, $insights['profit']['net_profit']);
        $this->assertFloatEquals(800000, $insights['inventory']['amount']);

        $inventoryUsageJson = json_encode($insights['costs']['inventory_usage_items'], JSON_THROW_ON_ERROR);
        $this->assertStringContainsString('Thinwall', $inventoryUsageJson);
        $this->assertStringNotContainsString('Pancake isi 8', $inventoryUsageJson);

        $salesByProductJson = json_encode($insights['sales_by_product'], JSON_THROW_ON_ERROR);
        $this->assertStringContainsString('Pancake isi 8', $salesByProductJson);

        $this->assertNotEmpty($insights['stock_movement']['rows']);
    }

    private function assertFloatEquals(float $expected, mixed $actual, float $delta = 0.01): void
    {
        $this->assertEqualsWithDelta($expected, (float) $actual, $delta);
    }

    private function registerSqliteCompatibilityFunctions(): void
    {
        if (DB::connection()->getDriverName() !== 'sqlite') {
            return;
        }

        DB::connection()->getPdo()->sqliteCreateFunction('GREATEST', function (...$values) {
            return max(array_map(fn ($value) => (float) $value, $values));
        });

        DB::connection()->getPdo()->sqliteCreateFunction('LEAST', function (...$values) {
            return min(array_map(fn ($value) => (float) $value, $values));
        });
    }
}
