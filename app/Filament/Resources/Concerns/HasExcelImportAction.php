<?php

namespace App\Filament\Resources\Concerns;

use App\Exports\ExcelTemplateExport;
use App\Exports\UpdateRecordsExport;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use Throwable;

trait HasExcelImportAction
{
    protected function excelTemplateAction(string $fileName, array $headings, array $sampleRows = []): Action
    {
        return Action::make('downloadExcelTemplate')
            ->label('Download Template')
            ->icon('heroicon-o-arrow-down-tray')
            ->color('gray')
            ->action(fn () => Excel::download(
                new ExcelTemplateExport($headings, $sampleRows),
                $fileName,
            ));
    }

    protected function excelUpdateExportAction(string $fileName, array $columns, array $with = []): Action
    {
        return Action::make('exportUpdateExcel')
            ->label('Export Update')
            ->icon('heroicon-o-arrow-down-on-square-stack')
            ->color('gray')
            ->action(function () use ($fileName, $columns, $with) {
                $query = clone $this->getFilteredTableQuery();

                if ($with !== []) {
                    $query->with($with);
                }

                return Excel::download(
                    new UpdateRecordsExport($query->get(), $columns),
                    $fileName,
                );
            });
    }

    protected function excelImportAction(string $importClass, string $label = 'Import Excel'): Action
    {
        return Action::make('importExcel')
            ->label($label)
            ->icon('heroicon-o-arrow-up-tray')
            ->color('success')
            ->modalHeading($label)
            ->form($this->excelImportForm())
            ->modalSubmitActionLabel('Import Excel')
            ->extraModalFooterActions(fn (Action $action): array => [
                $action->makeModalSubmitAction('testImport', arguments: ['test' => true])
                    ->label('Test Import')
                    ->icon('heroicon-o-check-circle')
                    ->color('warning'),
            ])
            ->action(function (array $data, array $arguments) use ($importClass): void {
                $file = $data['file'] ?? null;
                $isTest = (bool) ($arguments['test'] ?? false);

                if (is_array($file)) {
                    $file = reset($file) ?: null;
                }

                if (blank($file)) {
                    Notification::make()
                        ->title('File belum selesai diupload')
                        ->body('Tunggu sampai status upload selesai, lalu klik Test Import atau Import Excel lagi.')
                        ->color('warning')
                        ->send();

                    return;
                }

                $path = is_object($file) && method_exists($file, 'getRealPath')
                    ? $file->getRealPath()
                    : Storage::disk('local')->path((string) $file);

                if (! is_string($path) || ! file_exists($path)) {
                    Notification::make()
                        ->title('File upload tidak ditemukan')
                        ->body('Upload ulang file Excelnya. Kalau masih muter di 100%, refresh halaman lalu coba lagi.')
                        ->color('danger')
                        ->send();

                    return;
                }

                $import = new $importClass();

                DB::beginTransaction();

                try {
                    Excel::import($import, $path);

                    if ($import->errorCount() > 0) {
                        DB::rollBack();

                        Notification::make()
                            ->title($isTest ? 'File belum aman diimport' : 'Import dibatalkan')
                            ->body(
                                "Tidak ada data yang diimpor.\n"
                                . "{$import->importedCount()} baris valid ditemukan, tapi ada {$import->errorCount()} error.\n"
                                . "Perbaiki file Excel dulu, lalu upload ulang.\n"
                                . implode("\n", $import->errorMessages())
                            )
                            ->color('danger')
                            ->send();

                        return;
                    }

                    if ($isTest) {
                        DB::rollBack();

                        Notification::make()
                            ->title('File aman untuk diimport')
                            ->body("Tidak ada data yang diimpor.\n{$import->importedCount()} baris valid. Silakan lanjut import kalau sudah yakin.")
                            ->color('success')
                            ->send();

                        return;
                    }

                    DB::commit();
                } catch (Throwable $exception) {
                    DB::rollBack();

                    Notification::make()
                        ->title($isTest ? 'Test import gagal' : 'Import gagal')
                        ->body("Tidak ada data yang diimpor.\n{$exception->getMessage()}")
                        ->color('danger')
                        ->send();

                    return;
                }

                Notification::make()
                    ->title('Import berhasil')
                    ->body($import->summary())
                    ->color('success')
                    ->send();
            });
    }

    protected function excelImportForm(): array
    {
        return [
            FileUpload::make('file')
                ->label('File Excel')
                ->disk('local')
                ->directory('imports')
                ->acceptedFileTypes([
                    'application/vnd.ms-excel',
                    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                ])
                ->maxSize(20480)
                ->helperText('Gunakan file .xlsx atau .xls dengan baris pertama sebagai nama kolom.')
                ->required(),
        ];
    }
}
