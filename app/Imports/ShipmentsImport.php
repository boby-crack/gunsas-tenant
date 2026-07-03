<?php

namespace App\Imports;

use App\Models\Shipment;
use Illuminate\Database\Eloquent\Model;

class ShipmentsImport extends BaseExcelImport
{
    protected function makeModel(array $row): ?Model
    {
        $sentButir = $this->integer($row, ['qty_sent_butir', 'butir_kirim', 'kirim_butir', 'qty_kirim'], 0);
        $receivedButir = $this->integer($row, ['qty_received_butir', 'butir_terima', 'terima_butir', 'qty_terima'], $sentButir);
        $sentKg = $this->number($row, ['qty_sent_kg', 'kg_kirim', 'berat_kirim', 'berat_kg', 'kg'], 0);
        $modalPrice = $this->number($row, ['modal_price', 'modal', 'harga_modal', 'modal_per_butir'], 0);

        return new Shipment([
            'outlet_id' => $this->resolveOutletId($this->value($row, ['outlet_id', 'outlet', 'nama_outlet', 'cabang'])),
            'durian_variety_id' => $this->resolveDurianVarietyId($this->value($row, ['durian_variety_id', 'varian', 'variety', 'durian', 'jenis'])),
            'date' => $this->date($row, ['date', 'tanggal', 'tgl', 'tanggal_kirim', 'tgl_kirim']),
            'modal_price' => $modalPrice,
            'qty_sent_butir' => $sentButir,
            'qty_received_butir' => $receivedButir,
            'qty_sent_kg' => $sentKg,
            'average_weight' => $this->number($row, ['average_weight', 'rata_rata_berat', 'avg_kg'], $receivedButir > 0 ? $sentKg / $receivedButir : 0),
            'value_purchase' => $this->number($row, ['value_purchase', 'nilai_modal', 'total_modal'], $receivedButir * $modalPrice),
        ]);
    }
}
