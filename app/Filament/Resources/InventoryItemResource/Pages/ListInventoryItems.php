<?php

namespace App\Filament\Resources\InventoryItemResource\Pages;

use App\Filament\Resources\Concerns\HasExcelImportAction;
use App\Filament\Resources\InventoryItemResource;
use App\Imports\InventoryItemsImport;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListInventoryItems extends ListRecords
{
    use HasExcelImportAction;

    protected static string $resource = InventoryItemResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->excelTemplateAction(
                'template-master-produk.xlsx',
                ['nama_produk', 'sku', 'kategori', 'satuan', 'modal_default', 'varian_durian', 'aktif', 'catatan'],
                [
                    ['Thinwall 500ml', 'THIN-500', 'Packaging', 'pcs', 500, '', 'aktif', 'Kemasan daging take away'],
                    ['Pancake Durian', 'PANCAKE', 'Produk Olahan', 'pack', 8000, 'MONTHONG', 'aktif', 'Contoh produk olahan'],
                ],
            ),
            $this->excelImportAction(InventoryItemsImport::class),
            Actions\CreateAction::make(),
        ];
    }
}
