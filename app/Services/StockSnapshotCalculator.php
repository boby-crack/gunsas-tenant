<?php

namespace App\Services;

use App\Models\DurianVariety;
use App\Models\InventoryItem;
use App\Models\Outlet;
use App\Models\ProductConversion;
use App\Models\ProductReturn;
use App\Models\Production;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Shipment;
use App\Models\StockOpname;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

class StockSnapshotCalculator
{
    private const DURIAN_PRODUCTS = [
        'Buah Utuh' => 'Buah Utuh',
        'Daging Fresh' => 'Kupas Fresh',
        'Daging Frozen' => 'Durpas Frozen',
    ];

    public function calculate(array $filters): array
    {
        [$dateFrom, $dateUntil] = $this->dateRange($filters);
        $outletFilter = $this->outletFilter($filters);
        $outletIds = $this->outletIds($dateUntil, $outletFilter);
        $outlets = Outlet::query()
            ->whereKey($outletIds)
            ->orderBy('group_name')
            ->orderBy('name')
            ->get(['id', 'name', 'group_name'])
            ->keyBy('id');

        $rows = [];

        if (($filters['product_category'] ?? null) !== 'non_durian') {
            $rows = [
                ...$rows,
                ...$this->durianRows($dateFrom, $dateUntil, $outlets, $filters),
            ];
        }

        if (($filters['product_category'] ?? null) !== 'durian') {
            $rows = [
                ...$rows,
                ...$this->inventoryRows($dateFrom, $dateUntil, $outlets, $filters),
            ];
        }

        usort($rows, fn (array $a, array $b): int => [$a['outlet_name'], $a['sort'], $a['product_name']] <=> [$b['outlet_name'], $b['sort'], $b['product_name']]);

        $kgRows = array_filter($rows, fn (array $row): bool => ($row['unit'] ?? 'Kg') === 'Kg');

        return [
            'filters' => [
                'date' => $dateUntil,
                'date_from' => $dateFrom,
                'date_until' => $dateUntil,
                'outlet_group' => $filters['outlet_group'] ?? null,
                'outlet_ids' => $this->selectedOutletIds($filters),
                'outlet_id' => $filters['outlet_id'] ?? null,
                'product_category' => $filters['product_category'] ?? null,
                'product_type' => $filters['product_type'] ?? null,
                'durian_variety_id' => $filters['durian_variety_id'] ?? null,
                'inventory_item_id' => $filters['inventory_item_id'] ?? null,
            ],
            'summary' => [
                'start_qty' => array_sum(array_column($kgRows, 'start_qty')),
                'in_qty' => array_sum(array_column($kgRows, 'in_qty')),
                'sold_qty' => array_sum(array_column($kgRows, 'sold_qty')),
                'other_out_qty' => array_sum(array_column($kgRows, 'other_out_qty')),
                'olahan_reject_qty' => array_sum(array_map(fn (array $row): float => (float) ($row['detail']['olahan_reject'] ?? 0.0), $kgRows)),
                'end_qty' => array_sum(array_column($kgRows, 'end_qty')),
                'variance_qty' => array_sum(array_map(fn (array $row): float => $row['variance_qty'] ?? 0.0, $kgRows)),
            ],
            'rows' => $rows,
        ];
    }

    private function dateRange(array $filters): array
    {
        $dateFrom = Carbon::parse($filters['date_from'] ?? $filters['date'] ?? now())->toDateString();
        $dateUntil = Carbon::parse($filters['date_until'] ?? $filters['date'] ?? $dateFrom)->toDateString();

        if (Carbon::parse($dateUntil)->lt(Carbon::parse($dateFrom))) {
            return [$dateUntil, $dateFrom];
        }

        return [$dateFrom, $dateUntil];
    }

    public function durianStockForOpnameDate(string $date, int $outletId, int $varietyId, string $productType): float
    {
        return $this->anchoredDurianStockUntil($date, $outletId, $varietyId, $productType, includeOpnameOnDate: false);
    }

    public function durianStockForDate(string $date, int $outletId, int $varietyId, string $productType, bool $includeOpnameOnDate = true): float
    {
        return $this->anchoredDurianStockUntil($date, $outletId, $varietyId, $productType, $includeOpnameOnDate);
    }

    public function durianStockForOutletProductDate(string $date, int $outletId, string $productType): float
    {
        return collect($this->durianVarietyIds($date, $outletId))
            ->sum(fn (int $varietyId): float => $this->anchoredDurianStockUntil($date, $outletId, $varietyId, $productType, includeOpnameOnDate: true));
    }

    public function inventoryStockForOpnameDate(string $date, int $outletId, int $itemId): float
    {
        return $this->anchoredInventoryStockUntil($date, $outletId, $itemId, includeOpnameOnDate: false);
    }

