<?php

namespace App\Imports;

use App\Models\Production;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ProductionsImport extends BaseExcelImport
{
    protected function makeModel(array $row): ?Model
    {
        $id = (int) $this->number($row, ['id', 'production_id', 'produksi_id'], 0);
        $production = $id > 0 ? Production::find($id) : null;

        if ($id > 0 && ! $production) {
            throw new \InvalidArgumentException("production ID {$id} tidak ditemukan");
        }

        $qtyBuahKg = $this->kgNumber($row, ['qty_buah_kg', 'buah_kg', 'berat_buah_kg', 'berat_awal', 'berat_awal_kg'], 0);
        $qtyKupasKg = $this->kgNumber($row, ['qty_kupas_kg', 'kupas_kg', 'fresh_kg', 'daging_fresh_kg', 'berat_fresh_kg'], 0);
        $qtyOlahanKg = $this->kgNumber($row, ['qty_olahan_kg', 'olahan_kg', 'reject_kg', 'daging_olahan_kg'], 0);
        $totalUsableMeatKg = $qtyKupasKg + $qtyOlahanKg;
        $sourceType = $this->productionSourceType($this->text($row, ['source_type', 'sumber', 'sumber_buah', 'source'], Production::SOURCE_NORMAL));

        return ($production ?? new Production())->fill([
            'outlet_id' => $this->resolveOutletId($this->value($row, ['outlet_id', 'outlet', 'nama_outlet', 'cabang'])),
            'durian_variety_id' => $this->resolveDurianVarietyId($this->value($row, ['durian_variety_id', 'varian', 'variety', 'durian', 'jenis'])),
            'date' => $this->date($row, ['date', 'tanggal', 'tgl', 'tanggal_produksi', 'tgl_produksi']),
            'source_type' => $sourceType,
            'qty_buah_butir' => $this->integer($row, ['qty_buah_butir', 'buah_butir', 'butir', 'btr'], 0),
            'qty_buah_kg' => $qtyBuahKg,
            'qty_kupas_pack' => $this->integer($row, ['qty_kupas_pack', 'fresh_pack', 'pack_fresh', 'jumlah_pack_fresh'], 0),
            'qty_kupas_kg' => $qtyKupasKg,
            'qty_olahan_pack' => $this->integer($row, ['qty_olahan_pack', 'olahan_pack', 'reject_pack', 'pack_olahan'], 0),
            'qty_olahan_kg' => $qtyOlahanKg,
            'total_usable_meat_kg' => $totalUsableMeatKg,
            'shrinkage_percentage' => $qtyBuahKg > 0 ? round((($qtyBuahKg - $totalUsableMeatKg) / $qtyBuahKg) * 100, 2) : 0,
            'multiplier_factor' => $totalUsableMeatKg > 0 ? round($qtyBuahKg / $totalUsableMeatKg, 2) : 0,
        ]);
    }

    private function productionSourceType(?string $value): string
    {
        $normalized = Str::of((string) $value)->lower()->replace([' ', '-', '_'], '')->toString();

        return str_contains($normalized, 'retur') || str_contains($normalized, 'return')
            ? Production::SOURCE_RETURN
            : Production::SOURCE_NORMAL;
    }
}
