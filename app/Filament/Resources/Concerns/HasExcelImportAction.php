<?php

namespace App\Filament\Resources\Concerns;

use App\Exports\ExcelTemplateExport;
use App\Exports\UpdateRecordsExport;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Notifications\Notification;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use Throwable;

trait HasExcelImportAction
{
    public ?array $excelImportTestResult = null;

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
            ->mountUsing(function ($form): void {
                $this->excelImportTestResult = null;
                $form?->fill();
            })
            ->form($this->excelImportForm())
            ->modalSubmitActionLabel('Import Excel')
            ->extraModalFooterActions(fn (Action $action): array => [
                $action->makeModalSubmitAction('testImport', arguments: ['test' => true])
                    ->label('Test Import')
                    ->icon('heroicon-o-check-circle')
                    ->color('warning'),
            ])
            ->action(function (array $data, array $arguments, Action $action) use ($importClass): void {
                $file = $data['file'] ?? null;
                $isTest = (bool) ($arguments['test'] ?? false);

                if (! $isTest) {
                    $this->excelImportTestResult = null;
                }

                if (is_array($file)) {
                    $file = reset($file) ?: null;
                }

                if (blank($file)) {
                    if ($isTest) {
                        $this->setExcelImportTestResult(
                            'warning',
                            'File belum selesai diupload',
                            ['Tunggu sampai status upload selesai, lalu klik Test Import lagi.'],
                        );

                        $action->halt();
                    }

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
                    if ($isTest) {
                        $this->setExcelImportTestResult(
                            'danger',
                            'File upload tidak ditemukan',
                            ['Upload ulang file Excelnya. Kalau masih muter di 100%, refresh halaman lalu coba lagi.'],
                        );

                        $action->halt();
                    }

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

                        if ($isTest) {
                            $this->setExcelImportTestResult(
                                'danger',
                                'File belum aman diimport',
                                [
                                    'Tidak ada data yang diimpor.',
                                    "{$import->importedCount()} baris valid ditemukan, tapi ada {$import->errorCount()} error.",
                                    'Perbaiki file Excel dulu, lalu upload ulang.',
                                    ...$import->errorMessages(),
                                ],
                            );

                            $action->halt();
                        }

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

                        $this->setExcelImportTestResult(
                            'success',
                            'File aman untuk diimport',
                            [
                                'Tidak ada data yang diimpor.',
                                "{$import->importedCount()} baris valid.",
                                'Kalau sudah yakin, klik Import Excel untuk memasukkan data.',
                            ],
                        );

                        $action->halt();
                    }

                    DB::commit();
                } catch (Throwable $exception) {
                    DB::rollBack();

                    if ($isTest) {
                        $this->setExcelImportTestResult(
                            'danger',
                            'Test import gagal',
                            [
                                'Tidak ada data yang diimpor.',
                                $exception->getMessage(),
                            ],
                        );

                        $action->halt();
                    }

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
            Placeholder::make('excel_import_test_result')
                ->label('Hasil Test Import')
                ->content(fn (): HtmlString => $this->excelImportTestResultHtml())
                ->visible(fn (): bool => filled($this->excelImportTestResult))
                ->columnSpanFull(),
        ];
    }

    protected function setExcelImportTestResult(string $status, string $title, array $lines = []): void
    {
        $this->excelImportTestResult = [
            'status' => $status,
            'title' => $title,
            'lines' => $lines,
        ];
    }

    protected function excelImportTestResultHtml(): HtmlString
    {
        if (blank($this->excelImportTestResult)) {
            return new HtmlString('');
        }

        $status = $this->excelImportTestResult['status'] ?? 'warning';
        $title = e($this->excelImportTestResult['title'] ?? 'Hasil test import');
        $lines = $this->excelImportTestResult['lines'] ?? [];

        $classes = match ($status) {
            'success' => 'border-green-500/40 bg-green-500/10 text-green-200',
            'danger' => 'border-red-500/40 bg-red-500/10 text-red-200',
            default => 'border-yellow-500/40 bg-yellow-500/10 text-yellow-200',
        };

        $body = collect($lines)
            ->filter(fn ($line): bool => filled($line))
            ->map(fn ($line): string => '<div>' . nl2br(e((string) $line)) . '</div>')
            ->implode('');

        return new HtmlString(
            '<div class="rounded-xl border p-4 text-sm ' . $classes . '">'
            . '<div class="font-semibold">' . $title . '</div>'
            . '<div class="mt-2 space-y-1 text-xs leading-5 opacity-90">' . $body . '</div>'
            . '</div>',
        );
    }
}