    private function durianRows(string $dateFrom, string $dateUntil, iterable $outlets, array $filters = []): array
    {
        $varieties = DurianVariety::query()->orderBy('name')->get(['id', 'name'])->keyBy('id');
        $fromExclusive = Carbon::parse($dateFrom)->subDay()->toDateString();
        $productTypes = $this->durianProductTypes($filters);
        $rows = [];

        foreach ($outlets as $outlet) {
            $varietyIds = filled($filters['durian_variety_id'] ?? null)
                ? [(int) $filters['durian_variety_id']]
                : $this->durianVarietyIds($dateUntil, (int) $outlet->id);

            foreach ($varietyIds as $varietyId) {
                foreach ($productTypes as $productType => $label) {
                    $startQty = $this->durianStockBefore($dateFrom, (int) $outlet->id, (int) $varietyId, $productType);
                    $movement = $this->durianMovementForPeriod($fromExclusive, $dateUntil, (int) $outlet->id, (int) $varietyId, $productType);
                    $endQty = $startQty + $movement['in_qty'] - $movement['sold_qty'] - $movement['other_out_qty'];
                    $physicalQty = $this->durianPhysicalOnDate($dateUntil, (int) $outlet->id, (int) $varietyId, $productType);

                    if (! $this->shouldShowRow($startQty, $movement, $endQty, $physicalQty)) {
                        continue;
                    }

                    $rows[] = [
                        'sort' => match ($productType) {
                            'Buah Utuh' => 10,
                            'Daging Fresh' => 20,
                            default => 30,
                        },
                        'outlet_id' => (int) $outlet->id,
                        'outlet_name' => $outlet->name,
                        'group_name' => $this->groupLabel($outlet->group_name),
                        'category' => 'Produk Durian',
                        'product_name' => $label . ' ' . ($varieties[$varietyId]->name ?? ''),
                        'product_type' => $productType,
                        'durian_variety_id' => (int) $varietyId,
                        'durian_variety_name' => $varieties[$varietyId]->name ?? '',
                        'unit' => 'Kg',
                        'start_qty' => $startQty,
                        'in_qty' => $movement['in_qty'],
                        'sold_qty' => $movement['sold_qty'],
                        'other_out_qty' => $movement['other_out_qty'],
                        'end_qty' => $endQty,
                        'physical_qty' => $physicalQty,
                        'variance_qty' => $physicalQty === null ? null : $physicalQty - $endQty,
                        'detail' => $movement['detail'],
                    ];
                }
            }
        }

        return $rows;
    }

    private function durianProductTypes(array $filters): array
    {
        $productType = $filters['product_type'] ?? null;

        if ($productType && array_key_exists($productType, self::DURIAN_PRODUCTS)) {
            return [$productType => self::DURIAN_PRODUCTS[$productType]];
        }

        return self::DURIAN_PRODUCTS;
    }

    private function inventoryRows(string $dateFrom, string $dateUntil, iterable $outlets, array $filters = []): array
    {
        $fromExclusive = Carbon::parse($dateFrom)->subDay()->toDateString();
        $rows = [];

        foreach ($outlets as $outlet) {
            $itemIds = filled($filters['inventory_item_id'] ?? null)
                ? [(int) $filters['inventory_item_id']]
                : $this->inventoryItemIds($dateUntil, (int) $outlet->id);
            $items = InventoryItem::query()->whereKey($itemIds)->orderBy('name')->get(['id', 'name', 'unit'])->keyBy('id');

            foreach ($itemIds as $itemId) {
                $item = $items[$itemId] ?? null;

                if (! $item) {
                    continue;
                }

                $startQty = $this->inventoryStockBefore($dateFrom, (int) $outlet->id, (int) $itemId);
                $movement = $this->inventoryMovementForPeriod($fromExclusive, $dateUntil, (int) $outlet->id, (int) $itemId);
                $endQty = $startQty + $movement['in_qty'] - $movement['sold_qty'] - $movement['other_out_qty'];
                $physicalQty = $this->inventoryPhysicalOnDate($dateUntil, (int) $outlet->id, (int) $itemId);

                if (! $this->shouldShowRow($startQty, $movement, $endQty, $physicalQty)) {
                    continue;
                }

                $rows[] = [
                    'sort' => 40,
                    'outlet_id' => (int) $outlet->id,
                    'outlet_name' => $outlet->name,
                    'group_name' => $this->groupLabel($outlet->group_name),
                    'category' => 'Produk Non-durian',
                    'product_name' => $item->name,
                    'product_type' => 'Inventory Item',
                    'inventory_item_id' => (int) $itemId,
                    'unit' => $item->unit ?: 'pcs',
                    'start_qty' => $startQty,
                    'in_qty' => $movement['in_qty'],
                    'sold_qty' => $movement['sold_qty'],
                    'other_out_qty' => $movement['other_out_qty'],
                    'end_qty' => $endQty,
                    'physical_qty' => $physicalQty,
                    'variance_qty' => $physicalQty === null ? null : $physicalQty - $endQty,
                    'detail' => $movement['detail'],
                ];
            }
        }

        return $rows;
    }

    private function durianStockBefore(string $date, int $outletId, int $varietyId, string $productType): float
    {
        $beforeDate = Carbon::parse($date)->subDay()->toDateString();

        return $this->anchoredDurianStockUntil($beforeDate, $outletId, $varietyId, $productType, includeOpnameOnDate: true);
    }

    private function anchoredDurianStockUntil(string $date, int $outletId, int $varietyId, string $productType, bool $includeOpnameOnDate): float
    {
        $anchor = $this->latestDurianOpname($date, $outletId, $varietyId, $productType, $includeOpnameOnDate);

        if ($anchor) {
            return (float) $anchor->physical_qty_kg
                + $this->durianMovementBetween(
                    Carbon::parse($anchor->date)->toDateString(),
                    $date,
                    $outletId,
                    $varietyId,
                    $productType,
                );
        }

        return $this->absoluteDurianStockUntil($date, $outletId, $varietyId, $productType);
    }

