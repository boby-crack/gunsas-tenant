<?php

namespace App\Filament\Resources\StockOpnameResource\Pages;

use App\Filament\Resources\Concerns\HasExcelImportAction;
use App\Filament\Resources\Concerns\HasListSummaryHeader;
use App\Filament\Resources\StockOpnameResource;
use App\Imports\StockOpnamesImport;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Tables\Table;

class ListStockOpnames extends ListRecords
{
    use HasExcelImportAction;
    use HasListSummaryHeader;

    protected static string $resource = StockOpnameResource::class;

    public function table(Table $table): Table
    {
        return parent::table($table)
            ->header(fn () => $this->summaryHeader($this->getStockOpnameSummaryItems()));
    }

    protected function getStockOpnameSummaryItems(): array
    {
        $row = $this->filteredSummaryQuery()
            ->selectRaw("
                COUNT(*) as total_records,
                COALESCE(SUM(system_qty_kg), 0) as system_kg,
                COALESCE(SUM(physical_qty_kg), 0) as physical_kg,
                COALESCE(SUM(CASE WHEN difference_qty_kg < 0 THEN ABS(difference_qty_kg) ELSE 0 END), 0) as minus_kg,
                COALESCE(SUM(CASE WHEN difference_qty_kg > 0 THEN difference_qty_kg ELSE 0 END), 0) as plus_kg,
                COALESCE(SUM(generic_consumed_qty), 0) as consumed_qty,
                COALESCE(SUM(generic_consumed_amount), 0) as consumed_amount
            ")
            ->first();

        return [
            ['label' => 'Jumlah SO', 'value' => $this->whole((float) ($row->total_records ?? 0), 'data')],
            ['label' => 'Buku Sistem', 'value' => $this->kg((float) ($row->system_kg ?? 0))],
            ['label' => 'Fisik Toko', 'value' => $this->kg((float) ($row->physical_kg ?? 0))],
            ['label' => 'Selisih Minus', 'value' => $this->kg((float) ($row->minus_kg ?? 0))],
            ['label' => 'Selisih Plus', 'value' => $this->kg((float) ($row->plus_kg ?? 0)), 'description' => 'Inventory: ' . $this->rupiah((float) ($row->consumed_amount ?? 0))],
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->excelUpdateExportAction(
                'update-stock-opnames.xlsx',
                [
                    'id' => 'id',
                    'tanggal' => fn ($record) => $record->date instanceof \DateTimeInterface ? $record->date->format('Y-m-d') : $record->date,
                    'outlet' => fn ($record) => $record->outlet?->name,
                    'varian' => fn ($record) => $record->durianVariety?->name,
                    'produk' => fn ($record) => $record->inventoryItem?->name,
                    'kategori' => 'product_type',
                    'buku_kg' => 'system_qty_kg',
                    'fisik_kg' => 'physical_qty_kg',
                    'selisih_kg' => 'difference_qty_kg',
                    'item_terpakai' => 'generic_consumed_qty',
                    'satuan' => 'generic_unit',
                    'modal_satuan' => 'generic_unit_cost',
                    'catatan' => 'notes',
                ],
                ['outlet', 'durianVariety', 'inventoryItem'],
            ),
            $this->excelTemplateAction(
                'template-stock-opnames.xlsx',
                ['tanggal', 'outlet', 'varian', 'produk', 'kategori', 'buku_kg', 'fisik_kg', 'selisih_kg', 'item_terpakai', 'satuan', 'modal_satuan', 'catatan'],
                [
                    ['2026-07-21', 'TIPTOP RAWAMANGUN', 'MONTHONG', '', 'Buah Utuh', '', 118.2, '', '', '', '', 'buku_kg dan selisih_kg boleh kosong, sistem hitung otomatis'],
                    ['2026-07-21', 'TIPTOP RAWAMANGUN', 'MONTHONG', '', 'Daging Fresh', '', 10.75, '', '', '', '', 'Contoh SO kupas fresh'],
                    ['2026-07-21', 'TIPTOP RAWAMANGUN', '', 'Thinwall', 'Inventory Item', '', 72, '', '', 'pcs', '', 'item_terpakai dan modal_satuan boleh kosong, sistem hitung otomatis'],
                    ['2026-07-21', 'TIPTOP RAWAMANGUN', '', 'Stiker Batang', 'Inventory Item', '', 150, '', '', 'pcs', '', 'Contoh SO item inventory'],
                ],
            ),
            $this->excelImportAction(StockOpnamesImport::class),
            Actions\CreateAction::make(),
        ];
    }
}
