<?php

namespace App\Filament\Resources\PurchaseResource\Pages;

use App\Filament\Resources\Concerns\HasExcelImportAction;
use App\Filament\Resources\Concerns\HasListSummaryHeader;
use App\Filament\Resources\PurchaseResource;
use App\Imports\PurchasesImport;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Tables\Table;

class ListPurchases extends ListRecords
{
    use HasExcelImportAction;
    use HasListSummaryHeader;

    protected static string $resource = PurchaseResource::class;

    public function table(Table $table): Table
    {
        return parent::table($table)
            ->header(fn () => $this->summaryHeader($this->getPurchaseSummaryItems()));
    }

    protected function getPurchaseSummaryItems(): array
    {
        $row = $this->filteredSummaryQuery()
            ->selectRaw("
                COALESCE(SUM(CASE WHEN COALESCE(purchase_mode, 'durian') <> 'inventory' THEN qty_butir ELSE 0 END), 0) as durian_butir,
                COALESCE(SUM(CASE WHEN COALESCE(purchase_mode, 'durian') <> 'inventory' THEN qty_kg ELSE 0 END), 0) as durian_kg,
                COALESCE(SUM(CASE WHEN COALESCE(purchase_mode, 'durian') <> 'inventory' THEN total_amount ELSE 0 END), 0) as durian_amount,
                COALESCE(SUM(CASE WHEN purchase_mode = 'inventory' THEN generic_qty ELSE 0 END), 0) as inventory_qty,
                COALESCE(SUM(CASE WHEN purchase_mode = 'inventory' THEN generic_total_amount ELSE total_amount END), 0) as total_amount
            ")
            ->first();

        $durianKg = (float) ($row->durian_kg ?? 0);
        $durianAmount = (float) ($row->durian_amount ?? 0);
        $totalAmount = (float) ($row->total_amount ?? 0);

        return [
            ['label' => 'Total Pembelian', 'value' => $this->rupiah($totalAmount)],
            ['label' => 'Buah Dibeli', 'value' => $this->whole((float) ($row->durian_butir ?? 0), 'btr') . ' / ' . $this->kg($durianKg)],
            ['label' => 'Avg Modal Buah', 'value' => $durianKg > 0 ? $this->rupiah($durianAmount / $durianKg) . ' / Kg' : $this->rupiah(0) . ' / Kg'],
            ['label' => 'Qty Inventory', 'value' => $this->qty((float) ($row->inventory_qty ?? 0))],
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->excelUpdateExportAction(
                'update-purchases.xlsx',
                [
                    'id' => 'id',
                    'jenis_pembelian' => 'purchase_mode',
                    'tanggal' => fn ($record) => $record->date instanceof \DateTimeInterface ? $record->date->format('Y-m-d') : $record->date,
                    'varian' => fn ($record) => $record->durianVariety?->name,
                    'produk' => fn ($record) => $record->inventoryItem?->name,
                    'kode_supplier' => 'supplier_code',
                    'supplier' => 'supplier_name',
                    'butir' => 'qty_butir',
                    'berat_kg' => 'qty_kg',
                    'harga_per_kg' => 'price_per_kg',
                    'qty' => 'generic_qty',
                    'satuan' => 'generic_unit',
                    'harga_satuan' => 'generic_unit_cost',
                    'catatan' => 'notes',
                ],
                ['durianVariety', 'inventoryItem'],
            ),
            $this->excelTemplateAction(
                'template-purchases.xlsx',
                ['jenis_pembelian', 'tanggal', 'varian', 'produk', 'kode_supplier', 'supplier', 'butir', 'berat_kg', 'harga_per_kg', 'qty', 'satuan', 'harga_satuan', 'catatan'],
                [
                    ['durian', '2026-07-03', 'MONTHONG', '', 'SPL-01', 'Supplier A', 10, 35.5, 66000, '', '', '', 'Contoh pembelian buah'],
                    ['inventory', '2026-07-03', '', 'Thinwall 500ml', 'SPL-02', 'Toko Kemasan', '', '', '', 1000, 'pcs', 500, 'Contoh pembelian inventory'],
                ],
            ),
            $this->excelImportAction(PurchasesImport::class),
            Actions\CreateAction::make(),
        ];
    }
}
