<?php

namespace App\Filament\Resources\SaleResource\Pages;

use App\Filament\Resources\Concerns\HasExcelImportAction;
use App\Filament\Resources\Concerns\HasListSummaryHeader;
use App\Filament\Resources\SaleResource;
use App\Imports\SalesImport;
use App\Models\Sale;
use App\Models\SaleItem;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Tables\Table;
use Illuminate\Database\Query\Builder as QueryBuilder;

class ListSales extends ListRecords
{
    use HasExcelImportAction;
    use HasListSummaryHeader;

    protected static string $resource = SaleResource::class;

    public function table(Table $table): Table
    {
        return parent::table($table)
            ->header(fn () => $this->summaryHeader($this->getSaleSummaryItems()));
    }

    protected function getSaleSummaryItems(): array
    {
        $netSalesExpression = 'CASE WHEN net_sales > 0 THEN net_sales ELSE GREATEST(grand_total_revenue - discount_amount - COALESCE(sales_return_amount, 0), 0) END';
        $itemNetExpression = 'CASE WHEN net_sales > 0 THEN net_sales ELSE GREATEST(gross_sales - COALESCE(discount_amount, 0) - COALESCE(sales_return_amount, 0), 0) END';

        $row = Sale::query()
            ->whereIn('sales.id', $this->filteredSaleIdsQuery())
            ->selectRaw("
                COALESCE(SUM(buah_sold_kg), 0) as buah_kg,
                COALESCE(SUM(fresh_sold_kg), 0) as fresh_kg,
                COALESCE(SUM(frozen_sold_kg), 0) as frozen_kg,
                COALESCE(SUM(grand_total_revenue), 0) as gross_sales,
                COALESCE(SUM(discount_amount), 0) as discount_amount,
                COALESCE(SUM(sales_return_amount), 0) as sales_return_amount,
                COALESCE(SUM({$netSalesExpression}), 0) as net_sales
            ")
            ->first();

        $itemRow = SaleItem::query()
            ->whereIn('sale_id', $this->filteredSaleIdsQuery())
            ->where('category', 'produk_jualan')
            ->when($this->selectedInventoryItemId(), fn ($query, int $id) => $query->where('inventory_item_id', $id))
            ->selectRaw("
                COALESCE(SUM(quantity), 0) as item_qty,
                COALESCE(SUM(gross_sales), 0) as item_gross_sales,
                COALESCE(SUM(discount_amount), 0) as item_discount_amount,
                COALESCE(SUM(sales_return_amount), 0) as item_sales_return_amount,
                COALESCE(SUM({$itemNetExpression}), 0) as item_net_sales,
                COALESCE(SUM(total_cost), 0) as item_total_cost
            ")
            ->first();

        $totalKg = (float) ($row->buah_kg ?? 0) + (float) ($row->fresh_kg ?? 0) + (float) ($row->frozen_kg ?? 0);

        return [
            ['label' => 'Sales Net', 'value' => $this->rupiah((float) ($row->net_sales ?? 0))],
            ['label' => 'Gross Sales', 'value' => $this->rupiah((float) ($row->gross_sales ?? 0))],
            ['label' => 'Diskon', 'value' => $this->rupiah((float) ($row->discount_amount ?? 0))],
            ['label' => 'Sales Return', 'value' => $this->rupiah((float) ($row->sales_return_amount ?? 0))],
            ['label' => 'Buah Utuh', 'value' => $this->kg((float) ($row->buah_kg ?? 0))],
            ['label' => 'Daging Fresh', 'value' => $this->kg((float) ($row->fresh_kg ?? 0))],
            ['label' => 'Durpas Frozen', 'value' => $this->kg((float) ($row->frozen_kg ?? 0))],
            ['label' => 'Total Durian', 'value' => $this->kg($totalKg)],
            ['label' => 'Produk Non-Durian', 'value' => $this->qty((float) ($itemRow->item_qty ?? 0), 'unit'), 'description' => 'Net ' . $this->rupiah((float) ($itemRow->item_net_sales ?? 0))],
            ['label' => 'Gross Non-Durian', 'value' => $this->rupiah((float) ($itemRow->item_gross_sales ?? 0))],
            ['label' => 'Diskon Non-Durian', 'value' => $this->rupiah((float) ($itemRow->item_discount_amount ?? 0))],
            ['label' => 'Return Non-Durian', 'value' => $this->rupiah((float) ($itemRow->item_sales_return_amount ?? 0))],
            ['label' => 'HPP Non-Durian', 'value' => $this->rupiah((float) ($itemRow->item_total_cost ?? 0))],
        ];
    }

    protected function filteredSaleIdsQuery(): QueryBuilder
    {
        return (clone $this->getFilteredTableQuery())
            ->toBase()
            ->cloneWithout(['columns', 'orders', 'limit', 'offset'])
            ->cloneWithoutBindings(['select', 'order'])
            ->select('sales.id');
    }

    protected function selectedInventoryItemId(): ?int
    {
        $filter = $this->tableFilters['inventory_item_id'] ?? [];
        $value = $filter['value'] ?? null;

        return filled($value) ? (int) $value : null;
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->excelUpdateExportAction(
                'update-sales.xlsx',
                [
                    'id' => 'id',
                    'tanggal' => fn ($record) => $record->date instanceof \DateTimeInterface ? $record->date->format('Y-m-d') : $record->date,
                    'outlet' => fn ($record) => $record->outlet?->name,
                    'varian' => fn ($record) => $record->durianVariety?->name,
                    'buah_kg' => 'buah_sold_kg',
                    'buah_subtotal' => 'buah_subtotal',
                    'fresh_kg' => 'fresh_sold_kg',
                    'fresh_subtotal' => 'fresh_subtotal',
                    'frozen_kg' => 'frozen_sold_kg',
                    'frozen_subtotal' => 'frozen_subtotal',
                    'gross_sales' => 'grand_total_revenue',
                    'discount' => 'discount_amount',
                    'sales_return' => 'sales_return_amount',
                    'sales_setelah_diskon' => 'net_sales',
                ],
                ['outlet', 'durianVariety'],
            ),
            $this->excelTemplateAction(
                'template-sales.xlsx',
                [
                    'tanggal',
                    'outlet',
                    'varian',
                    'kategori_produk',
                    'produk',
                    'quantity_kg',
                    'quantity',
                    'satuan',
                    'unit_price',
                    'gross_sales',
                    'discount',
                    'sales_return',
                    'sales_setelah_diskon',
                    'catatan',
                ],
                [
                    ['2026-06-17', 'TIPTOP RAWAMANGUN', 'MONTHONG', 'buah', '', 10.482, '', '', '', 1441824, 24343, 477480, 940001, 'Durian: isi kategori_produk buah/fresh/frozen dan quantity_kg'],
                    ['2026-05-23', 'TIPTOP RAWAMANGUN', 'MONTHONG', 'fresh', '', 10.500, '', '', '', 990000, 0, 0, 990000, 'Durian fresh'],
                    ['2026-05-24', 'TOTAL BUAH BSD', '', '', 'Pancake Durian', '', 12, 'pcs', 25000, 300000, 0, 0, 300000, 'Produk non-durian: modal otomatis dari Master Produk'],
                ],
            ),
            $this->excelImportAction(SalesImport::class),
            Actions\CreateAction::make(),
        ];
    }
}
