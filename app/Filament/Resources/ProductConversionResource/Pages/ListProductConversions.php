<?php

namespace App\Filament\Resources\ProductConversionResource\Pages;

use App\Filament\Resources\Concerns\HasExcelImportAction;
use App\Filament\Resources\Concerns\HasListSummaryHeader;
use App\Filament\Resources\ProductConversionResource;
use App\Imports\ProductConversionsImport;
use App\Models\ProductConversion;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Tables\Table;

class ListProductConversions extends ListRecords
{
    use HasExcelImportAction;
    use HasListSummaryHeader;

    protected static string $resource = ProductConversionResource::class;

    public function table(Table $table): Table
    {
        return parent::table($table)
            ->header(fn () => $this->summaryHeader($this->getConversionSummaryItems()));
    }

    protected function getConversionSummaryItems(): array
    {
        $row = $this->filteredSummaryQuery()
            ->selectRaw("
                COUNT(*) as total_records,
                COALESCE(SUM(from_qty_pack), 0) as from_pack,
                COALESCE(SUM(from_qty_kg), 0) as from_kg,
                COALESCE(SUM(to_qty_pack), 0) as to_pack,
                COALESCE(SUM(to_qty_kg), 0) as to_kg,
                COALESCE(SUM(CASE WHEN conversion_type = ? THEN from_qty_kg ELSE 0 END), 0) as loss_kg
            ", [ProductConversion::TYPE_FRESH_LOSS])
            ->first();

        $fromKg = (float) ($row->from_kg ?? 0);
        $toKg = (float) ($row->to_kg ?? 0);
        $lossKg = (float) ($row->loss_kg ?? 0);

        return [
            ['label' => 'Jumlah Konversi', 'value' => $this->whole((float) ($row->total_records ?? 0), 'data')],
            ['label' => 'Fresh Diproses', 'value' => $this->kg($fromKg), 'description' => $this->qty((float) ($row->from_pack ?? 0), 'pack')],
            ['label' => 'Frozen Jadi', 'value' => $this->kg($toKg), 'description' => $this->qty((float) ($row->to_pack ?? 0), 'pack')],
            ['label' => 'Fresh Loss / Olahan', 'value' => $this->kg($lossKg)],
            ['label' => 'Selisih Proses', 'value' => $this->kg($fromKg - $toKg)],
            ['label' => 'Yield Frozen', 'value' => $fromKg > 0 ? $this->percent(($toKg / $fromKg) * 100) : $this->percent(0)],
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->excelUpdateExportAction(
                'update-product-conversions.xlsx',
                [
                    'id' => 'id',
                    'tanggal' => fn ($record) => $record->date instanceof \DateTimeInterface ? $record->date->format('Y-m-d') : $record->date,
                    'outlet' => fn ($record) => $record->outlet?->name,
                    'varian' => fn ($record) => $record->durianVariety?->name,
                    'tipe_konversi' => 'conversion_type',
                    'fresh_kg' => 'from_qty_kg',
                    'fresh_pack' => 'from_qty_pack',
                    'frozen_kg' => 'to_qty_kg',
                    'frozen_pack' => 'to_qty_pack',
                    'catatan' => 'notes',
                ],
                ['outlet', 'durianVariety'],
            ),
            $this->excelTemplateAction(
                'template-product-conversions.xlsx',
                [
                    'tanggal',
                    'outlet',
                    'varian',
                    'tipe_konversi',
                    'fresh_kg',
                    'fresh_pack',
                    'frozen_kg',
                    'frozen_pack',
                    'catatan',
                ],
                [
                    ['2026-07-03', 'TIPTOP RAWAMANGUN', 'MONTHONG', ProductConversion::TYPE_FRESH_TO_FROZEN, 3.05, 0, 1.172, 3, 'Contoh durpas'],
                    ['2026-07-04', 'TIPTOP RAWAMANGUN', 'MONTHONG', ProductConversion::TYPE_FRESH_LOSS, 1.25, 0, 0, 0, 'Telat frozen, jadi olahan/busuk'],
                ],
            ),
            $this->excelImportAction(ProductConversionsImport::class),
            Actions\CreateAction::make(),
        ];
    }
}
