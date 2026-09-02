<?php

namespace App\Services;

use App\Models\DurianVariety;
use App\Models\Expense;
use App\Models\InventoryItem;
use App\Models\Outlet;
use App\Models\ProductConversion;
use App\Models\Production;
use App\Models\ProductReturn;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SalesTarget;
use App\Models\Shipment;
use App\Models\StockOpname;
use Illuminate\Database\Eloquent\Builder;

class GunsasBusinessDataContext
{
    private const DETAIL_LIMIT = 80;

    public function build(string $question, array $filters): array
    {
        $outletFilter = $this->outletFilter($filters);

        return [
            'cara_baca' => [
                'mode' => 'read_only_business_database_context',
                'catatan' => 'Data ini diambil langsung dari tabel bisnis yang aman dibaca AI. Tabel user, password, session, token, env, dan konfigurasi sensitif tidak dikirim.',
                'detail_limit_per_table' => self::DETAIL_LIMIT,
                'jika_pertanyaan_tidak_spesifik' => 'Ajukan pertanyaan klarifikasi singkat sebelum menyimpulkan.',
            ],
            'filters' => [
                'date_from' => $filters['date_from'] ?? null,
                'date_until' => $filters['date_until'] ?? null,
                'outlet_id' => $filters['outlet_id'] ?? null,
                'outlet_name' => $this->outletLabel($filters),
                'cakupan' => empty($filters['date_from']) && empty($filters['date_until'])
                    ? 'semua tanggal yang tersedia'
                    : 'periode terfilter',
            ],
            'database_catalog' => $this->databaseCatalog($filters, $outletFilter),
            'master_data' => $this->masterData(),
            'sales' => $this->salesContext($filters, $outletFilter),
            'expenses' => $this->expensesContext($filters, $outletFilter),
            'purchases' => $this->purchasesContext($filters),
            'shipments' => $this->shipmentsContext($filters, $outletFilter),
            'productions' => $this->productionsContext($filters, $outletFilter),
            'product_conversions' => $this->conversionsContext($filters, $outletFilter),
            'product_returns' => $this->returnsContext($filters, $outletFilter),
            'stock_opnames' => $this->opnamesContext($filters, $outletFilter),
            'sales_targets' => $this->salesTargetsContext($filters, $outletFilter),
        ];
    }

    private function databaseCatalog(array $filters, mixed $outletFilter): array
    {
        return [
            'sales' => $this->tableStats(Sale::query(), $filters, $outletFilter),
            'expenses' => $this->tableStats(Expense::query(), $filters, $outletFilter),
            'purchases' => $this->tableStats(Purchase::query(), $filters),
            'shipments' => $this->tableStats(Shipment::query(), $filters, $outletFilter),
            'productions' => $this->tableStats(Production::query(), $filters, $outletFilter),
            'product_conversions' => $this->tableStats(ProductConversion::query(), $filters, $outletFilter),
            'product_returns' => $this->tableStats(ProductReturn::query(), $filters, $outletFilter),
            'stock_opnames' => $this->tableStats(StockOpname::query(), $filters, $outletFilter),
        ];
    }

