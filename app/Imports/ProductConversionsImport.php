<?php

namespace App\Imports;

use App\Models\ProductConversion;
use Illuminate\Database\Eloquent\Model;

class ProductConversionsImport extends BaseExcelImport
{
    protected function makeModel(array $row): ?Model
    {
        return new ProductConversion([
            'outlet_id' => $this->resolveOutletId($this->value($row, ['outlet_id', 'outlet', 'nama_outlet', 'cabang'])),
            'durian_variety_id' => $this->resolveDurianVarietyId($this->value($row, ['durian_variety_id', 'varian', 'variety', 'durian', 'jenis'])),
            'date' => $this->date($row, ['date', 'tanggal', 'tgl', 'tanggal_konversi', 'tgl_konversi', 'kedatangan', 'tgl_kedatangan']),
            'conversion_type' => $this->text($row, ['conversion_type', 'tipe_konversi', 'jenis_konversi'], 'Kupas Fresh ke Kupas Frozen'),
            'from_qty_pack' => $this->integer($row, ['from_qty_pack', 'fresh_pack', 'pack_awal', 'pack_fresh'], 0),
            'from_qty_kg' => $this->kgNumber($row, ['from_qty_kg', 'fresh_kg', 'berat_awal', 'berat_fresh_kg'], 0),
            'to_qty_pack' => $this->integer($row, ['to_qty_pack', 'frozen_pack', 'pack_akhir', 'pack_frozen', 'jumlah_pack'], 0),
            'to_qty_kg' => $this->kgNumber($row, ['to_qty_kg', 'frozen_kg', 'berat_akhir', 'berat_jadi', 'kupas_jadi'], 0),
            'notes' => $this->text($row, ['notes', 'catatan', 'note', 'keterangan']),
        ]);
    }
}
