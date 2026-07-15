<?php

namespace App\Filament\Resources\ExpenseResource\Pages;

use App\Filament\Resources\Concerns\HasExcelImportAction;
use App\Filament\Resources\ExpenseResource;
use App\Imports\ExpensesImport;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListExpenses extends ListRecords
{
    use HasExcelImportAction;

    protected static string $resource = ExpenseResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->excelTemplateAction(
                'template-expenses.xlsx',
                ['tanggal', 'outlet', 'kategori', 'nominal', 'catatan'],
                [['2026-07-03', 'TIPTOP RAWAMANGUN', 'Bensin & Tol', 150000, 'Contoh biaya operasional']],
            ),
            $this->excelImportAction(ExpensesImport::class),
            Actions\CreateAction::make(),
        ];
    }
}
