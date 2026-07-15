<?php

namespace App\Filament\Resources\ProductionResource\Pages;

use App\Filament\Resources\Concerns\HasExcelImportAction;
use App\Filament\Resources\ProductionResource;
use App\Imports\ProductionsImport;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListProductions extends ListRecords
{
    use HasExcelImportAction;

    protected static string $resource = ProductionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->excelTemplateAction(
                'template-productions.xlsx',
                [
                    'tanggal',
                    'outlet',
                    'varian',
                    'sumber',
                    'buah_butir',
                    'buah_kg',
                    'fresh_kg',
                    'fresh_pack',
                    'olahan_kg',
                    'olahan_pack',
                ],
                [['2026-07-03', 'TIPTOP RAWAMANGUN', 'MONTHONG', 'normal', 10, 30.5, 11.72, 3, 0.198, 1]],
            ),
            $this->excelImportAction(ProductionsImport::class),
            Actions\CreateAction::make(),
        ];
    }
}
