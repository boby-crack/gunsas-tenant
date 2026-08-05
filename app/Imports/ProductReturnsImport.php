<?php

namespace App\Imports;

use App\Models\ProductReturn;
use App\Models\Shipment;
use Illuminate\Database\Eloquent\Model;

class ProductReturnsImport extends BaseExcelImport
{
    protected function makeModel(array $row): ?Model
    {
        $id = (int) $this->number($row, ['id', 'product_return_id', 'retur_id'], 0);
        $existing = $id > 0 ? ProductReturn::find($id) : null;

        if ($id > 0 && ! $existing) {
            throw new \InvalidArgumentException("retur ID {$id} tidak ditemukan");
        }

        $outletValue = $this->value($row, ['outlet_id', 'outlet', 'nama_outlet', 'cabang']);
        $varietyValue = $this->value($row, ['durian_variety_id', 'varian', 'variety', 'durian', 'jenis']);
        $dateValue = $this->value($row, [
            'date',
            'tanggal',
            'tgl',
            'tanggal_retur',
            'tgl_retur',
            'tanggal_buka',
            'tgl_buka',
            'tanggal_kedatangan',
            'tgl_kedatangan',
            'tanggal_datang',
            'tgl_datang',
        ]);
        $status = $this->normalizeStatus($this->text($row, [
            'status',
            'status_supplier',
            'hasil_supplier',
            'keputusan_supplier',
            'qc_supplier',
        ], $existing?->status ?? 'pending'));

        $outletId = $outletValue !== null
            ? $this->resolveOutletId($outletValue)
            : $existing?->outlet_id;
        $varietyId = $varietyValue !== null
            ? $this->resolveDurianVarietyId($varietyValue)
            : $existing?->durian_variety_id;
        $date = $dateValue !== null
            ? $this->date($row, [
                'date',
                'tanggal',
                'tgl',
                'tanggal_retur',
                'tgl_retur',
                'tanggal_buka',
                'tgl_buka',
                'tanggal_kedatangan',
                'tgl_kedatangan',
                'tanggal_datang',
                'tgl_datang',
            ])
            : $existing?->date;
        $arrivalDateValue = $this->value($row, ['tanggal_datang', 'tgl_datang', 'tanggal_kedatangan', 'tgl_kedatangan']);
        $arrivalDate = $arrivalDateValue !== null
            ? $this->date($row, ['tanggal_datang', 'tgl_datang', 'tanggal_kedatangan', 'tgl_kedatangan'])
            : $date;
        $qtyButir = $this->integer($row, ['qty_butir', 'butir', 'jumlah_butir', 'btr', 'butir_diajukan'], $existing?->qty_butir ?? 1);
        $qtyKg = $this->kgNumber($row, ['qty_kg', 'kg', 'berat', 'berat_kg', 'berat_buah', 'berat_kg_diajukan'], $existing?->qty_kg ?? 0);
        $acceptedButirDefault = $existing?->supplier_accepted_qty_butir ?? ($status === 'rejected_by_supplier' ? 0 : null);
        $acceptedKgDefault = $existing?->supplier_accepted_qty_kg ?? ($status === 'rejected_by_supplier' ? 0 : null);

        $acceptedKg = $this->kgNumber($row, [
            'supplier_accepted_qty_kg',
            'diterima_supplier_kg',
            'supplier_diterima_kg',
            'diterima_kg',
            'kg_diterima',
            'qty_diterima_kg',
            'berat_diterima',
            'berat_diterima_kg',
        ], $acceptedKgDefault);
        $acceptedKg = $this->plausibleAcceptedKg($row, $acceptedKg, $qtyKg);

        $attributes = [
            'outlet_id' => $outletId,
            'durian_variety_id' => $varietyId,
            'shipment_id' => $this->shipmentId($row, $outletId, $varietyId, $arrivalDate) ?? $existing?->shipment_id,
            'return_type' => $this->text($row, ['return_type', 'tipe_retur'], 'outlet_to_gudang'),
            'supplier_code' => $this->text($row, ['supplier_code', 'kode_supplier', 'kode_buah', 'kode']),
            'paint_color' => $this->text($row, ['paint_color', 'warna_cat', 'cat']),
            'date' => $date,
            'return_reason_type' => $this->text($row, ['return_reason_type', 'alasan_rusak', 'jenis_alasan'], 'Buah Rusak / Asam'),
            'qty_butir' => $qtyButir,
            'qty_kg' => $qtyKg,
            'qty_to_supplier_butir' => $this->number($row, ['qty_to_supplier_butir', 'dikirim_supplier_butir'], $existing?->qty_to_supplier_butir),
            'qty_to_supplier_kg' => $this->kgNumber($row, ['qty_to_supplier_kg', 'dikirim_supplier_kg'], $existing?->qty_to_supplier_kg),
            'detailed_reason' => $this->text($row, ['detailed_reason', 'alasan', 'keterangan', 'catatan', 'catatan_supplier'], $existing?->detailed_reason),
            'status' => $status,
            'supplier_accepted_qty_butir' => $this->number($row, [
                'supplier_accepted_qty_butir',
                'diterima_supplier_butir',
                'supplier_diterima_butir',
                'diterima_butir',
                'diterima_btr',
                'qty_diterima_butir',
                'jumlah_diterima_butir',
                'jumlah_diterima',
            ], $acceptedButirDefault),
            'supplier_accepted_qty_kg' => $acceptedKg,
            'refund_amount' => $this->moneyNumber($row, ['refund_amount', 'refund', 'uang_kembali', 'potongan_nota'], $existing?->refund_amount ?? 0),
        ];

        if (
            $attributes['supplier_accepted_qty_kg'] !== null
            && $attributes['qty_kg'] > 0
            && $attributes['supplier_accepted_qty_kg'] > $attributes['qty_kg'] + 0.001
        ) {
            throw new \InvalidArgumentException(
                'diterima_supplier_kg (' . $attributes['supplier_accepted_qty_kg']
                . ') tidak boleh lebih besar dari berat_kg retur (' . $attributes['qty_kg'] . ')'
            );
        }

        if ($existing) {
            $existing->fill($attributes);

            return $existing;
        }

        return new ProductReturn($attributes);
    }

