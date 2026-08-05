<?php

namespace App\Filament\Resources\ProductReturnResource\Pages;

use App\Filament\Resources\Concerns\HasExcelImportAction;
use App\Filament\Resources\Concerns\HasListSummaryHeader;
use App\Filament\Resources\ProductReturnResource;
use App\Imports\ProductReturnsImport;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Tables\Table;

class ListProductReturns extends ListRecords
{
    use HasExcelImportAction;
    use HasListSummaryHeader;

    protected static string $resource = ProductReturnResource::class;

    public function table(Table $table): Table
    {
        return parent::table($table)
            ->header(fn () => $this->summaryHeader($this->getReturnSummaryItems()));
    }

    protected function getReturnSummaryItems(): array
    {
        $row = $this->filteredSummaryQuery()
            ->selectRaw("
                COUNT(*) as total_records,
                COALESCE(SUM(qty_butir), 0) as qty_butir,
                COALESCE(SUM(qty_kg), 0) as qty_kg,
                COALESCE(SUM(supplier_accepted_qty_butir), 0) as accepted_butir,
                COALESCE(SUM(supplier_accepted_qty_kg), 0) as accepted_kg,
                COALESCE(SUM(GREATEST(qty_kg - COALESCE(supplier_accepted_qty_kg, 0), 0)), 0) as rejected_kg,
                COALESCE(SUM(refund_amount), 0) as refund_amount
            ")
            ->first();

        return [
            ['label' => 'Jumlah Retur', 'value' => $this->whole((float) ($row->total_records ?? 0), 'retur')],
            ['label' => 'Total Diajukan', 'value' => $this->whole((float) ($row->qty_butir ?? 0), 'btr') . ' / ' . $this->kg((float) ($row->qty_kg ?? 0))],
            ['label' => 'Diterima Supplier', 'value' => $this->whole((float) ($row->accepted_butir ?? 0), 'btr') . ' / ' . $this->kg((float) ($row->accepted_kg ?? 0))],
            ['label' => 'Ditolak Supplier', 'value' => $this->kg((float) ($row->rejected_kg ?? 0))],
            ['label' => 'Refund', 'value' => $this->rupiah((float) ($row->refund_amount ?? 0))],
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->excelUpdateExportAction(
                'update-product-returns.xlsx',
                [
                    'id' => 'id',
                    'tanggal' => fn ($record) => $record->date instanceof \DateTimeInterface ? $record->date->format('Y-m-d') : $record->date,
                    'outlet' => fn ($record) => $record->outlet?->name,
                    'varian' => fn ($record) => $record->durianVariety?->name,
                    'kode_supplier' => 'supplier_code',
                    'warna_cat' => 'paint_color',
                    'butir' => 'qty_butir',
                    'berat_kg' => 'qty_kg',
                    'alasan' => 'detailed_reason',
                    'status' => 'status',
                    'diterima_supplier_butir' => 'supplier_accepted_qty_butir',
                    'diterima_supplier_kg' => 'supplier_accepted_qty_kg',
                    'refund' => 'refund_amount',
                    'catatan' => 'detailed_reason',
                ],
                ['outlet', 'durianVariety'],
            ),
            $this->excelTemplateAction(
                'template-product-returns.xlsx',
                [
                    'tanggal_buka',
                    'tanggal_datang',
                    'outlet',
                    'varian',
                    'kode_supplier',
                    'warna_cat',
                    'butir',
                    'berat_kg',
                    'alasan',
                    'status',
                    'diterima_supplier_butir',
                    'diterima_supplier_kg',
                    'refund',
                    'catatan',
                ],
                [
                    ['2026-07-09', '2026-07-07', 'TIPTOP TAMBUN', 'MONTHONG', 'BB', 'merah', 1, 2.104, 'kulit busuk/menghitam', 'no retur', '', '', 0, 'No retur = ditolak supplier'],
                    ['2026-07-09', '2026-07-07', 'TIPTOP RAWAMANGUN', 'MONTHONG', 'BB', 'merah', 1, 2.796, 'sebagian diterima supplier', 'diterima', 1, 1.398, 120000, 'Contoh retur diterima sebagian'],
                ],
            ),
            $this->excelImportAction(ProductReturnsImport::class),
            Actions\CreateAction::make(),
        ];
    }
}
