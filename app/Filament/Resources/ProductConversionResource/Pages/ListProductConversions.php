<?php

namespace App\Filament\Resources\ProductConversionResource\Pages;

use App\Filament\Resources\Concerns\HasExcelImportAction;
use App\Filament\Resources\ProductConversionResource;
use App\Imports\ProductConversionsImport;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListProductConversions extends ListRecords
{
    use HasExcelImportAction;

    protected static string $resource = ProductConversionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->excelTemplateAction(
                'template-product-conversions.xlsx',
                [
                    'tanggal',
                    'outlet',
                    'varian',
                    'tipe_konversi',
                    'fresh_kg',
                    'fresh_pack',
                    'frozen_kg',
                    'frozen_pack',
                    'catatan',
                ],
                [['2026-07-03', 'TIPTOP RAWAMANGUN', 'MONTHONG', 'Kupas Fresh ke Kupas Frozen', 3.05, 0, 1.172, 3, 'Contoh durpas']],
            ),
            $this->excelImportAction(ProductConversionsImport::class),
            Actions\CreateAction::make(),
        ];
    }
}
