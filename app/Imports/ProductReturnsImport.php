<?php

namespace App\Imports;

use App\Models\ProductReturn;
use App\Models\Shipment;
use Illuminate\Database\Eloquent\Model;

class ProductReturnsImport extends BaseExcelImport
{
    protected function makeModel(array $row): ?Model
    {
        $outletId = $this->resolveOutletId($this->value($row, ['outlet_id', 'outlet', 'nama_outlet', 'cabang']));
        $varietyId = $this->resolveDurianVarietyId($this->value($row, ['durian_variety_id', 'varian', 'variety', 'durian', 'jenis']));
        $date = $this->date($row, ['date', 'tanggal', 'tgl', 'tanggal_retur', 'tgl_retur', 'tanggal_kedatangan', 'tgl_kedatangan']);
        $qtyButir = $this->integer($row, ['qty_butir', 'butir', 'jumlah_butir', 'btr'], 1);
        $qtyKg = $this->number($row, ['qty_kg', 'kg', 'berat', 'berat_kg', 'berat_buah'], 0);

        return new ProductReturn([
            'outlet_id' => $outletId,
            'durian_variety_id' => $varietyId,
            'shipment_id' => $this->shipmentId($row, $outletId, $varietyId, $date),
            'return_type' => $this->text($row, ['return_type', 'tipe_retur'], 'outlet_to_gudang'),
            'supplier_code' => $this->text($row, ['supplier_code', 'kode_supplier', 'kode_buah', 'kode']),
            'paint_color' => $this->text($row, ['paint_color', 'warna_cat', 'cat']),
            'date' => $date,
            'return_reason_type' => $this->text($row, ['return_reason_type', 'alasan_rusak', 'jenis_alasan'], 'Buah Rusak / Asam'),
            'qty_butir' => $qtyButir,
            'qty_kg' => $qtyKg,
            'qty_to_supplier_butir' => $this->number($row, ['qty_to_supplier_butir', 'dikirim_supplier_butir'], null),
            'qty_to_supplier_kg' => $this->number($row, ['qty_to_supplier_kg', 'dikirim_supplier_kg'], null),
            'detailed_reason' => $this->text($row, ['detailed_reason', 'alasan', 'keterangan', 'catatan']),
            'status' => $this->normalizeStatus($this->text($row, ['status', 'status_supplier'])),
            'supplier_accepted_qty_butir' => $this->number($row, ['supplier_accepted_qty_butir', 'diterima_supplier_butir'], null),
            'supplier_accepted_qty_kg' => $this->number($row, ['supplier_accepted_qty_kg', 'diterima_supplier_kg'], null),
            'refund_amount' => $this->number($row, ['refund_amount', 'refund', 'uang_kembali', 'potongan_nota'], 0),
        ]);
    }

    protected function shipmentId(array $row, int $outletId, int $varietyId, string $date): ?int
    {
        $shipmentId = $this->value($row, ['shipment_id', 'id_pengiriman', 'nota_pengiriman']);

        if ($shipmentId && Shipment::whereKey((int) $shipmentId)->exists()) {
            return (int) $shipmentId;
        }

        return Shipment::query()
            ->where('outlet_id', $outletId)
            ->where('durian_variety_id', $varietyId)
            ->whereDate('date', '<=', $date)
            ->latest('date')
            ->value('id');
    }
}
