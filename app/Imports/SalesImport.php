<?php

namespace App\Imports;

use App\Models\Sale;
use Illuminate\Database\Eloquent\Model;

class SalesImport extends BaseExcelImport
{
    protected function makeModel(array $row): ?Model
    {
        $buahKg = $this->number($row, ['buah_sold_kg', 'buah_kg', 'buah_utuh_kg', 'buah_terjual_kg'], 0);
        $buahPrice = $this->number($row, ['buah_price_per_kg', 'harga_buah', 'harga_buah_kg'], 0);
        $freshKg = $this->number($row, ['fresh_sold_kg', 'fresh_kg', 'kupas_fresh_kg', 'daging_fresh_kg'], 0);
        $freshPrice = $this->number($row, ['fresh_price_per_kg', 'harga_fresh', 'harga_fresh_kg'], 0);
        $frozenKg = $this->number($row, ['frozen_sold_kg', 'frozen_kg', 'durpas_frozen_kg', 'durpas_kg'], 0);
        $frozenPrice = $this->number($row, ['frozen_price_per_kg', 'harga_frozen', 'harga_durpas'], 0);

        $buahSubtotal = $this->number($row, ['buah_subtotal', 'subtotal_buah'], $buahKg * $buahPrice);
        $freshSubtotal = $this->number($row, ['fresh_subtotal', 'subtotal_fresh'], $freshKg * $freshPrice);
        $frozenSubtotal = $this->number($row, ['frozen_subtotal', 'subtotal_frozen', 'subtotal_durpas'], $frozenKg * $frozenPrice);

        return new Sale([
            'outlet_id' => $this->resolveOutletId($this->value($row, ['outlet_id', 'outlet', 'nama_outlet', 'cabang'])),
            'durian_variety_id' => $this->resolveDurianVarietyId($this->value($row, ['durian_variety_id', 'varian', 'variety', 'durian', 'jenis'])),
            'date' => $this->date($row, ['date', 'tanggal', 'tgl', 'tanggal_jual', 'tgl_jual']),
            'buah_sold_kg' => $buahKg,
            'buah_sold_butir' => $this->integer($row, ['buah_sold_butir', 'buah_butir', 'butir_buah'], 0),
            'buah_price_per_kg' => $buahPrice,
            'buah_subtotal' => $buahSubtotal,
            'fresh_sold_kg' => $freshKg,
            'fresh_sold_pack' => $this->integer($row, ['fresh_sold_pack', 'fresh_pack', 'pack_fresh'], 0),
            'fresh_price_per_kg' => $freshPrice,
            'fresh_subtotal' => $freshSubtotal,
            'frozen_sold_kg' => $frozenKg,
            'frozen_sold_pack' => $this->integer($row, ['frozen_sold_pack', 'frozen_pack', 'durpas_pack'], 0),
            'frozen_price_per_kg' => $frozenPrice,
            'frozen_subtotal' => $frozenSubtotal,
            'grand_total_revenue' => $this->number($row, ['grand_total_revenue', 'grand_total', 'total_omset', 'omset'], $buahSubtotal + $freshSubtotal + $frozenSubtotal),
        ]);
    }
}
