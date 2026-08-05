<?php

namespace App\Imports;

use App\Models\InventoryItem;
use App\Models\StockOpname;
use App\Services\InventoryItemStockCalculator;
use App\Services\StockSnapshotCalculator;
use Illuminate\Database\Eloquent\Model;

class StockOpnamesImport extends BaseExcelImport
{
    protected function makeModel(array $row): ?Model
    {
        $id = (int) $this->number($row, ['id', 'stock_opname_id', 'opname_id'], 0);
        $opname = $id > 0 ? StockOpname::find($id) : null;

        if ($id > 0 && ! $opname) {
            throw new \InvalidArgumentException("stock opname ID {$id} tidak ditemukan");
        }

        $productType = $this->normalizeProductType($this->text($row, ['product_type', 'kategori', 'kategori_produk', 'jenis'], $opname?->product_type ?? 'Buah Utuh'));
        $inventoryItemValue = $this->value($row, ['produk', 'inventory_item', 'inventory_item_id', 'nama_produk']);
        $isInventory = $productType === 'Inventory Item' || filled($inventoryItemValue);
        $outletId = $this->resolveOutletId($this->value($row, ['outlet_id', 'outlet', 'nama_outlet', 'cabang']));
        $date = $this->date($row, ['date', 'tanggal', 'tgl', 'tanggal_cek', 'tanggal_cek_fisik']);
        $inventoryItemId = $isInventory ? $this->resolveInventoryItemId($inventoryItemValue) : null;
        $durianVarietyId = $isInventory ? null : $this->resolveDurianVarietyId($this->value($row, ['durian_variety_id', 'varian', 'variety', 'durian', 'jenis_durian']));

        $systemQty = $this->kgNumber($row, ['system_qty_kg', 'buku_kg', 'stok_buku', 'buku'], null);
        $physicalQty = $this->kgNumber($row, ['physical_qty_kg', 'fisik_kg', 'fisik_toko', 'fisik'], $opname?->physical_qty_kg ?? 0);
        $differenceQty = $this->kgNumber($row, ['difference_qty_kg', 'selisih_kg', 'selisih'], null);
        $inventoryItem = $inventoryItemId ? InventoryItem::find($inventoryItemId) : null;
        $unitCost = $this->number($row, ['generic_unit_cost', 'modal_satuan', 'harga_satuan'], null);

        if ($systemQty === null) {
            $systemQty = $isInventory
                ? app(InventoryItemStockCalculator::class)->systemQty((int) $inventoryItemId, $outletId, $date)
                : app(StockSnapshotCalculator::class)->durianStockForOpnameDate($date, $outletId, (int) $durianVarietyId, $productType);
        }

        if ($unitCost === null && $isInventory) {
            $unitCost = app(InventoryItemStockCalculator::class)->averageUnitCost((int) $inventoryItemId)
                ?: (float) ($inventoryItem?->default_unit_cost ?? 0);
        }

        $unitCost ??= $opname?->generic_unit_cost ?? 0;
        $consumedQty = $this->number($row, ['generic_consumed_qty', 'item_terpakai', 'terpakai'], null);

        if ($consumedQty === null && $isInventory) {
            $consumedQty = max(0, (float) $systemQty - (float) $physicalQty);
        }

        $consumedQty ??= $opname?->generic_consumed_qty ?? 0;

        return ($opname ?? new StockOpname())->fill([
            'outlet_id' => $outletId,
            'inventory_item_id' => $inventoryItemId,
            'durian_variety_id' => $durianVarietyId,
            'date' => $date,
            'product_type' => $isInventory ? 'Inventory Item' : $productType,
            'system_qty_kg' => $systemQty,
            'physical_qty_kg' => $physicalQty,
            'difference_qty_kg' => $differenceQty ?? ($physicalQty - $systemQty),
            'generic_unit' => $this->text($row, ['generic_unit', 'satuan', 'unit'], $opname?->generic_unit ?? $inventoryItem?->unit),
            'generic_consumed_qty' => $consumedQty,
            'generic_unit_cost' => $unitCost,
            'generic_consumed_amount' => $consumedQty * $unitCost,
            'notes' => $this->text($row, ['notes', 'catatan', 'keterangan'], $opname?->notes),
        ]);
    }

    private function normalizeProductType(?string $value): string
    {
        $normalized = $this->normalizeLookup($value ?? '');

        return match (true) {
            str_contains($normalized, 'inventory'), str_contains($normalized, 'item') => 'Inventory Item',
            str_contains($normalized, 'fresh') => 'Daging Fresh',
            str_contains($normalized, 'frozen'), str_contains($normalized, 'durpas') => 'Daging Frozen',
            default => 'Buah Utuh',
        };
    }
}