    private function absoluteDurianStockUntil(string $date, int $outletId, int $varietyId, string $productType): float
    {
        return match ($productType) {
            'Buah Utuh' => $this->shipmentKgUntil($date, $outletId, $varietyId, $productType, 'warehouse_to_outlet')
                - $this->soldKgUntil($date, $outletId, $varietyId, $productType)
                - $this->returnKgUntil($date, $outletId, $varietyId)
                - $this->productionKgUntil($date, $outletId, $varietyId, 'qty_buah_kg', normalOnly: true),
            'Daging Fresh' => $this->productionKgUntil($date, $outletId, $varietyId, 'qty_kupas_kg')
                + $this->shipmentKgUntil($date, $outletId, $varietyId, $productType, 'warehouse_to_outlet')
                - $this->soldKgUntil($date, $outletId, $varietyId, $productType)
                - $this->conversionKgUntil($date, $outletId, $varietyId, 'from_qty_kg')
                - $this->shipmentKgUntil($date, $outletId, $varietyId, $productType, 'outlet_to_warehouse'),
            default => $this->conversionKgUntil($date, $outletId, $varietyId, 'to_qty_kg')
                + $this->shipmentKgUntil($date, $outletId, $varietyId, $productType, 'warehouse_to_outlet')
                - $this->soldKgUntil($date, $outletId, $varietyId, $productType)
                - $this->shipmentKgUntil($date, $outletId, $varietyId, $productType, 'outlet_to_warehouse'),
        };
    }

    private function durianMovementBetween(string $fromExclusive, string $untilInclusive, int $outletId, int $varietyId, string $productType): float
    {
        if (Carbon::parse($untilInclusive)->lte(Carbon::parse($fromExclusive))) {
            return 0.0;
        }

        return match ($productType) {
            'Buah Utuh' => $this->shipmentKgBetween($fromExclusive, $untilInclusive, $outletId, $varietyId, $productType, 'warehouse_to_outlet')
                - $this->soldKgBetween($fromExclusive, $untilInclusive, $outletId, $varietyId, $productType)
                - $this->returnKgBetween($fromExclusive, $untilInclusive, $outletId, $varietyId)
                - $this->productionKgBetween($fromExclusive, $untilInclusive, $outletId, $varietyId, 'qty_buah_kg', normalOnly: true),
            'Daging Fresh' => $this->productionKgBetween($fromExclusive, $untilInclusive, $outletId, $varietyId, 'qty_kupas_kg')
                + $this->shipmentKgBetween($fromExclusive, $untilInclusive, $outletId, $varietyId, $productType, 'warehouse_to_outlet')
                - $this->soldKgBetween($fromExclusive, $untilInclusive, $outletId, $varietyId, $productType)
                - $this->conversionKgBetween($fromExclusive, $untilInclusive, $outletId, $varietyId, 'from_qty_kg')
                - $this->shipmentKgBetween($fromExclusive, $untilInclusive, $outletId, $varietyId, $productType, 'outlet_to_warehouse'),
            default => $this->conversionKgBetween($fromExclusive, $untilInclusive, $outletId, $varietyId, 'to_qty_kg')
                + $this->shipmentKgBetween($fromExclusive, $untilInclusive, $outletId, $varietyId, $productType, 'warehouse_to_outlet')
                - $this->soldKgBetween($fromExclusive, $untilInclusive, $outletId, $varietyId, $productType)
                - $this->shipmentKgBetween($fromExclusive, $untilInclusive, $outletId, $varietyId, $productType, 'outlet_to_warehouse'),
        };
    }

    private function latestDurianOpname(string $date, int $outletId, int $varietyId, string $productType, bool $includeOpnameOnDate): ?StockOpname
    {
        return StockOpname::query()
            ->where('outlet_id', $outletId)
            ->where('durian_variety_id', $varietyId)
            ->where('product_type', $productType)
            ->when(
                $includeOpnameOnDate,
                fn (Builder $query) => $query->whereDate('date', '<=', $date),
                fn (Builder $query) => $query->whereDate('date', '<', $date),
            )
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->first(['date', 'physical_qty_kg']);
    }

    private function durianMovementForPeriod(string $fromExclusive, string $untilInclusive, int $outletId, int $varietyId, string $productType): array
    {
        $shipmentIn = $this->shipmentKgBetween($fromExclusive, $untilInclusive, $outletId, $varietyId, $productType, 'warehouse_to_outlet');
        $shipmentOut = $this->shipmentKgBetween($fromExclusive, $untilInclusive, $outletId, $varietyId, $productType, 'outlet_to_warehouse');
        $sold = $this->soldKgBetween($fromExclusive, $untilInclusive, $outletId, $varietyId, $productType);
        $return = $productType === 'Buah Utuh' ? $this->returnKgBetween($fromExclusive, $untilInclusive, $outletId, $varietyId) : 0.0;
        $productionIn = $productType === 'Daging Fresh' ? $this->productionKgBetween($fromExclusive, $untilInclusive, $outletId, $varietyId, 'qty_kupas_kg') : 0.0;
        $productionOut = $productType === 'Buah Utuh' ? $this->productionKgBetween($fromExclusive, $untilInclusive, $outletId, $varietyId, 'qty_buah_kg', normalOnly: true) : 0.0;
        $olahanReject = $productType === 'Daging Fresh' ? $this->productionKgBetween($fromExclusive, $untilInclusive, $outletId, $varietyId, 'qty_olahan_kg') : 0.0;
        $conversionIn = $productType === 'Daging Frozen' ? $this->conversionKgBetween($fromExclusive, $untilInclusive, $outletId, $varietyId, 'to_qty_kg') : 0.0;
        $conversionOut = $productType === 'Daging Fresh' ? $this->conversionKgBetween($fromExclusive, $untilInclusive, $outletId, $varietyId, 'from_qty_kg') : 0.0;

        return [
            'in_qty' => $shipmentIn + $productionIn + $conversionIn,
            'sold_qty' => $sold,
            'other_out_qty' => $shipmentOut + $return + $productionOut + $conversionOut,
            'detail' => [
                'shipment_in' => $shipmentIn,
                'shipment_out' => $shipmentOut,
                'production_in' => $productionIn,
                'production_out' => $productionOut,
                'olahan_reject' => $olahanReject,
                'conversion_in' => $conversionIn,
                'conversion_out' => $conversionOut,
                'return' => $return,
            ],
        ];
    }

