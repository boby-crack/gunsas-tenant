<?php

namespace App\Filament\Resources\WhatsappReportResource\Pages;

use App\Filament\Resources\WhatsappReportResource;
use App\Services\WhatsappReportParser;
use Filament\Resources\Pages\CreateRecord;

class CreateWhatsappReport extends CreateRecord
{
    protected static string $resource = WhatsappReportResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $parsed = app(WhatsappReportParser::class)->parse($data['raw_message']);

        return array_merge($data, [
            'report_type' => $parsed['report_type'],
            'parsed_payload' => $parsed['parsed_payload'],
            'confidence' => $parsed['confidence'],
            'status' => $parsed['status'],
            'error_notes' => $parsed['error_notes'],
        ]);
    }
}
