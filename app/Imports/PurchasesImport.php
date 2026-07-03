<?php

namespace App\Imports;

use App\Models\Purchase;
use Illuminate\Database\Eloquent\Model;

class PurchasesImport extends BaseExcelImport
{
    protected function makeModel(array $row): ?Model
    {
        $qtyKg = $this->number($row, ['qty_kg', 'kg', 'berat', 'berat_kg', 'jumlah_kg'], 0);
        $pricePerKg = $this->number($row, ['price_per_kg', 'harga_per_kg', 'harga_kg', 'harga', 'modal_per_kg'], 0);
        $totalAmount = $this->number($row, ['total_amount', 'total', 'total_nota', 'nominal'], null);

        return new Purchase([
            'supplier_code' => $this->text($row, ['supplier_code', 'kode_supplier', 'kode_spl', 'kode']),
            'date' => $this->date($row, ['date', 'tanggal', 'tgl', 'tanggal_pembelian', 'tgl_pembelian']),
            'durian_variety_id' => $this->resolveDurianVarietyId($this->value($row, ['durian_variety_id', 'varian', 'variety', 'durian', 'jenis'])),
            'supplier_name' => $this->text($row, ['supplier_name', 'supplier', 'nama_supplier', 'kebun']),
            'qty_butir' => $this->integer($row, ['qty_butir', 'butir', 'jumlah_butir', 'btr'], 0),
            'qty_kg' => $qtyKg,
            'price_per_kg' => $pricePerKg,
            'total_amount' => $totalAmount ?? ($qtyKg * $pricePerKg),
            'notes' => $this->text($row, ['notes', 'catatan', 'keterangan']),
        ]);
    }
}
