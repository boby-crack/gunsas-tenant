<?php

namespace App\Filament\Resources\DurianVarietyResource\Pages;

use App\Filament\Resources\DurianVarietyResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListDurianVarieties extends ListRecords
{
    protected static string $resource = DurianVarietyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
