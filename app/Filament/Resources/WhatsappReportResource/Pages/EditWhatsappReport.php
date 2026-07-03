<?php

namespace App\Filament\Resources\WhatsappReportResource\Pages;

use App\Filament\Resources\WhatsappReportResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditWhatsappReport extends EditRecord
{
    protected static string $resource = WhatsappReportResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
