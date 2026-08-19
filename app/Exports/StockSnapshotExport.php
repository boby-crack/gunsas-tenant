<?php

namespace App\Exports;

use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;

class StockSnapshotExport implements FromArray, ShouldAutoSize
{
    public function __construct(private array $snapshot) {}

    public function array(): array
    {
        $filters = $this->snapshot['filters'] ?? [];
        $summary = $this->snapshot['summary'] ?? [];
        $rows = $this->snapshot['rows'] ?? [];

        $from = Carbon::parse($filters['date_from'] ?? $filters['date'] ?? now())->format('d M Y');
        $until = Carbon::parse($filters['date_until'] ?? $filters['date'] ?? now())->format('d M Y');
        $period = $from === $until ? $from : "{$from} - {$until}";

        $data = [
            ['Laporan Sisa Stok'],
            ['Periode', $period],
            ['Generated At', now()->format('d M Y H:i')],
            [],
            ['Ringkasan'],
            ['Stok Awal', round((float) ($summary['start_qty'] ?? 0), 3), 'Kg'],
            ['Masuk', round((float) ($summary['in_qty'] ?? 0), 3), 'Kg'],
            ['Terjual', round((float) ($summary['sold_qty'] ?? 0), 3), 'Kg'],
            ['Keluar Lain', round((float) ($summary['other_out_qty'] ?? 0), 3), 'Kg'],
            ['Olahan/Reject', round((float) ($summary['olahan_reject_qty'] ?? 0), 3), 'Kg'],
            ['Stok Akhir', round((float) ($summary['end_qty'] ?? 0), 3), 'Kg'],
            ['Selisih Opname', round((float) ($summary['variance_qty'] ?? 0), 3), 'Kg'],
            [],
            ['Detail Mutasi Stok'],
            [
                'Outlet',
                'Grup',
                'Kategori',
                'Produk',
                'Tipe Produk',
                'Varian',
                'Satuan',
                'Stok Awal',
                'Masuk',
                'Terjual',
                'Retur',
                'Diproduksi',
                'Olahan/Reject',
                'Dikonversi',
                'Balik Gudang',
                'Inventory Terpakai',
                'Stok Akhir',
                'Opname Fisik',
                'Selisih',
                'Detail',
            ],
        ];

        foreach ($rows as $row) {
            $unit = $row['unit'] ?? 'Kg';
            $detail = $row['detail'] ?? [];

            $data[] = [
                $row['outlet_name'] ?? '',
                $row['group_name'] ?? '',
                $row['category'] ?? '',
                $row['product_name'] ?? '',
                $row['product_type'] ?? '',
                $row['durian_variety_name'] ?? '',
                $unit,
                round((float) ($row['start_qty'] ?? 0), 3),
                round((float) ($row['in_qty'] ?? 0), 3),
                round((float) ($row['sold_qty'] ?? 0), 3),
                round((float) ($detail['return'] ?? 0), 3),
                round((float) ($detail['production_out'] ?? 0), 3),
                round((float) ($detail['olahan_reject'] ?? 0), 3),
                round((float) ($detail['conversion_out'] ?? 0), 3),
                round((float) ($detail['shipment_out'] ?? 0), 3),
                round((float) ($detail['consumed'] ?? 0), 3),
                round((float) ($row['end_qty'] ?? 0), 3),
                $row['physical_qty'] === null ? null : round((float) $row['physical_qty'], 3),
                $row['variance_qty'] === null ? null : round((float) $row['variance_qty'], 3),
                $this->detailText($detail, $unit),
            ];
        }

        return $data;
    }

    private function detailText(array $detail, string $unit): string
    {
        $labels = [
            'shipment_in' => 'Kirim masuk',
            'production_in' => 'Produksi masuk',
            'conversion_in' => 'Konversi masuk',
            'shipment_out' => 'Balik gudang',
            'return' => 'Retur',
            'production_out' => 'Diproduksi',
            'olahan_reject' => 'Olahan/reject',
            'conversion_out' => 'Dikonversi',
            'consumed' => 'Terpakai',
            'sold' => 'Terjual',
        ];

        $parts = [];

        foreach ($labels as $key => $label) {
            $value = (float) ($detail[$key] ?? 0);

            if (abs($value) > 0.0005) {
                $parts[] = $label . ' ' . $this->qty($value, $unit);
            }
        }

        return implode(' | ', $parts);
    }

    private function qty(float $value, string $unit): string
    {
        return number_format($value, 3, ',', '.') . ' ' . $unit;
    }
}
