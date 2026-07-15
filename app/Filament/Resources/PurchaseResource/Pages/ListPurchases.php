<?php

namespace App\Filament\Resources\PurchaseResource\Pages;

use App\Filament\Resources\Concerns\HasExcelImportAction;
use App\Filament\Resources\PurchaseResource;
use App\Imports\PurchasesImport;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListPurchases extends ListRecords
{
    use HasExcelImportAction;

    protected static string $resource = PurchaseResource::class;

    protected function getHeaderActions(): array
    {
        return [
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
