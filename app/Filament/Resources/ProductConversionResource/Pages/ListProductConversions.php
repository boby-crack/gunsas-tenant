<?php

namespace App\Filament\Resources\ProductConversionResource\Pages;

use App\Filament\Resources\ProductConversionResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListProductConversions extends ListRecords
{
    protected static string $resource = ProductConversionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
