<?php

namespace App\Filament\Resources\WhatsappReportResource\Pages;

use App\Filament\Resources\WhatsappReportResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListWhatsappReports extends ListRecords
{
    protected static string $resource = WhatsappReportResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
