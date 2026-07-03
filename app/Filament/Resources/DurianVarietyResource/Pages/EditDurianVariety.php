<?php

namespace App\Filament\Resources\DurianVarietyResource\Pages;

use App\Filament\Resources\DurianVarietyResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditDurianVariety extends EditRecord
{
    protected static string $resource = DurianVarietyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
