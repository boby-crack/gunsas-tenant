<?php

namespace App\Filament\Resources\ProductReturnResource\Pages;

use App\Filament\Resources\Concerns\HasExcelImportAction;
use App\Filament\Resources\ProductReturnResource;
use App\Imports\ProductReturnsImport;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListProductReturns extends ListRecords
{
    use HasExcelImportAction;

    protected static string $resource = ProductReturnResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->excelTemplateAction(
                'template-product-returns.xlsx',
                [
                    'tanggal',
                    'outlet',
                    'varian',
                    'kode_supplier',
                    'warna_cat',
                    'butir',
                    'berat_kg',
                    'alasan',
                    'status',
                    'diterima_supplier_butir',
                    'diterima_supplier_kg',
                    'refund',
                    'catatan',
                ],
                [['2026-07-03', 'TIPTOP RAWAMANGUN', 'MONTHONG', 'SPL-01', 'merah', 1, 3.54, 'mengkel, rasa hambar', 'pending', '', '', 0, 'Contoh retur']],
            ),
            $this->excelImportAction(ProductReturnsImport::class),
            Actions\CreateAction::make(),
        ];
    }
}
