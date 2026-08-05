<?php

namespace App\Imports;

use App\Models\DurianVariety;
use App\Models\InventoryItem;
use Illuminate\Database\Eloquent\Model;

class InventoryItemsImport extends BaseExcelImport
{
    protected function makeModel(array $row): ?Model
    {
        $id = (int) $this->number($row, ['id', 'inventory_item_id', 'produk_id'], 0);
        $existingById = $id > 0 ? InventoryItem::find($id) : null;

        if ($id > 0 && ! $existingById) {
            throw new \InvalidArgumentException("produk ID {$id} tidak ditemukan");
        }

        $name = $this->text($row, ['name', 'nama_produk', 'produk', 'item']);

        if (! $name) {
            throw new \InvalidArgumentException('nama produk wajib diisi');
        }

        $sku = $this->text($row, ['sku', 'kode', 'kode_produk']);
        $category = $this->normalizeCategory($this->text($row, ['category', 'kategori', 'jenis'], 'lainnya'));
        $unit = $this->normalizeUnit($this->text($row, ['unit', 'uom', 'satuan'], 'pcs'));
        $varietyId = $this->resolveOptionalVariety($this->value($row, ['varian', 'varian_durian', 'durian_variety']));

        $attributes = [
            'name' => $name,
            'sku' => $sku,
            'category' => $category,
            'unit' => $unit,
            'durian_variety_id' => $varietyId,
            'default_unit_cost' => $this->number($row, ['default_unit_cost', 'harga_default', 'modal_default', 'harga_modal', 'modal'], 0),
            'is_active' => $this->normalizeBoolean($this->text($row, ['is_active', 'aktif', 'status'], 'aktif')),
            'is_sellable' => $this->normalizeBoolean($this->text($row, ['is_sellable', 'produk_dijual', 'dijual', 'sellable'], 'tidak')),
            'notes' => $this->text($row, ['notes', 'catatan', 'keterangan']),
        ];

        $item = $existingById ?? InventoryItem::query()
            ->when($sku, fn ($query) => $query->where('sku', $sku))
            ->when(! $sku, fn ($query) => $query->where('name', $name))
            ->first();

        if ($item) {
            $item->fill($attributes)->save();
            $this->imported++;

            return null;
        }

        return new InventoryItem($attributes);
    }

    private function normalizeCategory(?string $category): string
    {
        $category = $this->normalizeLookup($category ?? '');

        return match (true) {
            str_contains($category, 'buah') => 'buah_utuh',
            str_contains($category, 'fresh'), str_contains($category, 'kupas') => 'kupas_fresh',
            str_contains($category, 'frozen'), str_contains($category, 'durpas') => 'durpas_frozen',
            str_contains($category, 'nondurian'),
            str_contains($category, 'produknondurian'),
            str_contains($category, 'produkjualan'),
            str_contains($category, 'produkjual'),
            str_contains($category, 'jualanlain'),
            str_contains($category, 'penjualannondurian'),
            str_contains($category, 'kategoripenjualan'),
            str_contains($category, 'pancake'),
            str_contains($category, 'brulee'),
            str_contains($category, 'brule') => 'produk_jualan',
            str_contains($category, 'olahan') => 'produk_olahan',
            str_contains($category, 'pack'), str_contains($category, 'thinwall'), str_contains($category, 'kemasan') => 'packaging',
            str_contains($category, 'stiker'), str_contains($category, 'label') => 'stiker',
            str_contains($category, 'bahan') => 'bahan_baku',
            str_contains($category, 'operasional') => 'operasional',
            default => 'lainnya',
        };
    }

    private function normalizeUnit(?string $unit): string
    {
        $unit = $this->normalizeLookup($unit ?? '');

        return match ($unit) {
            'kg', 'kilogram' => 'kg',
            'unit' => 'unit',
            'pcs', 'pc', 'piece', 'pieces' => 'pcs',
            'pack' => 'pack',
            'box' => 'box',
            'roll' => 'roll',
            'lembar' => 'lembar',
            'botol' => 'botol',
            'liter', 'ltr', 'l' => 'liter',
            'gram', 'gr' => 'gram',
            'dus' => 'dus',
            'karung' => 'karung',
            default => 'pcs',
        };
    }

    private function resolveOptionalVariety(mixed $value): ?int
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }

        if (is_numeric($value) && DurianVariety::whereKey((int) $value)->exists()) {
            return (int) $value;
        }

        return $this->resolveDurianVarietyId($value, false);
    }

    private function normalizeBoolean(?string $value): bool
    {
        $value = $this->normalizeLookup($value ?? 'aktif');

        return ! in_array($value, ['0', 'false', 'nonaktif', 'inactive', 'tidak'], true);
    }
}