    private function durianMovementOnDate(string $date, int $outletId, int $varietyId, string $productType): array
    {
        $shipmentIn = $this->shipmentKgOnDate($date, $outletId, $varietyId, $productType, 'warehouse_to_outlet');
        $shipmentOut = $this->shipmentKgOnDate($date, $outletId, $varietyId, $productType, 'outlet_to_warehouse');
        $sold = $this->soldKgOnDate($date, $outletId, $varietyId, $productType);
        $return = $productType === 'Buah Utuh' ? $this->returnKgOnDate($date, $outletId, $varietyId) : 0.0;
        $productionIn = $productType === 'Daging Fresh' ? $this->productionKgOnDate($date, $outletId, $varietyId, 'qty_kupas_kg') : 0.0;
        $productionOut = $productType === 'Buah Utuh' ? $this->productionKgOnDate($date, $outletId, $varietyId, 'qty_buah_kg', normalOnly: true) : 0.0;
        $olahanReject = $productType === 'Daging Fresh' ? $this->productionKgOnDate($date, $outletId, $varietyId, 'qty_olahan_kg') : 0.0;
        $conversionIn = $productType === 'Daging Frozen' ? $this->conversionKgOnDate($date, $outletId, $varietyId, 'to_qty_kg') : 0.0;
        $conversionOut = $productType === 'Daging Fresh' ? $this->conversionKgOnDate($date, $outletId, $varietyId, 'from_qty_kg') : 0.0;

        return [
            'in_qty' => $shipmentIn + $productionIn + $conversionIn,
            'sold_qty' => $sold,
            'other_out_qty' => $shipmentOut + $return + $productionOut + $conversionOut,
            'detail' => [
                'shipment_in' => $shipmentIn,
                'shipment_out' => $shipmentOut,
                'production_in' => $productionIn,
                'production_out' => $productionOut,
                'olahan_reject' => $olahanReject,
                'conversion_in' => $conversionIn,
                'conversion_out' => $conversionOut,
                'return' => $return,
            ],
        ];
    }

    private function shipmentKgUntil(string $date, int $outletId, int $varietyId, string $productType, string $direction): float
    {
        return (float) $this->shipmentQuery($outletId, $varietyId, $productType, $direction)
            ->whereDate('date', '<=', $date)
            ->selectRaw('COALESCE(SUM(CASE WHEN COALESCE(qty_received_kg, 0) > 0 THEN qty_received_kg ELSE qty_sent_kg END), 0) as total')
            ->value('total');
    }

    private function shipmentKgOnDate(string $date, int $outletId, int $varietyId, string $productType, string $direction): float
    {
        return (float) $this->shipmentQuery($outletId, $varietyId, $productType, $direction)
            ->whereDate('date', $date)
            ->selectRaw('COALESCE(SUM(CASE WHEN COALESCE(qty_received_kg, 0) > 0 THEN qty_received_kg ELSE qty_sent_kg END), 0) as total')
            ->value('total');
    }

    private function shipmentKgBetween(string $fromExclusive, string $untilInclusive, int $outletId, int $varietyId, string $productType, string $direction): float
    {
        return (float) $this->shipmentQuery($outletId, $varietyId, $productType, $direction)
            ->whereDate('date', '>', $fromExclusive)
            ->whereDate('date', '<=', $untilInclusive)
            ->selectRaw('COALESCE(SUM(CASE WHEN COALESCE(qty_received_kg, 0) > 0 THEN qty_received_kg ELSE qty_sent_kg END), 0) as total')
            ->value('total');
    }

    private function shipmentQuery(int $outletId, int $varietyId, string $productType, string $direction): Builder
    {
        return Shipment::query()
            ->where('outlet_id', $outletId)
            ->where('durian_variety_id', $varietyId)
            ->where('shipment_direction', $direction)
            ->when(
                $productType === 'Buah Utuh',
                fn (Builder $query) => $query->where(fn (Builder $query) => $query->where('product_type', 'Buah Utuh')->orWhereNull('product_type')),
                fn (Builder $query) => $query->where('product_type', $productType)
            );
    }

    private function soldKgUntil(string $date, int $outletId, int $varietyId, string $productType): float
    {
        return (float) $this->saleQuery($outletId, $varietyId, $productType)->whereDate('date', '<=', $date)->sum($this->saleColumn($productType));
    }

