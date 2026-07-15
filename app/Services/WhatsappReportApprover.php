<?php

namespace App\Services;

use App\Models\InventoryItem;
use App\Models\ProductConversion;
use App\Models\ProductReturn;
use App\Models\Production;
use App\Models\Sale;
use App\Models\Shipment;
use App\Models\StockOpname;
use App\Models\WhatsappReport;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class WhatsappReportApprover
{
    public function approve(WhatsappReport $report): array
    {
        if ($report->status === 'approved') {
            return [
                'ok' => false,
                'message' => 'Draft #' . $report->id . ' sudah pernah di-approve.',
            ];
        }

        $payload = $report->parsed_payload ?? [];
        $missingFields = $payload['missing_fields'] ?? [];

        if (! empty($missingFields)) {
            return [
                'ok' => false,
                'message' => 'Draft ini belum lengkap. Yang kurang: ' . implode(', ', $missingFields),
            ];
        }

        return DB::transaction(function () use ($report, $payload) {
            $target = match ($report->report_type) {
                'retur' => $this->createProductReturn($payload),
                'rijek' => $this->createReturnProduction($payload),
                'kupas' => $this->createProduction($payload),
                'frozen' => $this->createProductConversion($payload),
                'opname' => $this->createStockOpnames($payload),
                default => null,
            };

            if (! $target) {
                return [
                    'ok' => false,
                    'message' => 'Jenis laporannya belum kebaca, jadi belum bisa aku approve.',
                ];
            }

            $targetRecord = is_array($target) ? ($target[0] ?? null) : $target;

            if (! $targetRecord instanceof Model) {
                return [
                    'ok' => false,
                    'message' => 'Draft ini belum menghasilkan transaksi.',
                ];
            }

            $report->update([
                'status' => 'approved',
                'target_type' => $targetRecord::class,
                'target_id' => $targetRecord->id,
                'approved_at' => now(),
            ]);

            return [
                'ok' => true,
                'message' => 'Siap, draft #' . $report->id . ' sudah aku approve.',
                'target' => $targetRecord,
                'target_label' => is_array($target)
                    ? $this->targetArrayLabel($target)
                    : $this->targetLabel($targetRecord),
            ];
        });
    }

    /**
     * @return array<int, Model>
     */
    private function createReturnProduction(array $payload): array
    {
        $productReturn = $this->createProductReturn($payload);
        $production = $this->createProduction(array_merge($payload, [
            'source_type' => Production::SOURCE_RETURN,
            'qty_buah_butir' => $payload['qty_buah_butir'] ?? $payload['qty_butir'] ?? 1,
            'qty_buah_kg' => $payload['qty_buah_kg'] ?? $payload['qty_kg'],
        ]));

        return [$productReturn, $production];
    }

    private function createProductReturn(array $payload): ProductReturn
    {
        return ProductReturn::create([
            'outlet_id' => $payload['outlet_id'],
            'durian_variety_id' => $payload['durian_variety_id'],
            'date' => $payload['date'],
            'supplier_code' => $payload['supplier_code'] ?? null,
            'paint_color' => $payload['paint_color'] ?? null,
            'return_reason_type' => $payload['return_reason_type'] ?? 'Buah Rusak / Asam',
            'qty_butir' => $payload['qty_butir'] ?? 0,
            'qty_kg' => $payload['qty_kg'],
            'detailed_reason' => $payload['detailed_reason'] ?? 'Input dari WhatsApp',
            'status' => 'pending',
            'refund_amount' => 0,
        ]);
    }

    private function createProduction(array $payload): Production
    {
        return Production::create([
            'outlet_id' => $payload['outlet_id'],
            'durian_variety_id' => $payload['durian_variety_id'],
            'date' => $payload['date'],
            'source_type' => $payload['source_type'] ?? Production::SOURCE_NORMAL,
            'qty_buah_butir' => $payload['qty_buah_butir'] ?? 0,
            'qty_buah_kg' => $payload['qty_buah_kg'],
            'qty_kupas_pack' => $payload['qty_kupas_pack'] ?? 0,
            'qty_kupas_kg' => $payload['qty_kupas_kg'],
            'qty_olahan_pack' => $payload['qty_olahan_pack'] ?? 0,
            'qty_olahan_kg' => $payload['qty_olahan_kg'] ?? 0,
            'total_usable_meat_kg' => $payload['total_usable_meat_kg'] ?? (($payload['qty_kupas_kg'] ?? 0) + ($payload['qty_olahan_kg'] ?? 0)),
            'shrinkage_percentage' => $payload['shrinkage_percentage'] ?? 0,
            'multiplier_factor' => $payload['multiplier_factor'] ?? 0,
        ]);
    }

    private function createProductConversion(array $payload): ProductConversion
    {
        return ProductConversion::create([
            'outlet_id' => $payload['outlet_id'],
            'durian_variety_id' => $payload['durian_variety_id'],
            'date' => $payload['date'],
            'conversion_type' => 'Kupas Fresh ke Kupas Frozen',
            'from_qty_pack' => $payload['from_qty_pack'] ?? 0,
            'from_qty_kg' => $payload['from_qty_kg'],
            'to_qty_pack' => $payload['to_qty_pack'] ?? 0,
            'to_qty_kg' => $payload['to_qty_kg'],
            'notes' => $payload['notes'] ?? 'Input dari WhatsApp',
        ]);
    }

    /**
     * @return array<int, StockOpname>
     */
    private function createStockOpnames(array $payload): array
    {
        $records = [];

        foreach ($payload['opname_items'] ?? [] as $item) {
            $systemQty = $this->durianSystemQty(
                (int) $payload['outlet_id'],
                (int) $payload['durian_variety_id'],
                (string) $item['product_type'],
                $payload['date'],
            );
            $physicalQty = (float) ($item['physical_qty_kg'] ?? 0);

            $records[] = StockOpname::create([
                'outlet_id' => $payload['outlet_id'],
                'durian_variety_id' => $payload['durian_variety_id'],
                'inventory_item_id' => null,
                'date' => $payload['date'],
                'product_type' => $item['product_type'],
                'system_qty_kg' => round(max(0, $systemQty), 3),
                'physical_qty_kg' => round($physicalQty, 3),
                'difference_qty_kg' => round($physicalQty - max(0, $systemQty), 3),
                'notes' => $this->opnameNotes($payload, $item),
            ]);
        }

        foreach ($payload['inventory_items'] ?? [] as $item) {
            $inventoryItem = $this->inventoryItemForOpname($item);
            $date = $payload['date'];
            $systemQty = app(InventoryItemStockCalculator::class)->systemQty((int) $inventoryItem->id, (int) $payload['outlet_id'], $date);
            $unitCost = app(InventoryItemStockCalculator::class)->averageUnitCost((int) $inventoryItem->id)
                ?: (float) $inventoryItem->default_unit_cost;
            $physicalQty = (float) ($item['physical_qty'] ?? 0);
            $consumedQty = max(0, $systemQty - $physicalQty);

            $records[] = StockOpname::create([
                'outlet_id' => $payload['outlet_id'],
                'durian_variety_id' => null,
                'inventory_item_id' => $inventoryItem->id,
                'date' => $date,
                'product_type' => 'Inventory Item',
                'system_qty_kg' => round(max(0, $systemQty), 3),
                'physical_qty_kg' => round($physicalQty, 3),
                'difference_qty_kg' => round($physicalQty - max(0, $systemQty), 3),
                'generic_unit' => $item['unit'] ?? $inventoryItem->unit,
                'generic_unit_cost' => round($unitCost, 2),
                'generic_consumed_qty' => round($consumedQty, 3),
                'generic_consumed_amount' => round($consumedQty * $unitCost, 2),
                'notes' => 'Input opname WA: ' . ($item['inventory_item_name'] ?? $inventoryItem->name) . ' = ' . ($item['raw_value'] ?? $physicalQty),
            ]);
        }

        return $records;
    }

    private function durianSystemQty(int $outletId, int $varietyId, string $productType, mixed $date): float
    {
        return match ($productType) {
            'Buah Utuh' => $this->wholeFruitStock($outletId, $varietyId, $date),
            'Daging Fresh' => $this->freshStock($outletId, $varietyId, $date),
            'Daging Frozen' => $this->frozenStock($outletId, $varietyId, $date),
            default => 0,
        };
    }

    private function wholeFruitStock(int $outletId, int $varietyId, mixed $date): float
    {
        $shipmentKg = Shipment::where('outlet_id', $outletId)
            ->where('durian_variety_id', $varietyId)
            ->where('shipment_direction', 'warehouse_to_outlet')
            ->where(fn ($query) => $query->where('product_type', 'Buah Utuh')->orWhereNull('product_type'))
            ->when($date, fn ($query) => $query->whereDate('date', '<=', $date))
            ->sum('qty_sent_kg');

        $soldKg = Sale::where('outlet_id', $outletId)
            ->where('durian_variety_id', $varietyId)
            ->when($date, fn ($query) => $query->whereDate('date', '<=', $date))
            ->sum('buah_sold_kg');

        $peeledKg = Production::where('outlet_id', $outletId)
            ->where('durian_variety_id', $varietyId)
            ->when($date, fn ($query) => $query->whereDate('date', '<=', $date))
            ->sum('qty_buah_kg');

        return (float) $shipmentKg - (float) $soldKg - (float) $peeledKg;
    }

    private function freshStock(int $outletId, int $varietyId, mixed $date): float
    {
        $producedKg = Production::where('outlet_id', $outletId)
            ->where('durian_variety_id', $varietyId)
            ->when($date, fn ($query) => $query->whereDate('date', '<=', $date))
            ->sum('qty_kupas_kg');

        $soldKg = Sale::where('outlet_id', $outletId)
            ->where('durian_variety_id', $varietyId)
            ->when($date, fn ($query) => $query->whereDate('date', '<=', $date))
            ->sum('fresh_sold_kg');

        $convertedKg = ProductConversion::where('outlet_id', $outletId)
            ->where('durian_variety_id', $varietyId)
            ->where('conversion_type', 'Kupas Fresh ke Kupas Frozen')
            ->when($date, fn ($query) => $query->whereDate('date', '<=', $date))
            ->sum('from_qty_kg');

        $shipmentInKg = Shipment::where('outlet_id', $outletId)
            ->where('durian_variety_id', $varietyId)
            ->where('shipment_direction', 'warehouse_to_outlet')
            ->where('product_type', 'Daging Fresh')
            ->when($date, fn ($query) => $query->whereDate('date', '<=', $date))
            ->sum('qty_received_kg');

        $shipmentOutKg = Shipment::where('outlet_id', $outletId)
            ->where('durian_variety_id', $varietyId)
            ->where('shipment_direction', 'outlet_to_warehouse')
            ->where('product_type', 'Daging Fresh')
            ->when($date, fn ($query) => $query->whereDate('date', '<=', $date))
            ->sum('qty_sent_kg');

        return (float) $producedKg + (float) $shipmentInKg - (float) $soldKg - (float) $convertedKg - (float) $shipmentOutKg;
    }

    private function frozenStock(int $outletId, int $varietyId, mixed $date): float
    {
        $producedKg = ProductConversion::where('outlet_id', $outletId)
            ->where('durian_variety_id', $varietyId)
            ->where('conversion_type', 'Kupas Fresh ke Kupas Frozen')
            ->when($date, fn ($query) => $query->whereDate('date', '<=', $date))
            ->sum('to_qty_kg');

        $soldKg = Sale::where('outlet_id', $outletId)
            ->where('durian_variety_id', $varietyId)
            ->when($date, fn ($query) => $query->whereDate('date', '<=', $date))
            ->sum('frozen_sold_kg');

        $shipmentInKg = Shipment::where('outlet_id', $outletId)
            ->where('durian_variety_id', $varietyId)
            ->where('shipment_direction', 'warehouse_to_outlet')
            ->where('product_type', 'Daging Frozen')
            ->when($date, fn ($query) => $query->whereDate('date', '<=', $date))
            ->sum('qty_received_kg');

        $shipmentOutKg = Shipment::where('outlet_id', $outletId)
            ->where('durian_variety_id', $varietyId)
            ->where('shipment_direction', 'outlet_to_warehouse')
            ->where('product_type', 'Daging Frozen')
            ->when($date, fn ($query) => $query->whereDate('date', '<=', $date))
            ->sum('qty_sent_kg');

        return (float) $producedKg + (float) $shipmentInKg - (float) $soldKg - (float) $shipmentOutKg;
    }

    private function inventoryItemForOpname(array $item): InventoryItem
    {
        if (! empty($item['inventory_item_id'])) {
            $record = InventoryItem::find($item['inventory_item_id']);

            if ($record) {
                return $record;
            }
        }

        $name = trim((string) ($item['inventory_item_name'] ?? 'Inventory WA'));
        $sku = $this->defaultInventorySku($name);

        return InventoryItem::firstOrCreate(
            ['name' => $name],
            [
                'sku' => $sku,
                'category' => 'packaging',
                'unit' => $item['unit'] ?? 'pcs',
                'default_unit_cost' => 0,
                'is_active' => true,
                'notes' => 'Dibuat otomatis dari laporan stock opname WA.',
            ],
        );
    }

    private function defaultInventorySku(string $name): string
    {
        return match (Str::lower($name)) {
            'thinwall' => 'SUP-THINWALL',
            'stiker batang' => 'SUP-STIKER-BATANG',
            'stiker durpas' => 'SUP-STIKER-DURPAS',
            'sendok tester' => 'SUP-SENDOK-TESTER',
            'tusuk gigi' => 'SUP-TUSUK-GIGI',
            'sarung tangan plastik' => 'SUP-SARUNG-TANGAN-PLASTIK',
            default => 'WA-' . Str::upper(Str::slug($name)),
        };
    }

    private function opnameNotes(array $payload, array $item): string
    {
        $parts = ['Input opname WA'];

        if (! empty($item['physical_qty_butir'])) {
            $parts[] = 'butir: ' . $item['physical_qty_butir'];
        }

        if (! empty($item['physical_qty_pack'])) {
            $parts[] = 'pack: ' . $item['physical_qty_pack'];
        }

        return implode('; ', $parts);
    }

    private function targetLabel(Model $target): string
    {
        return class_basename($target) . ' #' . $target->id;
    }

    /**
     * @param array<int, Model> $targets
     */
    private function targetArrayLabel(array $targets): string
    {
        if (
            count($targets) === 2
            && $targets[0] instanceof ProductReturn
            && $targets[1] instanceof Production
        ) {
            return 'Product Return #' . $targets[0]->id . ' + Production #' . $targets[1]->id;
        }

        return 'Stock Opname ' . count($targets) . ' record';
    }
}
