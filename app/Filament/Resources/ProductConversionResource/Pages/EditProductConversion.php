<?php

namespace App\Filament\Resources\ProductConversionResource\Pages;

use App\Filament\Resources\ProductConversionResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditProductConversion extends EditRecord
{
    protected static string $resource = ProductConversionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