    private function soldKgOnDate(string $date, int $outletId, int $varietyId, string $productType): float
    {
        return (float) $this->saleQuery($outletId, $varietyId, $productType)->whereDate('date', $date)->sum($this->saleColumn($productType));
    }

    private function soldKgBetween(string $fromExclusive, string $untilInclusive, int $outletId, int $varietyId, string $productType): float
    {
        return (float) $this->saleQuery($outletId, $varietyId, $productType)
            ->whereDate('date', '>', $fromExclusive)
            ->whereDate('date', '<=', $untilInclusive)
            ->sum($this->saleColumn($productType));
    }

    private function saleQuery(int $outletId, int $varietyId, string $productType): Builder
    {
        return Sale::query()->where('outlet_id', $outletId)->where('durian_variety_id', $varietyId);
    }

    private function saleColumn(string $productType): string
    {
        return match ($productType) {
            'Daging Fresh' => 'fresh_sold_kg',
            'Daging Frozen' => 'frozen_sold_kg',
            default => 'buah_sold_kg',
        };
    }

    private function productionKgUntil(string $date, int $outletId, int $varietyId, string $column, bool $normalOnly = false): float
    {
        return (float) Production::query()
            ->where('outlet_id', $outletId)
            ->where('durian_variety_id', $varietyId)
            ->when($normalOnly, fn (Builder $query) => $query->where(fn (Builder $query) => $query->whereNull('source_type')->orWhere('source_type', Production::SOURCE_NORMAL)))
            ->whereDate('date', '<=', $date)
            ->sum($column);
    }

    private function productionKgOnDate(string $date, int $outletId, int $varietyId, string $column, bool $normalOnly = false): float
    {
        return (float) Production::query()
            ->where('outlet_id', $outletId)
            ->where('durian_variety_id', $varietyId)
            ->when($normalOnly, fn (Builder $query) => $query->where(fn (Builder $query) => $query->whereNull('source_type')->orWhere('source_type', Production::SOURCE_NORMAL)))
            ->whereDate('date', $date)
            ->sum($column);
    }

    private function productionKgBetween(string $fromExclusive, string $untilInclusive, int $outletId, int $varietyId, string $column, bool $normalOnly = false): float
    {
        return (float) Production::query()
            ->where('outlet_id', $outletId)
            ->where('durian_variety_id', $varietyId)
            ->when($normalOnly, fn (Builder $query) => $query->where(fn (Builder $query) => $query->whereNull('source_type')->orWhere('source_type', Production::SOURCE_NORMAL)))
            ->whereDate('date', '>', $fromExclusive)
            ->whereDate('date', '<=', $untilInclusive)
            ->sum($column);
    }

    private function conversionKgUntil(string $date, int $outletId, int $varietyId, string $column): float
    {
        return (float) $this->conversionQuery($outletId, $varietyId, $column)
            ->whereDate('date', '<=', $date)
            ->sum($column);
    }

    private function conversionKgOnDate(string $date, int $outletId, int $varietyId, string $column): float
    {
        return (float) $this->conversionQuery($outletId, $varietyId, $column)
            ->whereDate('date', $date)
            ->sum($column);
    }

    private function conversionKgBetween(string $fromExclusive, string $untilInclusive, int $outletId, int $varietyId, string $column): float
    {
        return (float) $this->conversionQuery($outletId, $varietyId, $column)
            ->whereDate('date', '>', $fromExclusive)
            ->whereDate('date', '<=', $untilInclusive)
            ->sum($column);
    }

    private function conversionQuery(int $outletId, int $varietyId, string $column): Builder
    {
        return ProductConversion::query()
            ->where('outlet_id', $outletId)
            ->where('durian_variety_id', $varietyId)
            ->when(
                $column === 'to_qty_kg',
                fn (Builder $query) => $query->where('conversion_type', ProductConversion::TYPE_FRESH_TO_FROZEN)
            );
    }

    private function returnKgUntil(string $date, int $outletId, int $varietyId): float
    {
        return (float) ProductReturn::query()->where('outlet_id', $outletId)->where('durian_variety_id', $varietyId)->whereDate('date', '<=', $date)->sum('qty_kg');
    }

    private function returnKgOnDate(string $date, int $outletId, int $varietyId): float
    {
        return (float) ProductReturn::query()->where('outlet_id', $outletId)->where('durian_variety_id', $varietyId)->whereDate('date', $date)->sum('qty_kg');
    }

    private function returnKgBetween(string $fromExclusive, string $untilInclusive, int $outletId, int $varietyId): float
    {
        return (float) ProductReturn::query()
            ->where('outlet_id', $outletId)
            ->where('durian_variety_id', $varietyId)
            ->whereDate('date', '>', $fromExclusive)
            ->whereDate('date', '<=', $untilInclusive)
            ->sum('qty_kg');
    }

    private function inventoryStockBefore(string $date, int $outletId, int $itemId): float
    {
        $beforeDate = Carbon::parse($date)->subDay()->toDateString();

        return $this->anchoredInventoryStockUntil($beforeDate, $outletId, $itemId, includeOpnameOnDate: true);
    }

    private function anchoredInventoryStockUntil(string $date, int $outletId, int $itemId, bool $includeOpnameOnDate): float
    {
        $anchor = $this->latestInventoryOpname($date, $outletId, $itemId, $includeOpnameOnDate);

        if ($anchor) {
            return (float) $anchor->physical_qty_kg
                + $this->inventoryMovementBetween(
                    Carbon::parse($anchor->date)->toDateString(),
                    $date,
                    $outletId,
                    $itemId,
                    $includeOpnameOnDate,
                );
        }

        return $this->absoluteInventoryStockUntil($date, $outletId, $itemId, $includeOpnameOnDate);
    }