    private function masterData(): array
    {
        return [
            'outlets' => Outlet::query()
                ->orderBy('group_name')
                ->orderBy('name')
                ->get(['id', 'name', 'group_name', 'partner_share_percent', 'aliases'])
                ->map(fn (Outlet $outlet) => [
                    'id' => $outlet->id,
                    'name' => $outlet->name,
                    'group' => $outlet->group_name ? (Outlet::GROUPS[$outlet->group_name] ?? $outlet->group_name) : null,
                    'partner_share_percent' => (float) ($outlet->partner_share_percent ?? 15),
                    'aliases' => $outlet->aliases,
                ])
                ->all(),
            'durian_varieties' => DurianVariety::query()
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (DurianVariety $variety) => [
                    'id' => $variety->id,
                    'name' => $variety->name,
                ])
                ->all(),
            'inventory_items' => InventoryItem::query()
                ->orderBy('name')
                ->get(['id', 'name', 'category', 'unit', 'default_unit_cost', 'is_sellable'])
                ->map(fn (InventoryItem $item) => [
                    'id' => $item->id,
                    'name' => $item->name,
                    'category' => $item->category,
                    'unit' => $item->unit,
                    'default_unit_cost' => (float) $item->default_unit_cost,
                    'is_sellable' => (bool) $item->is_sellable,
                ])
                ->all(),
        ];
    }

    private function salesContext(array $filters, mixed $outletFilter): array
    {
        $netExpression = 'CASE WHEN net_sales > 0 THEN net_sales ELSE GREATEST(grand_total_revenue - discount_amount - COALESCE(sales_return_amount, 0), 0) END';
        $base = $this->businessPeriodQuery(Sale::query(), $filters, $outletFilter);
        $totals = (clone $base)
            ->selectRaw("
                COUNT(*) as records,
                COALESCE(SUM(grand_total_revenue), 0) as gross_sales,
                COALESCE(SUM(discount_amount), 0) as discount_amount,
                COALESCE(SUM(COALESCE(sales_return_amount, 0)), 0) as sales_return_amount,
                COALESCE(SUM({$netExpression}), 0) as net_sales,
                COALESCE(SUM(buah_sold_kg), 0) as buah_sold_kg,
                COALESCE(SUM(fresh_sold_kg), 0) as fresh_sold_kg,
                COALESCE(SUM(frozen_sold_kg), 0) as frozen_sold_kg
            ")
            ->first();

        return [
            'summary' => $this->modelToFloatArray($totals),
            'by_product' => $this->salesByProduct($base, $netExpression),
            'by_outlet' => $this->salesByOutlet($base, $netExpression),
            'by_date' => $this->salesByDate($base, $netExpression),
            'detail_rows' => (clone $base)
                ->with(['outlet:id,name,partner_share_percent', 'durianVariety:id,name', 'items:id,sale_id,item_name,category,unit,quantity,gross_sales,discount_amount,sales_return_amount,net_sales,total_cost'])
                ->latest('date')
                ->latest('id')
                ->limit(self::DETAIL_LIMIT)
                ->get()
                ->map(fn (Sale $sale) => [
                    'date' => $this->dateValue($sale->date),
                    'outlet' => $sale->outlet?->name,
                    'varian' => $sale->durianVariety?->name,
                    'buah_kg' => (float) $sale->buah_sold_kg,
                    'fresh_kg' => (float) $sale->fresh_sold_kg,
                    'frozen_kg' => (float) $sale->frozen_sold_kg,
                    'gross_sales' => (float) $sale->grand_total_revenue,
                    'discount' => (float) $sale->discount_amount,
                    'sales_return' => (float) $sale->sales_return_amount,
                    'net_sales' => (float) ($sale->net_sales > 0 ? $sale->net_sales : max(0, $sale->grand_total_revenue - $sale->discount_amount - $sale->sales_return_amount)),
                    'produk_lain' => $sale->items->map(fn (SaleItem $item) => [
                        'produk' => $item->item_name,
                        'kategori' => $item->category,
                        'qty' => (float) $item->quantity,
                        'satuan' => $item->unit,
                        'gross_sales' => (float) $item->gross_sales,
                        'discount' => (float) $item->discount_amount,
                        'sales_return' => (float) $item->sales_return_amount,
                        'net_sales' => (float) ($item->net_sales > 0 ? $item->net_sales : max(0, $item->gross_sales - $item->discount_amount - $item->sales_return_amount)),
                        'hpp' => (float) $item->total_cost,
                    ])->all(),
                ])
                ->all(),
        ];
    }

    private function salesByProduct(Builder $base, string $netExpression): array
    {
        $rows = (clone $base)
            ->with(['durianVariety:id,name', 'outlet:id,partner_share_percent', 'items.inventoryItem:id,name,category,unit'])
            ->get();
        $products = [];

        foreach ($rows as $sale) {
            $grossByType = [
                'Buah Utuh' => (float) $sale->buah_subtotal,
                'Kupas Fresh' => (float) $sale->fresh_subtotal,
                'Durpas Frozen' => (float) $sale->frozen_subtotal,
            ];
            $rowGross = (float) $sale->grand_total_revenue > 0 ? (float) $sale->grand_total_revenue : array_sum($grossByType);
            $rowNet = (float) $sale->net_sales > 0 ? (float) $sale->net_sales : max(0, $rowGross - (float) $sale->discount_amount - (float) $sale->sales_return_amount);
            $gunsasRate = (100 - (float) ($sale->outlet?->partner_share_percent ?? 15)) / 100;
            $variety = $sale->durianVariety?->name ?? 'Tanpa Varian';

            $this->addProductAggregate($products, 'Buah Utuh', $variety, (float) $sale->buah_sold_kg, $grossByType['Buah Utuh'], $rowGross, $rowNet, $gunsasRate);
            $this->addProductAggregate($products, 'Kupas Fresh', $variety, (float) $sale->fresh_sold_kg, $grossByType['Kupas Fresh'], $rowGross, $rowNet, $gunsasRate);
            $this->addProductAggregate($products, 'Durpas Frozen', $variety, (float) $sale->frozen_sold_kg, $grossByType['Durpas Frozen'], $rowGross, $rowNet, $gunsasRate);

            foreach ($sale->items as $item) {
                $this->addGenericProductAggregate(
                    $products,
                    (string) ($item->item_name ?: $item->inventoryItem?->name ?: 'Produk Lain'),
                    (string) ($item->category ?: $item->inventoryItem?->category ?: 'produk_jualan'),
                    (string) ($item->unit ?: $item->inventoryItem?->unit ?: 'pcs'),
                    (float) $item->quantity,
                    (float) $item->gross_sales,
                    (float) ($item->net_sales > 0 ? $item->net_sales : max(0, $item->gross_sales - $item->discount_amount - $item->sales_return_amount)),
                    $gunsasRate
                );
            }
        }

        return collect($products)
            ->map(function (array $product): array {
                $product['avg_price_per_kg'] = $product['kg'] > 0 ? $product['net_sales'] / $product['kg'] : 0;
                $product['avg_price_per_unit'] = ($product['quantity'] ?? 0) > 0 ? $product['net_sales'] / $product['quantity'] : 0;

                return $product;
            })
            ->sortByDesc('net_sales')
            ->values()
            ->all();
    }

    private function addProductAggregate(array &$products, string $category, string $variety, float $kg, float $gross, float $rowGross, float $rowNet, float $gunsasRate): void
    {
        if ($kg <= 0 && $gross <= 0) {
            return;
        }

        $key = $category . '|' . $variety;
        $products[$key] ??= [
            'product' => "{$category} {$variety}",
            'category' => $category,
            'variety' => $variety,
            'kg' => 0,
            'quantity' => 0,
            'unit' => 'kg',
            'gross_sales' => 0,
            'net_sales' => 0,
            'gunsas_revenue' => 0,
            'avg_price_per_kg' => 0,
        ];

        $allocatedNet = $rowGross > 0 ? ($gross / $rowGross) * $rowNet : $gross;
        $products[$key]['kg'] += $kg;
        $products[$key]['quantity'] += $kg;
        $products[$key]['gross_sales'] += $gross;
        $products[$key]['net_sales'] += $allocatedNet;
        $products[$key]['gunsas_revenue'] += $allocatedNet * $gunsasRate;
    }

    private function addGenericProductAggregate(array &$products, string $name, string $category, string $unit, float $quantity, float $gross, float $net, float $gunsasRate): void
    {
        if ($quantity <= 0 && $gross <= 0) {
            return;
        }

        $key = 'item|' . $name . '|' . $unit;
        $products[$key] ??= [
            'product' => $name,
            'category' => str($category)->replace('_', ' ')->title()->toString(),
            'variety' => '-',
            'kg' => $unit === 'kg' ? 0 : 0,
            'quantity' => 0,
            'unit' => $unit,
            'gross_sales' => 0,
            'net_sales' => 0,
            'gunsas_revenue' => 0,
            'avg_price_per_kg' => 0,
            'avg_price_per_unit' => 0,
        ];

        if ($unit === 'kg') {
            $products[$key]['kg'] += $quantity;
        }

        $products[$key]['quantity'] += $quantity;
        $products[$key]['gross_sales'] += $gross;
        $products[$key]['net_sales'] += $net;
        $products[$key]['gunsas_revenue'] += $net * $gunsasRate;
    }

    private function salesByOutlet(Builder $base, string $netExpression): array
    {
        return (clone $base)
            ->join('outlets', 'sales.outlet_id', '=', 'outlets.id')
            ->selectRaw("
                outlets.name as outlet,
                COALESCE(SUM({$netExpression}), 0) as net_sales,
                COALESCE(SUM(grand_total_revenue), 0) as gross_sales,
                COALESCE(SUM(discount_amount), 0) as discount_amount,
                COALESCE(SUM(COALESCE(sales_return_amount, 0)), 0) as sales_return_amount,
                COALESCE(SUM(buah_sold_kg + fresh_sold_kg + frozen_sold_kg), 0) as total_kg
            ")
            ->groupBy('outlets.name')
            ->orderByDesc('net_sales')
            ->limit(30)
            ->get()
            ->map(fn ($row) => $this->modelToFloatArray($row))
            ->all();
    }

    private function salesByDate(Builder $base, string $netExpression): array
    {
        return (clone $base)
            ->selectRaw("
                date,
                COALESCE(SUM({$netExpression}), 0) as net_sales,
                COALESCE(SUM(grand_total_revenue), 0) as gross_sales,
                COALESCE(SUM(discount_amount), 0) as discount_amount,
                COALESCE(SUM(COALESCE(sales_return_amount, 0)), 0) as sales_return_amount
            ")
            ->groupBy('date')
            ->orderByDesc('date')
            ->limit(60)
            ->get()
            ->map(fn ($row) => $this->modelToFloatArray($row))
            ->all();
    }

    private function expensesContext(array $filters, mixed $outletFilter): array
    {
        $base = $this->businessPeriodQuery(Expense::query(), $filters, $outletFilter);

        return [
            'summary' => $this->modelToFloatArray((clone $base)->selectRaw('COUNT(*) as records, COALESCE(SUM(amount), 0) as total')->first()),
            'by_category' => (clone $base)
                ->selectRaw('category, COALESCE(SUM(amount), 0) as total')
                ->groupBy('category')
                ->orderByDesc('total')
                ->get()
                ->map(fn ($row) => $this->modelToFloatArray($row))
                ->all(),
            'by_outlet' => (clone $base)
                ->leftJoin('outlets', 'expenses.outlet_id', '=', 'outlets.id')
                ->selectRaw("COALESCE(outlets.name, 'Pusat / Global') as outlet, COALESCE(SUM(expenses.amount), 0) as total")
                ->groupBy('outlet')
                ->orderByDesc('total')
                ->get()
                ->map(fn ($row) => $this->modelToFloatArray($row))
                ->all(),
            'detail_rows' => (clone $base)
                ->with('outlet:id,name')
                ->latest('date')
                ->latest('id')
                ->limit(self::DETAIL_LIMIT)
                ->get()
                ->map(fn (Expense $expense) => [
                    'date' => $this->dateValue($expense->date),
                    'outlet' => $expense->outlet?->name ?? 'Pusat / Global',
                    'category' => $expense->category,
                    'amount' => (float) $expense->amount,
                    'notes' => $expense->notes,
                ])
                ->all(),
        ];
    }

    private function purchasesContext(array $filters): array
    {
        $base = $this->applyDateFilter(Purchase::query(), $filters);

        return [
            'summary' => $this->modelToFloatArray((clone $base)->selectRaw('COUNT(*) as records, COALESCE(SUM(COALESCE(total_amount, 0) + COALESCE(generic_total_amount, 0)), 0) as total, COALESCE(SUM(qty_kg), 0) as durian_kg, COALESCE(SUM(qty_butir), 0) as durian_butir')->first()),
            'by_supplier' => (clone $base)
                ->selectRaw("COALESCE(supplier_name, supplier_code, 'Tanpa Supplier') as supplier, COALESCE(SUM(COALESCE(total_amount, 0) + COALESCE(generic_total_amount, 0)), 0) as total, COALESCE(SUM(qty_kg), 0) as kg")
                ->groupBy('supplier')
                ->orderByDesc('total')
                ->limit(30)
                ->get()
                ->map(fn ($row) => $this->modelToFloatArray($row))
                ->all(),
            'detail_rows' => (clone $base)
                ->with(['durianVariety:id,name', 'inventoryItem:id,name,unit'])
                ->latest('date')
                ->latest('id')
                ->limit(self::DETAIL_LIMIT)
                ->get()
                ->map(fn (Purchase $purchase) => [
                    'date' => $this->dateValue($purchase->date),
                    'mode' => $purchase->purchase_mode,
                    'varian' => $purchase->durianVariety?->name,
                    'inventory_item' => $purchase->inventoryItem?->name,
                    'supplier_code' => $purchase->supplier_code,
                    'supplier_name' => $purchase->supplier_name,
                    'qty_butir' => (float) $purchase->qty_butir,
                    'qty_kg' => (float) $purchase->qty_kg,
                    'generic_qty' => (float) $purchase->generic_qty,
                    'generic_unit' => $purchase->generic_unit,
                    'total_amount' => (float) ((float) $purchase->total_amount + (float) $purchase->generic_total_amount),
                    'notes' => $purchase->notes,
                ])
                ->all(),
        ];
    }

    private function shipmentsContext(array $filters, mixed $outletFilter): array
    {
        $base = $this->businessPeriodQuery(Shipment::query(), $filters, $outletFilter);

        return [
            'summary' => $this->modelToFloatArray((clone $base)->selectRaw('COUNT(*) as records, COALESCE(SUM(qty_sent_kg), 0) as sent_kg, COALESCE(SUM(qty_received_kg), 0) as received_kg, COALESCE(SUM(COALESCE(value_purchase, 0) + COALESCE(generic_total_amount, 0)), 0) as value')->first()),
            'by_outlet_product' => (clone $base)
                ->leftJoin('outlets', 'shipments.outlet_id', '=', 'outlets.id')
                ->selectRaw("COALESCE(outlets.name, 'Tanpa Outlet') as outlet, COALESCE(shipments.product_type, shipments.shipment_mode) as product_type, shipments.shipment_direction, COALESCE(SUM(qty_sent_kg), 0) as sent_kg, COALESCE(SUM(qty_received_kg), 0) as received_kg, COALESCE(SUM(COALESCE(value_purchase, 0) + COALESCE(generic_total_amount, 0)), 0) as value")
                ->groupBy('outlets.name', 'shipments.product_type', 'shipments.shipment_mode', 'shipments.shipment_direction')
                ->orderByDesc('value')
                ->limit(40)
                ->get()
                ->map(fn ($row) => $this->modelToFloatArray($row))
                ->all(),
            'detail_rows' => (clone $base)
                ->with(['outlet:id,name', 'durianVariety:id,name', 'inventoryItem:id,name,unit'])
                ->latest('date')
                ->latest('id')
                ->limit(self::DETAIL_LIMIT)
                ->get()
                ->map(fn (Shipment $shipment) => [
                    'date' => $this->dateValue($shipment->date),
                    'outlet' => $shipment->outlet?->name,
                    'direction' => $shipment->shipment_direction,
                    'mode' => $shipment->shipment_mode,
                    'product_type' => $shipment->product_type,
                    'varian' => $shipment->durianVariety?->name,
                    'inventory_item' => $shipment->inventoryItem?->name,
                    'sent_kg' => (float) $shipment->qty_sent_kg,
                    'received_kg' => (float) $shipment->qty_received_kg,
                    'sent_qty' => (float) $shipment->generic_qty_sent,
                    'received_qty' => (float) $shipment->generic_qty_received,
                    'unit' => $shipment->generic_unit,
                    'value' => (float) ((float) $shipment->value_purchase + (float) $shipment->generic_total_amount),
                ])
                ->all(),
        ];
    }

    private function productionsContext(array $filters, mixed $outletFilter): array
    {
        $base = $this->businessPeriodQuery(Production::query(), $filters, $outletFilter);

        return [
            'summary' => $this->modelToFloatArray((clone $base)->selectRaw('COUNT(*) as records, COALESCE(SUM(qty_buah_kg), 0) as input_kg, COALESCE(SUM(CASE WHEN COALESCE(source_type, "normal") <> "return" THEN qty_buah_kg ELSE 0 END), 0) as normal_input_kg, COALESCE(SUM(CASE WHEN COALESCE(source_type, "normal") = "return" THEN qty_buah_kg ELSE 0 END), 0) as return_input_kg, COALESCE(SUM(qty_kupas_kg), 0) as fresh_kg, COALESCE(SUM(qty_olahan_kg), 0) as olahan_kg, COALESCE(SUM(total_usable_meat_kg), 0) as usable_kg')->first()),
            'by_outlet' => (clone $base)
                ->leftJoin('outlets', 'productions.outlet_id', '=', 'outlets.id')
                ->selectRaw("COALESCE(outlets.name, 'Tanpa Outlet') as outlet, COALESCE(SUM(qty_buah_kg), 0) as input_kg, COALESCE(SUM(CASE WHEN COALESCE(source_type, 'normal') <> 'return' THEN qty_buah_kg ELSE 0 END), 0) as normal_input_kg, COALESCE(SUM(CASE WHEN COALESCE(source_type, 'normal') = 'return' THEN qty_buah_kg ELSE 0 END), 0) as return_input_kg, COALESCE(SUM(qty_kupas_kg), 0) as fresh_kg, COALESCE(SUM(qty_olahan_kg), 0) as olahan_kg, COALESCE(AVG(shrinkage_percentage), 0) as avg_shrinkage_percentage")
                ->groupBy('outlet')
                ->orderByDesc('input_kg')
                ->limit(40)
                ->get()
                ->map(fn ($row) => $this->modelToFloatArray($row))
                ->all(),
            'detail_rows' => (clone $base)
                ->with(['outlet:id,name', 'durianVariety:id,name'])
                ->latest('date')
                ->latest('id')
                ->limit(self::DETAIL_LIMIT)
                ->get()
                ->map(fn (Production $production) => [
                    'date' => $this->dateValue($production->date),
                    'outlet' => $production->outlet?->name,
                    'varian' => $production->durianVariety?->name,
                    'source_type' => $production->source_type,
                    'buah_butir' => (float) $production->qty_buah_butir,
                    'buah_kg' => (float) $production->qty_buah_kg,
                    'fresh_kg' => (float) $production->qty_kupas_kg,
                    'olahan_kg' => (float) $production->qty_olahan_kg,
                    'usable_kg' => (float) $production->total_usable_meat_kg,
                    'shrinkage_percentage' => (float) $production->shrinkage_percentage,
                    'multiplier_factor' => (float) $production->multiplier_factor,
                ])
                ->all(),
        ];
    }

    private function conversionsContext(array $filters, mixed $outletFilter): array
    {
        $base = $this->businessPeriodQuery(ProductConversion::query(), $filters, $outletFilter);

        return [
            'summary' => $this->modelToFloatArray((clone $base)->selectRaw('COUNT(*) as records, COALESCE(SUM(from_qty_kg), 0) as from_kg, COALESCE(SUM(to_qty_kg), 0) as to_kg')->first()),
            'detail_rows' => (clone $base)
                ->with(['outlet:id,name', 'durianVariety:id,name'])
                ->latest('date')
                ->latest('id')
                ->limit(self::DETAIL_LIMIT)
                ->get()
                ->map(fn (ProductConversion $conversion) => [
                    'date' => $this->dateValue($conversion->date),
                    'outlet' => $conversion->outlet?->name,
                    'varian' => $conversion->durianVariety?->name,
                    'type' => $conversion->conversion_type,
                    'from_kg' => (float) $conversion->from_qty_kg,
                    'to_kg' => (float) $conversion->to_qty_kg,
                    'notes' => $conversion->notes,
                ])
                ->all(),
        ];
    }

    private function returnsContext(array $filters, mixed $outletFilter): array
    {
        $base = $this->businessPeriodQuery(ProductReturn::query(), $filters, $outletFilter);

        return [
            'summary' => $this->modelToFloatArray((clone $base)->selectRaw('COUNT(*) as records, COALESCE(SUM(qty_kg), 0) as submitted_kg, COALESCE(SUM(supplier_accepted_qty_kg), 0) as accepted_kg, COALESCE(SUM(refund_amount), 0) as refund')->first()),
            'by_status' => (clone $base)
                ->selectRaw("status, COALESCE(SUM(qty_kg), 0) as kg, COALESCE(SUM(supplier_accepted_qty_kg), 0) as accepted_kg, COALESCE(SUM(refund_amount), 0) as refund, COUNT(*) as records")
                ->groupBy('status')
                ->get()
                ->map(fn ($row) => $this->modelToFloatArray($row))
                ->all(),
            'detail_rows' => (clone $base)
                ->with(['outlet:id,name', 'durianVariety:id,name'])
                ->latest('date')
                ->latest('id')
                ->limit(self::DETAIL_LIMIT)
                ->get()
                ->map(fn (ProductReturn $return) => [
                    'date' => $this->dateValue($return->date),
                    'outlet' => $return->outlet?->name,
                    'varian' => $return->durianVariety?->name,
                    'supplier_code' => $return->supplier_code,
                    'paint_color' => $return->paint_color,
                    'status' => $return->status,
                    'reason' => $return->detailed_reason,
                    'qty_butir' => (float) $return->qty_butir,
                    'qty_kg' => (float) $return->qty_kg,
                    'accepted_butir' => (float) $return->supplier_accepted_qty_butir,
                    'accepted_kg' => (float) $return->supplier_accepted_qty_kg,
                    'refund' => (float) $return->refund_amount,
                ])
                ->all(),
        ];
    }

    private function opnamesContext(array $filters, mixed $outletFilter): array
    {
        $base = $this->businessPeriodQuery(StockOpname::query(), $filters, $outletFilter);

        return [
            'summary' => $this->modelToFloatArray((clone $base)->selectRaw('COUNT(*) as records, COALESCE(SUM(system_qty_kg), 0) as system_kg, COALESCE(SUM(physical_qty_kg), 0) as physical_kg, COALESCE(SUM(difference_qty_kg), 0) as difference_kg, COALESCE(SUM(generic_consumed_amount), 0) as inventory_consumed_amount')->first()),
            'latest_by_outlet_product' => (clone $base)
                ->with(['outlet:id,name', 'durianVariety:id,name', 'inventoryItem:id,name'])
                ->latest('date')
                ->latest('id')
                ->limit(self::DETAIL_LIMIT)
                ->get()
                ->map(fn (StockOpname $opname) => [
                    'date' => $this->dateValue($opname->date),
                    'outlet' => $opname->outlet?->name,
                    'varian' => $opname->durianVariety?->name,
                    'inventory_item' => $opname->inventoryItem?->name,
                    'product_type' => $opname->product_type,
                    'system_kg' => (float) $opname->system_qty_kg,
                    'physical_kg' => (float) $opname->physical_qty_kg,
                    'difference_kg' => (float) $opname->difference_qty_kg,
                    'inventory_consumed_qty' => (float) $opname->generic_consumed_qty,
                    'unit' => $opname->generic_unit,
                    'inventory_consumed_amount' => (float) $opname->generic_consumed_amount,
                    'notes' => $opname->notes,
                ])
                ->all(),
        ];
    }

    private function salesTargetsContext(array $filters, mixed $outletFilter): array
    {
        $query = SalesTarget::query()
            ->with('outlet:id,name')
            ->when($filters['date_from'] ?? null, fn (Builder $query, $date) => $query->where('period_end', '>=', $date))
            ->when($filters['date_until'] ?? null, fn (Builder $query, $date) => $query->where('period_start', '<=', $date));

        $this->applyOutletFilter($query, $outletFilter);

        return [
            'rows' => $query
                ->latest('period_start')
                ->limit(self::DETAIL_LIMIT)
                ->get()
                ->map(fn (SalesTarget $target) => [
                    'outlet' => $target->outlet?->name,
                    'period_type' => $target->period_type,
                    'period_start' => $this->dateValue($target->period_start),
                    'period_end' => $this->dateValue($target->period_end),
                    'target_amount' => (float) $target->target_amount,
                    'notes' => $target->notes,
                ])
                ->all(),
        ];
    }

    private function tableStats(Builder $query, array $filters, mixed $outletFilter = null): array
    {
        $query = $this->businessPeriodQuery($query, $filters, $outletFilter);

        return $this->modelToFloatArray($query
            ->selectRaw('COUNT(*) as records, MIN(date) as first_date, MAX(date) as last_date')
            ->first());
    }

    private function businessPeriodQuery(Builder $query, array $filters, mixed $outletFilter = null): Builder
    {
        $this->applyDateFilter($query, $filters);

        if ($outletFilter !== null) {
            $this->applyOutletFilter($query, $outletFilter);
        }

        return $query;
    }

    private function applyDateFilter(Builder $query, array $filters): Builder
    {
        return $query
            ->when($filters['date_from'] ?? null, fn (Builder $query, $date) => $query->where('date', '>=', $date))
            ->when($filters['date_until'] ?? null, fn (Builder $query, $date) => $query->where('date', '<=', $date));
    }

    private function applyOutletFilter(Builder $query, mixed $outletFilter): Builder
    {
        if (is_array($outletFilter)) {
            return count($outletFilter) > 0 ? $query->whereIn('outlet_id', $outletFilter) : $query->whereRaw('1 = 0');
        }

        return $outletFilter ? $query->where('outlet_id', $outletFilter) : $query;
    }

    private function outletFilter(array $filters): mixed
    {
        if (! empty($filters['outlet_id'])) {
            return $filters['outlet_id'];
        }

        if (! empty($filters['outlet_group'])) {
            return Outlet::query()
                ->where('group_name', Outlet::normalizeGroupName($filters['outlet_group']))
                ->pluck('id')
                ->all();
        }

        return null;
    }

    private function outletLabel(array $filters): string
    {
        if (! empty($filters['outlet_id'])) {
            return Outlet::query()->whereKey($filters['outlet_id'])->value('name') ?? 'Outlet tidak ditemukan';
        }

        if (! empty($filters['outlet_group'])) {
            return Outlet::GROUPS[$filters['outlet_group']] ?? $filters['outlet_group'];
        }

        return 'Semua Outlet';
    }

    private function modelToFloatArray(mixed $row): array
    {
        $values = is_object($row) && method_exists($row, 'getAttributes')
            ? $row->getAttributes()
            : (array) $row;

        return collect($values)
            ->map(fn ($value) => is_numeric($value) ? (float) $value : $value)
            ->all();
    }

    private function dateValue(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return is_object($value) && method_exists($value, 'toDateString')
            ? $value->toDateString()
            : (string) $value;
    }
}
