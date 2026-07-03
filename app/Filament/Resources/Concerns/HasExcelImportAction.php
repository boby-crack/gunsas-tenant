<?php

namespace App\Filament\Resources\Concerns;

use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;

trait HasExcelImportAction
{
    protected function excelImportAction(string $importClass, string $label = 'Import Excel'): Action
    {
        return Action::make('importExcel')
            ->label($label)
            ->icon('heroicon-o-arrow-up-tray')
            ->color('success')
            ->modalHeading($label)
            ->form([
                FileUpload::make('file')
                    ->label('File Excel')
                    ->disk('local')
                    ->directory('imports')
                    ->acceptedFileTypes([
                        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                        'application/vnd.ms-excel',
                    ])
                    ->helperText('Gunakan file .xlsx atau .xls dengan baris pertama sebagai nama kolom.')
                    ->required(),
            ])
            ->action(function (array $data) use ($importClass): void {
                $import = new $importClass();
                $path = Storage::disk('local')->path($data['file']);

                Excel::import($import, $path);

                Notification::make()
                    ->title($import->errorCount() > 0 ? 'Import selesai dengan catatan' : 'Import berhasil')
                    ->body($import->summary())
                    ->color($import->errorCount() > 0 ? 'warning' : 'success')
                    ->send();
            });
    }
}
