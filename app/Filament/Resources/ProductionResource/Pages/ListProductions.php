<?php

namespace App\Filament\Resources\ProductionResource\Pages;

use App\Filament\Resources\Concerns\HasExcelImportAction;
use App\Filament\Resources\Concerns\HasListSummaryHeader;
use App\Filament\Resources\ProductionResource;
use App\Imports\ProductionsImport;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Tables\Table;

class ListProductions extends ListRecords
{
    use HasExcelImportAction;
    use HasListSummaryHeader;

    protected static string $resource = ProductionResource::class;

    public function table(Table $table): Table
    {
        return parent::table($table)
            ->header(fn () => $this->summaryHeader($this->getProductionSummaryItems()));
    }

    protected function getProductionSummaryItems(): array
    {
        $row = $this->filteredSummaryQuery()
            ->selectRaw("
                COUNT(*) as total_records,
                COALESCE(SUM(qty_buah_butir), 0) as buah_butir,
                COALESCE(SUM(qty_buah_kg), 0) as buah_kg,
                COALESCE(SUM(qty_kupas_pack), 0) as fresh_pack,
                COALESCE(SUM(qty_kupas_kg), 0) as fresh_kg,
                COALESCE(SUM(qty_olahan_kg), 0) as olahan_kg,
                COALESCE(SUM(total_usable_meat_kg), 0) as usable_kg
            ")
            ->first();

        $buahKg = (float) ($row->buah_kg ?? 0);
        $usableKg = (float) ($row->usable_kg ?? 0);

        return [
            ['label' => 'Batch Produksi', 'value' => $this->whole((float) ($row->total_records ?? 0), 'batch')],
            ['label' => 'Buah Diproses', 'value' => $this->whole((float) ($row->buah_butir ?? 0), 'btr') . ' / ' . $this->kg($buahKg)],
            ['label' => 'Fresh Jadi', 'value' => $this->kg((float) ($row->fresh_kg ?? 0)), 'description' => $this->qty((float) ($row->fresh_pack ?? 0), 'pack')],
            ['label' => 'Olahan / Reject', 'value' => $this->kg((float) ($row->olahan_kg ?? 0))],
            ['label' => 'Yield Daging', 'value' => $buahKg > 0 ? $this->percent(($usableKg / $buahKg) * 100) : $this->percent(0)],
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->excelUpdateExportAction(
                'update-productions.xlsx',
                [
                    'id' => 'id',
                    'tanggal' => fn ($record) => $record->date instanceof \DateTimeInterface ? $record->date->format('Y-m-d') : $record->date,
                    'outlet' => fn ($record) => $record->outlet?->name,
                    'varian' => fn ($record) => $record->durianVariety?->name,
                    'sumber' => 'source_type',
                    'buah_butir' => 'qty_buah_butir',
                    'buah_kg' => 'qty_buah_kg',
                    'fresh_kg' => 'qty_kupas_kg',
                    'fresh_pack' => 'qty_kupas_pack',
                    'olahan_kg' => 'qty_olahan_kg',
                    'olahan_pack' => 'qty_olahan_pack',
                ],
                ['outlet', 'durianVariety'],
            ),
            $this->excelTemplateAction(
                'template-productions.xlsx',
                [
                    'tanggal',
                    'outlet',
                    'varian',
                    'sumber',
                    'buah_butir',
                    'buah_kg',
                    'fresh_kg',
                    'fresh_pack',
                    'olahan_kg',
                    'olahan_pack',
                ],
                [['2026-07-03', 'TIPTOP RAWAMANGUN', 'MONTHONG', 'normal', 10, 30.5, 11.72, 3, 0.198, 1]],
            ),
            $this->excelImportAction(ProductionsImport::class),
            Actions\CreateAction::make(),
        ];
    }
}