    private function absoluteInventoryStockUntil(string $date, int $outletId, int $itemId, bool $includeOpnameOnDate = true): float
    {
        return $this->inventoryReceivedUntil($date, $outletId, $itemId)
            - $this->inventorySoldUntil($date, $outletId, $itemId)
            - $this->inventoryReturnedUntil($date, $outletId, $itemId)
            - $this->inventoryConsumedUntil($date, $outletId, $itemId, $includeOpnameOnDate);
    }

    private function inventoryMovementBetween(string $fromExclusive, string $untilInclusive, int $outletId, int $itemId, bool $includeOpnameOnEndDate = true): float
    {
        if (Carbon::parse($untilInclusive)->lte(Carbon::parse($fromExclusive))) {
            return 0.0;
        }

        return $this->inventoryReceivedBetween($fromExclusive, $untilInclusive, $outletId, $itemId)
            - $this->inventorySoldBetween($fromExclusive, $untilInclusive, $outletId, $itemId)
            - $this->inventoryReturnedBetween($fromExclusive, $untilInclusive, $outletId, $itemId)
            - $this->inventoryConsumedBetween($fromExclusive, $untilInclusive, $outletId, $itemId, $includeOpnameOnEndDate);
    }

    private function latestInventoryOpname(string $date, int $outletId, int $itemId, bool $includeOpnameOnDate): ?StockOpname
    {
        return StockOpname::query()
            ->where('outlet_id', $outletId)
            ->where('inventory_item_id', $itemId)
            ->when(
                $includeOpnameOnDate,
                fn (Builder $query) => $query->whereDate('date', '<=', $date),
                fn (Builder $query) => $query->whereDate('date', '<', $date),
            )
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->first(['date', 'physical_qty_kg']);
    }

    private function inventoryMovementForPeriod(string $fromExclusive, string $untilInclusive, int $outletId, int $itemId): array
    {
        $in = $this->inventoryReceivedBetween($fromExclusive, $untilInclusive, $outletId, $itemId);
        $sold = $this->inventorySoldBetween($fromExclusive, $untilInclusive, $outletId, $itemId);
        $returned = $this->inventoryReturnedBetween($fromExclusive, $untilInclusive, $outletId, $itemId);
        $consumed = $this->inventoryConsumedBetween($fromExclusive, $untilInclusive, $outletId, $itemId);

        return [
            'in_qty' => $in,
            'sold_qty' => $sold,
            'other_out_qty' => $returned + $consumed,
            'detail' => [
                'shipment_in' => $in,
                'sold' => $sold,
                'shipment_out' => $returned,
                'consumed' => $consumed,
            ],
        ];
    }

    private function inventoryMovementOnDate(string $date, int $outletId, int $itemId): array
    {
        $in = $this->inventoryReceivedOnDate($date, $outletId, $itemId);
        $sold = $this->inventorySoldOnDate($date, $outletId, $itemId);
        $returned = $this->inventoryReturnedOnDate($date, $outletId, $itemId);
        $consumed = $this->inventoryConsumedOnDate($date, $outletId, $itemId);

        return [
            'in_qty' => $in,
            'sold_qty' => $sold,
            'other_out_qty' => $returned + $consumed,
            'detail' => [
                'shipment_in' => $in,
                'sold' => $sold,
                'shipment_out' => $returned,
                'consumed' => $consumed,
            ],
        ];
    }

    private function inventorySoldUntil(string $date, int $outletId, int $itemId): float
    {
        return (float) $this->inventorySaleQuery($outletId, $itemId)
            ->whereDate('sales.date', '<=', $date)
            ->sum('sale_items.quantity');
    }

    private function inventorySoldOnDate(string $date, int $outletId, int $itemId): float
    {
        return (float) $this->inventorySaleQuery($outletId, $itemId)
            ->whereDate('sales.date', $date)
            ->sum('sale_items.quantity');
    }

    private function inventorySoldBetween(string $fromExclusive, string $untilInclusive, int $outletId, int $itemId): float
    {
        return (float) $this->inventorySaleQuery($outletId, $itemId)
            ->whereDate('sales.date', '>', $fromExclusive)
            ->whereDate('sales.date', '<=', $untilInclusive)
            ->sum('sale_items.quantity');
    }

    private function inventorySaleQuery(int $outletId, int $itemId): Builder
    {
        return SaleItem::query()
            ->join('sales', 'sale_items.sale_id', '=', 'sales.id')
            ->where('sales.outlet_id', $outletId)
            ->where('sale_items.inventory_item_id', $itemId);
    }

    private function inventoryReceivedBefore(string $date, int $outletId, int $itemId): float
    {
        return (float) Shipment::query()
            ->where('shipment_mode', 'inventory')
            ->where('inventory_item_id', $itemId)
            ->where('outlet_id', $outletId)
            ->where('shipment_direction', 'warehouse_to_outlet')
            ->whereDate('date', '<', $date)
            ->selectRaw('COALESCE(SUM(CASE WHEN COALESCE(generic_qty_received, 0) > 0 THEN generic_qty_received ELSE generic_qty_sent END), 0) as total')
            ->value('total');
    }

