<?php

namespace App\Imports;

use App\Models\InventoryItem;
use App\Models\Purchase;
use Illuminate\Database\Eloquent\Model;

class PurchasesImport extends BaseExcelImport
{
    protected function makeModel(array $row): ?Model
    {
        $id = (int) $this->number($row, ['id', 'purchase_id', 'pembelian_id'], 0);
        $purchase = $id > 0 ? Purchase::find($id) : null;

        if ($id > 0 && ! $purchase) {
            throw new \InvalidArgumentException("purchase ID {$id} tidak ditemukan");
        }

        $purchaseMode = $this->normalizeLookup($this->text($row, ['jenis_pembelian', 'purchase_mode', 'jenis'], ''));
        $product = $this->value($row, ['produk', 'inventory_item', 'item', 'nama_produk']);
        $isInventoryPurchase = $product || str_contains($purchaseMode, 'inventory') || str_contains($purchaseMode, 'produk');

        if ($isInventoryPurchase) {
            $inventoryItemId = $this->resolveInventoryItemId($product);
            $inventoryItem = InventoryItem::find($inventoryItemId);
            $qty = $this->number($row, ['qty', 'quantity', 'jumlah', 'qty_item', 'jumlah_item'], 0);
            $unitCost = $this->number($row, ['harga_satuan', 'harga_per_satuan', 'modal_satuan', 'harga', 'modal'], (float) ($inventoryItem?->default_unit_cost ?? 0));

            return ($purchase ?? new Purchase())->fill([
                'purchase_mode' => 'inventory',
                'inventory_item_id' => $inventoryItemId,
                'supplier_code' => $this->text($row, ['supplier_code', 'kode_supplier', 'kode_spl', 'kode']),
                'date' => $this->date($row, ['date', 'tanggal', 'tgl', 'tanggal_pembelian', 'tgl_pembelian']),
                'supplier_name' => $this->text($row, ['supplier_name', 'supplier', 'nama_supplier', 'kebun']),
                'qty_butir' => 0,
                'qty_kg' => 0,
                'price_per_kg' => 0,
                'total_amount' => 0,
                'generic_qty' => $qty,
                'generic_unit' => $inventoryItem?->unit ?: $this->text($row, ['unit', 'uom', 'satuan']),
                'generic_unit_cost' => $unitCost,
                'generic_total_amount' => $qty * $unitCost,
                'notes' => $this->text($row, ['notes', 'catatan', 'keterangan']),
            ]);
        }

        $qtyKg = $this->kgNumber($row, ['qty_kg', 'kg', 'berat', 'berat_kg', 'jumlah_kg'], 0);
        $pricePerKg = $this->number($row, ['price_per_kg', 'harga_per_kg', 'harga_kg', 'harga', 'modal_per_kg'], 0);

        return ($purchase ?? new Purchase())->fill([
            'purchase_mode' => 'durian',
            'supplier_code' => $this->text($row, ['supplier_code', 'kode_supplier', 'kode_spl', 'kode']),
            'date' => $this->date($row, ['date', 'tanggal', 'tgl', 'tanggal_pembelian', 'tgl_pembelian']),
            'durian_variety_id' => $this->resolveDurianVarietyId($this->value($row, ['durian_variety_id', 'varian', 'variety', 'durian', 'jenis'])),
            'supplier_name' => $this->text($row, ['supplier_name', 'supplier', 'nama_supplier', 'kebun']),
            'qty_butir' => $this->integer($row, ['qty_butir', 'butir', 'jumlah_butir', 'btr'], 0),
            'qty_kg' => $qtyKg,
            'price_per_kg' => $pricePerKg,
            'total_amount' => $qtyKg * $pricePerKg,
            'notes' => $this->text($row, ['notes', 'catatan', 'keterangan']),
        ]);
    }
}
