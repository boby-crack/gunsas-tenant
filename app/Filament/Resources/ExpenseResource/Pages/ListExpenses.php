<?php

namespace App\Filament\Resources\ExpenseResource\Pages;

use App\Filament\Resources\Concerns\HasExcelImportAction;
use App\Filament\Resources\Concerns\HasListSummaryHeader;
use App\Filament\Resources\ExpenseResource;
use App\Imports\ExpensesImport;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Tables\Table;

class ListExpenses extends ListRecords
{
    use HasExcelImportAction;
    use HasListSummaryHeader;

    protected static string $resource = ExpenseResource::class;

    public function table(Table $table): Table
    {
        return parent::table($table)
            ->header(fn () => $this->summaryHeader($this->getExpenseSummaryItems()));
    }

    protected function getExpenseSummaryItems(): array
    {
        $row = $this->filteredSummaryQuery()
            ->selectRaw("
                COUNT(*) as total_records,
                COALESCE(SUM(amount), 0) as total_amount,
                COALESCE(SUM(CASE WHEN outlet_id IS NULL THEN amount ELSE 0 END), 0) as pusat_amount,
                COALESCE(SUM(CASE WHEN outlet_id IS NOT NULL THEN amount ELSE 0 END), 0) as outlet_amount,
                COALESCE(SUM(CASE WHEN allocation_scope = 'group' THEN amount ELSE 0 END), 0) as group_amount
            ")
            ->first();

        return [
            ['label' => 'Total Expense', 'value' => $this->rupiah((float) ($row->total_amount ?? 0))],
            ['label' => 'Jumlah Data', 'value' => $this->whole((float) ($row->total_records ?? 0), 'baris')],
            ['label' => 'Pusat / Global', 'value' => $this->rupiah((float) ($row->pusat_amount ?? 0))],
            ['label' => 'Langsung Outlet', 'value' => $this->rupiah((float) ($row->outlet_amount ?? 0))],
            ['label' => 'Scope Grup', 'value' => $this->rupiah((float) ($row->group_amount ?? 0))],
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->excelUpdateExportAction(
                'update-expenses.xlsx',
                [
                    'id' => 'id',
                    'tanggal' => fn ($record) => $record->date instanceof \DateTimeInterface ? $record->date->format('Y-m-d') : $record->date,
                    'outlet' => fn ($record) => $record->outlet?->name ?? 'pusat',
                    'scope_alokasi' => 'allocation_scope',
                    'grup_alokasi' => 'allocation_group',
                    'kategori' => 'category',
                    'nominal' => 'amount',
                    'catatan' => 'notes',
                ],
                ['outlet'],
            ),
            $this->excelTemplateAction(
                'template-expenses.xlsx',
                ['tanggal', 'outlet', 'scope_alokasi', 'grup_alokasi', 'kategori', 'nominal', 'catatan'],
                [
                    ['2026-07-03', 'TIPTOP RAWAMANGUN', '', '', 'Parkir', 5000, 'Contoh beban langsung outlet'],
                    ['2026-07-03', 'pusat', '', '', 'Logistik / Kurir', 150000, 'Dibagi ke semua outlet aktif'],
                    ['2026-07-03', 'TOTAL BUAH BSD', '', '', 'Biaya Partner / Settlement', 12067, 'ISPO-TOTAL-GUNSA-9872-071726'],
                    ['2026-07-03', 'pusat', 'grup', 'tiptop', 'Gaji / Lemburan Staff', 1000000, 'Dibagi hanya ke grup TipTop'],
                    ['2026-07-03', 'pusat', 'pusat saja', '', 'Lain-lain', 50000, 'Dicatat pusat, tidak dibagi ke outlet'],
                ],
            ),
            $this->excelImportAction(ExpensesImport::class),
            Actions\CreateAction::make(),
        ];
    }
}