    private function inventoryReceivedUntil(string $date, int $outletId, int $itemId): float
    {
        return (float) Shipment::query()
            ->where('shipment_mode', 'inventory')
            ->where('inventory_item_id', $itemId)
            ->where('outlet_id', $outletId)
            ->where('shipment_direction', 'warehouse_to_outlet')
            ->whereDate('date', '<=', $date)
            ->selectRaw('COALESCE(SUM(CASE WHEN COALESCE(generic_qty_received, 0) > 0 THEN generic_qty_received ELSE generic_qty_sent END), 0) as total')
            ->value('total');
    }

    private function inventoryReceivedOnDate(string $date, int $outletId, int $itemId): float
    {
        return (float) Shipment::query()
            ->where('shipment_mode', 'inventory')
            ->where('inventory_item_id', $itemId)
            ->where('outlet_id', $outletId)
            ->where('shipment_direction', 'warehouse_to_outlet')
            ->whereDate('date', $date)
            ->selectRaw('COALESCE(SUM(CASE WHEN COALESCE(generic_qty_received, 0) > 0 THEN generic_qty_received ELSE generic_qty_sent END), 0) as total')
            ->value('total');
    }

    private function inventoryReceivedBetween(string $fromExclusive, string $untilInclusive, int $outletId, int $itemId): float
    {
        return (float) Shipment::query()
            ->where('shipment_mode', 'inventory')
            ->where('inventory_item_id', $itemId)
            ->where('outlet_id', $outletId)
            ->where('shipment_direction', 'warehouse_to_outlet')
            ->whereDate('date', '>', $fromExclusive)
            ->whereDate('date', '<=', $untilInclusive)
            ->selectRaw('COALESCE(SUM(CASE WHEN COALESCE(generic_qty_received, 0) > 0 THEN generic_qty_received ELSE generic_qty_sent END), 0) as total')
            ->value('total');
    }

    private function inventoryReturnedBefore(string $date, int $outletId, int $itemId): float
    {
        return (float) Shipment::query()
            ->where('shipment_mode', 'inventory')
            ->where('inventory_item_id', $itemId)
            ->where('outlet_id', $outletId)
            ->where('shipment_direction', 'outlet_to_warehouse')
            ->whereDate('date', '<', $date)
            ->selectRaw('COALESCE(SUM(CASE WHEN COALESCE(generic_qty_sent, 0) > 0 THEN generic_qty_sent ELSE generic_qty_received END), 0) as total')
            ->value('total');
    }

    private function inventoryReturnedUntil(string $date, int $outletId, int $itemId): float
    {
        return (float) Shipment::query()
            ->where('shipment_mode', 'inventory')
            ->where('inventory_item_id', $itemId)
            ->where('outlet_id', $outletId)
            ->where('shipment_direction', 'outlet_to_warehouse')
            ->whereDate('date', '<=', $date)
            ->selectRaw('COALESCE(SUM(CASE WHEN COALESCE(generic_qty_sent, 0) > 0 THEN generic_qty_sent ELSE generic_qty_received END), 0) as total')
            ->value('total');
    }

    private function inventoryReturnedOnDate(string $date, int $outletId, int $itemId): float
    {
        return (float) Shipment::query()
            ->where('shipment_mode', 'inventory')
            ->where('inventory_item_id', $itemId)
            ->where('outlet_id', $outletId)
            ->where('shipment_direction', 'outlet_to_warehouse')
            ->whereDate('date', $date)
            ->selectRaw('COALESCE(SUM(CASE WHEN COALESCE(generic_qty_sent, 0) > 0 THEN generic_qty_sent ELSE generic_qty_received END), 0) as total')
            ->value('total');
    }

    private function inventoryReturnedBetween(string $fromExclusive, string $untilInclusive, int $outletId, int $itemId): float
    {
        return (float) Shipment::query()
            ->where('shipment_mode', 'inventory')
            ->where('inventory_item_id', $itemId)
            ->where('outlet_id', $outletId)
            ->where('shipment_direction', 'outlet_to_warehouse')
            ->whereDate('date', '>', $fromExclusive)
            ->whereDate('date', '<=', $untilInclusive)
            ->selectRaw('COALESCE(SUM(CASE WHEN COALESCE(generic_qty_sent, 0) > 0 THEN generic_qty_sent ELSE generic_qty_received END), 0) as total')
            ->value('total');
    }

    private function inventoryConsumedBefore(string $date, int $outletId, int $itemId): float
    {
        return (float) StockOpname::query()
            ->where('inventory_item_id', $itemId)
            ->where('outlet_id', $outletId)
            ->whereDate('date', '<', $date)
            ->sum('generic_consumed_qty');
    }

    private function inventoryConsumedUntil(string $date, int $outletId, int $itemId, bool $includeDate = true): float
    {
        return (float) StockOpname::query()
            ->where('inventory_item_id', $itemId)
            ->where('outlet_id', $outletId)
            ->whereDate('date', $includeDate ? '<=' : '<', $date)
            ->sum('generic_consumed_qty');
    }

    private function inventoryConsumedOnDate(string $date, int $outletId, int $itemId): float
    {
        return (float) StockOpname::query()
            ->where('inventory_item_id', $itemId)
            ->where('outlet_id', $outletId)
            ->whereDate('date', $date)
            ->sum('generic_consumed_qty');
    }

