<?php

namespace App\Services;

use App\Models\InventoryItem;
use App\Models\Purchase;
use App\Models\SaleItem;
use App\Models\Shipment;
use App\Models\StockOpname;
use Illuminate\Database\Eloquent\Builder;

class InventoryItemStockCalculator
{
    public function systemQty(int $itemId, int $outletId, mixed $date = null): float
    {
        if ($date) {
            return app(StockSnapshotCalculator::class)->inventoryStockForOpnameDate(
                (string) $date,
                $outletId,
                $itemId,
            );
        }

        $received = Shipment::query()
            ->where('shipment_mode', 'inventory')
            ->where('inventory_item_id', $itemId)
            ->where('outlet_id', $outletId)
            ->where('shipment_direction', 'warehouse_to_outlet')
            ->selectRaw('COALESCE(SUM(CASE WHEN COALESCE(generic_qty_received, 0) > 0 THEN generic_qty_received ELSE generic_qty_sent END), 0) as total')
            ->value('total');

        $returnedToWarehouse = Shipment::query()
            ->where('shipment_mode', 'inventory')
            ->where('inventory_item_id', $itemId)
            ->where('outlet_id', $outletId)
            ->where('shipment_direction', 'outlet_to_warehouse')
            ->selectRaw('COALESCE(SUM(CASE WHEN COALESCE(generic_qty_sent, 0) > 0 THEN generic_qty_sent ELSE generic_qty_received END), 0) as total')
            ->value('total');

        $sold = SaleItem::query()
            ->join('sales', 'sale_items.sale_id', '=', 'sales.id')
            ->where('sale_items.inventory_item_id', $itemId)
            ->where('sales.outlet_id', $outletId)
            ->sum('sale_items.quantity');

        $previousConsumed = StockOpname::query()
            ->where('inventory_item_id', $itemId)
            ->where('outlet_id', $outletId)
            ->when($date, fn (Builder $query) => $query->whereDate('date', '<', $date))
            ->sum('generic_consumed_qty');

        return (float) $received - (float) $sold - (float) $returnedToWarehouse - (float) $previousConsumed;
    }

    public function warehouseQty(int $itemId, mixed $date = null): float
    {
        $purchased = Purchase::query()
            ->where('purchase_mode', 'inventory')
            ->where('inventory_item_id', $itemId)
            ->when($date, fn (Builder $query) => $query->whereDate('date', '<=', $date))
            ->sum('generic_qty');

        $sent = Shipment::query()
            ->where('shipment_mode', 'inventory')
            ->where('inventory_item_id', $itemId)
            ->where('shipment_direction', 'warehouse_to_outlet')
            ->when($date, fn (Builder $query) => $query->whereDate('date', '<=', $date))
            ->sum('generic_qty_sent');

        $returned = Shipment::query()
            ->where('shipment_mode', 'inventory')
            ->where('inventory_item_id', $itemId)
            ->where('shipment_direction', 'outlet_to_warehouse')
            ->when($date, fn (Builder $query) => $query->whereDate('date', '<=', $date))
            ->sum('generic_qty_received');

        return max(0, (float) $purchased - (float) $sent + (float) $returned);
    }

    public function averageUnitCost(int $itemId): float
    {
        $qty = Purchase::query()
            ->where('purchase_mode', 'inventory')
            ->where('inventory_item_id', $itemId)
            ->sum('generic_qty');

        if ((float) $qty > 0) {
            $amount = Purchase::query()
                ->where('purchase_mode', 'inventory')
                ->where('inventory_item_id', $itemId)
                ->sum('generic_total_amount');

            return (float) $amount / (float) $qty;
        }

        return (float) (InventoryItem::find($itemId)?->default_unit_cost ?? 0);
    }
}
