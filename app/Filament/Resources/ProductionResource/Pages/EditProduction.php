<?php

namespace App\Filament\Resources\ProductionResource\Pages;

use App\Filament\Resources\ProductionResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditProduction extends EditRecord
{
    protected static string $resource = ProductionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
