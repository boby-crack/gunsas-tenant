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
            $this->excelUpdateExportAction(
                'update-master-produk.xlsx',
                [
                    'id' => 'id',
                    'nama_produk' => 'name',
                    'sku' => 'sku',
                    'kategori' => 'category',
                    'satuan' => 'unit',
                    'modal_default' => 'default_unit_cost',
                    'varian_durian' => fn ($record) => $record->durianVariety?->name,
                    'aktif' => fn ($record) => $record->is_active ? 'aktif' : 'nonaktif',
                    'produk_dijual' => fn ($record) => $record->is_sellable ? 'ya' : 'tidak',
                    'catatan' => 'notes',
                ],
                ['durianVariety'],
            ),
            $this->excelTemplateAction(
                'template-master-produk.xlsx',
                ['nama_produk', 'sku', 'kategori', 'satuan', 'modal_default', 'varian_durian', 'aktif', 'produk_dijual', 'catatan'],
                [
                    ['Thinwall 500ml', 'THIN-500', 'Packaging', 'pcs', 500, '', 'aktif', 'tidak', 'Kemasan daging take away'],
                    ['Pancake Durian', 'PANCAKE', 'Produk Jualan Non-Durian', 'pack', 8000, '', 'aktif', 'ya', 'Contoh produk jualan lain'],
                ],
            ),
            $this->excelImportAction(InventoryItemsImport::class),
            Actions\CreateAction::make(),
        ];
    }
}
