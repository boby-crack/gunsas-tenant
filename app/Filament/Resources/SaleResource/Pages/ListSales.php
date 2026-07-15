<?php

namespace App\Filament\Resources\SaleResource\Pages;

use App\Filament\Resources\Concerns\HasExcelImportAction;
use App\Filament\Resources\SaleResource;
use App\Imports\SalesImport;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListSales extends ListRecords
{
    use HasExcelImportAction;

    protected static string $resource = SaleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->excelTemplateAction(
                'template-sales.xlsx',
                [
                    'tanggal',
                    'outlet',
                    'varian',
                    'kategori_produk',
                    'quantity_kg',
                    'gross_sales',
                    'discount',
                    'sales_return',
                    'sales_setelah_diskon',
                    'catatan',
                ],
                [
                    ['2026-06-17', 'TIPTOP RAWAMANGUN', 'MONTHONG', 'buah', 10.482, 1441824, 24343, 477480, 940001, 'Contoh TipTop dengan sales return'],
                    ['2026-05-23', 'TIPTOP RAWAMANGUN', 'MONTHONG', 'fresh', 10.500, 990000, 0, 0, 990000, 'Isi kategori: buah / fresh / frozen'],
                    ['2026-05-24', 'TIPTOP RAWAMANGUN', 'MONTHONG', 'frozen', 0.450, 54000, 0, 0, 54000, 'Durpas frozen'],
                ],
            ),
            $this->excelImportAction(SalesImport::class),
            Actions\CreateAction::make(),
        ];
    }
}
