<?php

namespace App\Imports;

use App\Models\Shipment;
use Illuminate\Database\Eloquent\Model;

class ShipmentsImport extends BaseExcelImport
{
    protected function makeModel(array $row): ?Model
    {
        $shipmentMode = $this->normalizeShipmentMode($this->text($row, ['shipment_mode', 'jenis_pengiriman', 'jenis'], 'durian'));
        $shipmentDirection = $this->normalizeShipmentDirection($this->text($row, ['shipment_direction', 'arah', 'arah_pengiriman'], 'warehouse_to_outlet'));
        $productType = $this->normalizeDurianProductType($this->text($row, ['product_type', 'kategori_produk', 'kategori', 'produk'], 'Buah Utuh'));
        $sentButir = $this->integer($row, ['qty_sent_butir', 'butir_kirim', 'kirim_butir', 'qty_kirim'], 0);
        $receivedButir = $this->integer($row, ['qty_received_butir', 'butir_terima', 'terima_butir', 'qty_terima'], $sentButir);
        $sentKg = $this->kgNumber($row, ['qty_sent_kg', 'kg_kirim', 'berat_kirim', 'berat_kirim_kg', 'berat_kg', 'kg'], 0);
        $receivedKg = $this->kgNumber($row, ['qty_received_kg', 'kg_terima', 'berat_terima', 'berat_terima_kg'], $sentKg);
        $modalPrice = $this->number($row, ['modal_price', 'modal', 'modal_per_kg', 'harga_modal', 'harga_modal_per_kg'], 0);
        $inventoryItemId = $shipmentMode === 'inventory'
            ? $this->resolveInventoryItemId($this->value($row, ['inventory_item_id', 'inventory_item', 'item', 'produk_inventory', 'produk']))
            : null;
        $inventoryQtySent = $this->number($row, ['generic_qty_sent', 'item_kirim', 'qty_item_kirim', 'qty_kirim'], 0);
        $inventoryQtyReceived = $this->number($row, ['generic_qty_received', 'item_terima', 'qty_item_terima', 'qty_terima'], $inventoryQtySent);
        $inventoryUnitCost = $this->number($row, ['generic_unit_cost', 'modal_satuan', 'harga_satuan'], 0);

        return new Shipment([
            'outlet_id' => $this->resolveOutletId($this->value($row, ['outlet_id', 'outlet', 'nama_outlet', 'cabang'])),
            'inventory_item_id' => $inventoryItemId,
            'shipment_mode' => $shipmentMode,
            'shipment_direction' => $shipmentDirection,
            'product_type' => $productType,
            'durian_variety_id' => $shipmentMode === 'durian' ? $this->resolveDurianVarietyId($this->value($row, ['durian_variety_id', 'varian', 'variety', 'durian', 'jenis'])) : null,
            'date' => $this->date($row, ['date', 'tanggal', 'tgl', 'tanggal_kirim', 'tgl_kirim']),
            'modal_price' => $modalPrice,
            'qty_sent_butir' => $sentButir,
            'qty_received_butir' => $receivedButir,
            'qty_sent_kg' => $sentKg,
            'qty_received_kg' => $receivedKg,
            'generic_qty_sent' => $inventoryQtySent,
            'generic_qty_received' => $inventoryQtyReceived,
            'generic_unit' => $this->text($row, ['generic_unit', 'satuan', 'unit']),
            'generic_unit_cost' => $inventoryUnitCost,
            'generic_total_amount' => round($inventoryQtyReceived * $inventoryUnitCost, 2),
            'average_weight' => $productType === 'Buah Utuh' && $sentButir > 0 ? round($sentKg / $sentButir, 3) : 0,
            'value_purchase' => $productType === 'Buah Utuh' && $shipmentDirection === 'warehouse_to_outlet' ? round($sentKg * $modalPrice, 2) : 0,
        ]);
    }

    private function normalizeShipmentMode(?string $value): string
    {
        $normalized = $this->normalizeLookup($value ?? '');

        return str_contains($normalized, 'inventory') || str_contains($normalized, 'item')
            ? 'inventory'
            : 'durian';
    }

    private function normalizeShipmentDirection(?string $value): string
    {
        $normalized = $this->normalizeLookup($value ?? '');

        return str_contains($normalized, 'outletgudang')
            || str_contains($normalized, 'outletkegudang')
            || str_contains($normalized, 'outlettogudang')
            || str_contains($normalized, 'tokokegudang')
            || str_contains($normalized, 'tokogudang')
            || str_contains($normalized, 'balik')
            || str_contains($normalized, 'tarik')
            || (str_contains($normalized, 'outlet') && str_contains($normalized, 'pusat'))
            ? 'outlet_to_warehouse'
            : 'warehouse_to_outlet';
    }

    private function normalizeDurianProductType(?string $value): string
    {
        $normalized = $this->normalizeLookup($value ?? '');

        return match (true) {
            str_contains($normalized, 'fresh') => 'Daging Fresh',
            str_contains($normalized, 'frozen'), str_contains($normalized, 'durpas') => 'Daging Frozen',
            default => 'Buah Utuh',
        };
    }
}