    private function inventoryConsumedBetween(string $fromExclusive, string $untilInclusive, int $outletId, int $itemId, bool $includeEndDate = true): float
    {
        return (float) StockOpname::query()
            ->where('inventory_item_id', $itemId)
            ->where('outlet_id', $outletId)
            ->whereDate('date', '>', $fromExclusive)
            ->whereDate('date', $includeEndDate ? '<=' : '<', $untilInclusive)
            ->sum('generic_consumed_qty');
    }

    private function durianPhysicalOnDate(string $date, int $outletId, int $varietyId, string $productType): ?float
    {
        $record = StockOpname::query()
            ->where('outlet_id', $outletId)
            ->where('durian_variety_id', $varietyId)
            ->where('product_type', $productType)
            ->whereDate('date', $date)
            ->latest('id')
            ->first(['physical_qty_kg']);

        return $record ? (float) $record->physical_qty_kg : null;
    }

    private function inventoryPhysicalOnDate(string $date, int $outletId, int $itemId): ?float
    {
        $record = StockOpname::query()
            ->where('outlet_id', $outletId)
            ->where('inventory_item_id', $itemId)
            ->whereDate('date', $date)
            ->latest('id')
            ->first(['physical_qty_kg']);

        return $record ? (float) $record->physical_qty_kg : null;
    }

    private function durianVarietyIds(string $date, int $outletId): array
    {
        return collect([
            ...Shipment::query()->where('outlet_id', $outletId)->whereNotNull('durian_variety_id')->whereDate('date', '<=', $date)->pluck('durian_variety_id'),
            ...Sale::query()->where('outlet_id', $outletId)->whereNotNull('durian_variety_id')->whereDate('date', '<=', $date)->pluck('durian_variety_id'),
            ...Production::query()->where('outlet_id', $outletId)->whereNotNull('durian_variety_id')->whereDate('date', '<=', $date)->pluck('durian_variety_id'),
            ...ProductConversion::query()->where('outlet_id', $outletId)->whereNotNull('durian_variety_id')->whereDate('date', '<=', $date)->pluck('durian_variety_id'),
            ...ProductReturn::query()->where('outlet_id', $outletId)->whereNotNull('durian_variety_id')->whereDate('date', '<=', $date)->pluck('durian_variety_id'),
            ...StockOpname::query()->where('outlet_id', $outletId)->whereNotNull('durian_variety_id')->whereDate('date', '<=', $date)->pluck('durian_variety_id'),
        ])->filter()->map(fn ($id) => (int) $id)->unique()->values()->all();
    }

    private function inventoryItemIds(string $date, int $outletId): array
    {
        return collect([
            ...Shipment::query()->where('outlet_id', $outletId)->whereNotNull('inventory_item_id')->whereDate('date', '<=', $date)->pluck('inventory_item_id'),
            ...StockOpname::query()->where('outlet_id', $outletId)->whereNotNull('inventory_item_id')->whereDate('date', '<=', $date)->pluck('inventory_item_id'),
        ])->filter()->map(fn ($id) => (int) $id)->unique()->values()->all();
    }

    private function outletIds(string $date, mixed $outletFilter): array
    {
        if (is_array($outletFilter)) {
            return collect($outletFilter)->map(fn ($id) => (int) $id)->values()->all();
        }

        if ($outletFilter) {
            return [(int) $outletFilter];
        }

        return collect([
            ...Shipment::query()->whereDate('date', '<=', $date)->pluck('outlet_id'),
            ...Sale::query()->whereDate('date', '<=', $date)->pluck('outlet_id'),
            ...Production::query()->whereDate('date', '<=', $date)->pluck('outlet_id'),
            ...ProductConversion::query()->whereDate('date', '<=', $date)->pluck('outlet_id'),
            ...ProductReturn::query()->whereDate('date', '<=', $date)->pluck('outlet_id'),
            ...StockOpname::query()->whereDate('date', '<=', $date)->pluck('outlet_id'),
        ])->filter()->map(fn ($id) => (int) $id)->unique()->values()->all();
    }

    private function outletFilter(array $filters): mixed
    {
        $selectedOutletIds = $this->selectedOutletIds($filters);

        if ($selectedOutletIds !== []) {
            return $selectedOutletIds;
        }

        if (filled($filters['outlet_id'] ?? null)) {
            return (int) $filters['outlet_id'];
        }

        if (filled($filters['outlet_group'] ?? null)) {
            $group = Outlet::normalizeGroupName($filters['outlet_group']);

            return Outlet::query()->where('group_name', $group)->pluck('id')->map(fn ($id) => (int) $id)->all();
        }

        return null;
    }

    private function selectedOutletIds(array $filters): array
    {
        $outletIds = $filters['outlet_ids'] ?? [];

        if (! is_array($outletIds)) {
            $outletIds = filled($outletIds) ? [$outletIds] : [];
        }

        if (filled($filters['outlet_id'] ?? null)) {
            $outletIds[] = $filters['outlet_id'];
        }

        return collect($outletIds)
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function shouldShowRow(float $startQty, array $movement, float $endQty, ?float $physicalQty): bool
    {
        return abs($startQty) > 0.0005
            || abs($movement['in_qty']) > 0.0005
            || abs($movement['sold_qty']) > 0.0005
            || abs($movement['other_out_qty']) > 0.0005
            || abs($endQty) > 0.0005
            || $physicalQty !== null;
    }

    private function groupLabel(?string $group): string
    {
        return $group ? (Outlet::GROUPS[$group] ?? $group) : '-';
    }
}
