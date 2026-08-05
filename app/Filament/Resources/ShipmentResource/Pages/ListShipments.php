<?php

namespace App\Filament\Resources\ShipmentResource\Pages;

use App\Filament\Resources\Concerns\HasExcelImportAction;
use App\Filament\Resources\Concerns\HasListSummaryHeader;
use App\Filament\Resources\ShipmentResource;
use App\Imports\ShipmentsImport;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Tables\Table;

class ListShipments extends ListRecords
{
    use HasExcelImportAction;
    use HasListSummaryHeader;

    protected static string $resource = ShipmentResource::class;

    public function table(Table $table): Table
    {
        return parent::table($table)
            ->header(fn () => view('filament.resources.shipment-resource.shipment-summary', [
                'summary' => $this->getShipmentSummary(),
            ]));
    }

    public function getShipmentSummary(): array
    {
        $row = $this->filteredSummaryQuery()
            ->selectRaw("
                COALESCE(SUM(CASE WHEN COALESCE(shipment_mode, 'durian') <> 'inventory' AND COALESCE(product_type, 'Buah Utuh') = 'Buah Utuh' THEN qty_sent_butir ELSE 0 END), 0) as sent_butir,
                COALESCE(SUM(CASE WHEN COALESCE(shipment_mode, 'durian') <> 'inventory' AND COALESCE(product_type, 'Buah Utuh') = 'Buah Utuh' THEN qty_received_butir ELSE 0 END), 0) as received_butir,
                COALESCE(SUM(CASE WHEN COALESCE(shipment_mode, 'durian') <> 'inventory' THEN qty_sent_kg ELSE 0 END), 0) as sent_kg,
                COALESCE(SUM(CASE WHEN COALESCE(shipment_mode, 'durian') <> 'inventory' THEN CASE WHEN COALESCE(qty_received_kg, 0) > 0 THEN qty_received_kg ELSE qty_sent_kg END ELSE 0 END), 0) as received_kg,
                COALESCE(SUM(CASE WHEN shipment_mode = 'inventory' THEN generic_qty_sent ELSE 0 END), 0) as item_sent,
                COALESCE(SUM(CASE WHEN shipment_mode = 'inventory' THEN generic_qty_received ELSE 0 END), 0) as item_received,
                COALESCE(SUM(CASE WHEN shipment_mode = 'inventory' THEN generic_total_amount ELSE value_purchase END), 0) as total_modal
            ")
            ->first();

        $sentButir = (float) ($row->sent_butir ?? 0);
        $sentKg = (float) ($row->sent_kg ?? 0);

        return [
            'sent_butir' => $sentButir,
            'received_butir' => (float) ($row->received_butir ?? 0),
            'sent_kg' => $sentKg,
            'received_kg' => (float) ($row->received_kg ?? 0),
            'item_sent' => (float) ($row->item_sent ?? 0),
            'item_received' => (float) ($row->item_received ?? 0),
            'avg_weight' => $sentButir > 0 ? $sentKg / $sentButir : 0,
            'total_modal' => (float) ($row->total_modal ?? 0),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->excelUpdateExportAction(
                'update-shipments.xlsx',
                [
                    'id' => 'id',
                    'tanggal' => fn ($record) => $record->date instanceof \DateTimeInterface ? $record->date->format('Y-m-d') : $record->date,
                    'outlet' => fn ($record) => $record->outlet?->name,
                    'arah_pengiriman' => 'shipment_direction',
                    'jenis_pengiriman' => 'shipment_mode',
                    'kategori_produk' => 'product_type',
                    'varian' => fn ($record) => $record->durianVariety?->name,
                    'modal_per_kg' => 'modal_price',
                    'butir_kirim' => 'qty_sent_butir',
                    'butir_terima' => 'qty_received_butir',
                    'berat_kirim_kg' => 'qty_sent_kg',
                    'berat_terima_kg' => 'qty_received_kg',
                    'produk_inventory' => fn ($record) => $record->inventoryItem?->name,
                    'qty_item_kirim' => 'generic_qty_sent',
                    'qty_item_terima' => 'generic_qty_received',
                    'satuan' => 'generic_unit',
                    'modal_satuan' => 'generic_unit_cost',
                ],
                ['outlet', 'durianVariety', 'inventoryItem'],
            ),
            $this->excelTemplateAction(
                'template-shipments.xlsx',
                ['tanggal', 'outlet', 'arah_pengiriman', 'jenis_pengiriman', 'kategori_produk', 'varian', 'modal_per_kg', 'butir_kirim', 'butir_terima', 'berat_kirim_kg', 'berat_terima_kg', 'produk_inventory', 'qty_item_kirim', 'qty_item_terima'],
                [
                    ['2026-07-03', 'TIPTOP RAWAMANGUN', 'gudang_ke_outlet', 'durian', 'buah_utuh', 'MONTHONG', 66000, 20, 20, 67.5, 67.5, '', '', ''],
                    ['2026-07-13', 'TIPTOP RAWAMANGUN', 'outlet_ke_gudang', 'durian', 'frozen', 'MONTHONG', '', '', '', 12.5, 12.5, '', '', ''],
                    ['2026-07-13', 'TOTAL BUAH BSD', 'gudang_ke_outlet', 'inventory', '', '', '', '', '', '', '', 'Pancake Durian', 10, 10],
                ],
            ),
            $this->excelImportAction(ShipmentsImport::class),
            Actions\CreateAction::make(),
        ];
    }
}
