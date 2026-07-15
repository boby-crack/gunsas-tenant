<?php

namespace App\Filament\Resources\ShipmentResource\Pages;

use App\Filament\Resources\Concerns\HasExcelImportAction;
use App\Filament\Resources\ShipmentResource;
use App\Imports\ShipmentsImport;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListShipments extends ListRecords
{
    use HasExcelImportAction;

    protected static string $resource = ShipmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->excelTemplateAction(
                'template-shipments.xlsx',
                ['tanggal', 'outlet', 'arah_pengiriman', 'jenis_pengiriman', 'kategori_produk', 'varian', 'modal_per_kg', 'butir_kirim', 'butir_terima', 'berat_kirim_kg', 'berat_terima_kg', 'produk_inventory', 'qty_item_kirim', 'qty_item_terima', 'satuan', 'modal_satuan'],
                [
                    ['2026-07-03', 'TIPTOP RAWAMANGUN', 'gudang_ke_outlet', 'durian', 'buah_utuh', 'MONTHONG', 66000, 20, 20, 67.5, 67.5, '', '', '', '', ''],
                    ['2026-07-13', 'TIPTOP RAWAMANGUN', 'outlet_ke_gudang', 'durian', 'frozen', 'MONTHONG', '', '', '', 12.5, 12.5, '', '', '', '', ''],
                    ['2026-07-13', 'TIPTOP RAWAMANGUN', 'gudang_ke_outlet', 'inventory', '', '', '', '', '', '', '', 'Thinwall', 3, 3, 'pack', 25000],
                ],
            ),
            $this->excelImportAction(ShipmentsImport::class),
            Actions\CreateAction::make(),
        ];
    }
}