    protected function kgNumber(array $row, array|string $keys, float|int|null $default = 0): float|int|null
    {
        $value = $this->value($row, $keys);

        if ($value === null || $value === '') {
            return $default;
        }

        if (is_numeric($value)) {
            return $value + 0;
        }

        $value = strtolower(trim((string) $value));
        $value = str_replace(['kg', 'kilogram', ' '], '', $value);
        $value = preg_replace('/[^0-9,\.\-]/', '', $value) ?: '';

        if ($value === '' || $value === '-') {
            return $default;
        }

        $lastComma = strrpos($value, ',');
        $lastDot = strrpos($value, '.');

        if ($lastComma !== false && $lastDot !== false) {
            $value = $lastComma > $lastDot
                ? str_replace('.', '', str_replace(',', '.', $value))
                : str_replace(',', '', $value);
        } elseif ($lastComma !== false) {
            $value = str_replace(',', '.', $value);
        } elseif (substr_count($value, '.') > 1) {
            $lastDot = strrpos($value, '.');
            $value = str_replace('.', '', substr($value, 0, $lastDot)) . substr($value, $lastDot);
        }

        return is_numeric($value) ? $value + 0 : $default;
    }

    protected function plausibleAcceptedKg(array $row, float|int|null $acceptedKg, float|int|null $qtyKg): float|int|null
    {
        if ($acceptedKg === null || $qtyKg <= 0 || $acceptedKg <= $qtyKg + 0.001) {
            return $acceptedKg;
        }

        foreach ($row as $key => $value) {
            $key = $this->normalizeKey($key);

            if (! str_contains($key, 'diterima') || ! str_contains($key, 'kg')) {
                continue;
            }

            $candidate = $this->kgNumber([$key => $value], [$key], null);

            if ($candidate !== null && $candidate <= $qtyKg + 0.001) {
                return $candidate;
            }
        }

        return $acceptedKg;
    }

    protected function moneyNumber(array $row, array|string $keys, float|int|null $default = 0): float|int|null
    {
        $value = $this->value($row, $keys);

        if ($value === null || $value === '') {
            return $default;
        }

        if (is_numeric($value)) {
            return $value + 0;
        }

        $value = strtolower(trim((string) $value));
        $value = str_replace(['rp', 'idr', ' '], '', $value);
        $value = preg_replace('/[^0-9,\.\-]/', '', $value) ?: '';

        if ($value === '' || $value === '-') {
            return $default;
        }

        $lastComma = strrpos($value, ',');
        $lastDot = strrpos($value, '.');

        if ($lastComma !== false && $lastDot !== false) {
            $value = $lastComma > $lastDot
                ? str_replace('.', '', str_replace(',', '.', $value))
                : str_replace(',', '', $value);
        } elseif ($lastComma !== false) {
            $value = preg_match('/^\d{1,3}(,\d{3})+$/', $value)
                ? str_replace(',', '', $value)
                : str_replace(',', '.', $value);
        } elseif ($lastDot !== false) {
            $value = preg_match('/^\d{1,3}(\.\d{3})+$/', $value)
                ? str_replace('.', '', $value)
                : $value;
        }

        return is_numeric($value) ? $value + 0 : $default;
    }

    protected function shipmentId(array $row, ?int $outletId, ?int $varietyId, ?string $date): ?int
    {
        $shipmentId = $this->value($row, ['shipment_id', 'id_pengiriman', 'nota_pengiriman']);

        if ($shipmentId && Shipment::whereKey((int) $shipmentId)->exists()) {
            return (int) $shipmentId;
        }

        if (! $outletId || ! $varietyId || ! $date) {
            return null;
        }

        return Shipment::query()
            ->where('outlet_id', $outletId)
            ->where('durian_variety_id', $varietyId)
            ->whereDate('date', '<=', $date)
            ->latest('date')
            ->value('id');
    }
}
