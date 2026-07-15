<?php

namespace App\Imports;

use App\Models\Sale;
use Illuminate\Database\Eloquent\Model;

class SalesImport extends BaseExcelImport
{
    protected function makeModel(array $row): ?Model
    {
        $outletId = $this->resolveOutletId($this->value($row, ['outlet_id', 'outlet', 'nama_outlet', 'cabang']));
        $varietyId = $this->resolveDurianVarietyId($this->value($row, ['durian_variety_id', 'varian', 'variety', 'durian', 'jenis']));
        $date = $this->date($row, ['date', 'tanggal', 'tgl', 'tanggal_jual', 'tgl_jual']);

        $buahKg = $this->kgNumber($row, ['buah_sold_kg', 'buah_kg', 'buah_utuh_kg', 'buah_terjual_kg'], 0);
        $buahPrice = $this->number($row, ['buah_price_per_kg', 'harga_buah', 'harga_buah_kg'], 0);
        $freshKg = $this->kgNumber($row, ['fresh_sold_kg', 'fresh_kg', 'kupas_fresh_kg', 'daging_fresh_kg'], 0);
        $freshPrice = $this->number($row, ['fresh_price_per_kg', 'harga_fresh', 'harga_fresh_kg'], 0);
        $frozenKg = $this->kgNumber($row, ['frozen_sold_kg', 'frozen_kg', 'durpas_frozen_kg', 'durpas_kg'], 0);
        $frozenPrice = $this->number($row, ['frozen_price_per_kg', 'harga_frozen', 'harga_durpas'], 0);
        $quantityKg = $this->kgNumber($row, ['quantity_kg', 'qty_kg', 'jumlah_kg'], 0);
        $category = $this->normalizeLookup($this->text($row, ['kategori_produk', 'kategori', 'produk', 'nama_produk', 'product_name', 'product_type'], ''));

        if ($quantityKg > 0 && $buahKg + $freshKg + $frozenKg <= 0) {
            if (str_contains($category, 'frozen') || str_contains($category, 'durpas')) {
                $frozenKg = $quantityKg;
            } elseif (str_contains($category, 'buah') || str_contains($category, 'utuh')) {
                $buahKg = $quantityKg;
            } else {
                $freshKg = $quantityKg;
            }
        }

        $buahSubtotal = $this->number($row, ['buah_subtotal', 'subtotal_buah'], $buahKg * $buahPrice);
        $freshSubtotal = $this->number($row, ['fresh_subtotal', 'subtotal_fresh'], $freshKg * $freshPrice);
        $frozenSubtotal = $this->number($row, ['frozen_subtotal', 'subtotal_frozen', 'subtotal_durpas'], $frozenKg * $frozenPrice);
        $grossSales = $this->number($row, ['grand_total_revenue', 'grand_total', 'total_omset', 'omset', 'gross_sales'], $buahSubtotal + $freshSubtotal + $frozenSubtotal);

        if ($grossSales > 0 && $buahSubtotal + $freshSubtotal + $frozenSubtotal <= 0) {
            if ($frozenKg > 0) {
                $frozenSubtotal = $grossSales;
                $frozenPrice = $frozenKg > 0 ? $grossSales / $frozenKg : 0;
            } elseif ($buahKg > 0) {
                $buahSubtotal = $grossSales;
                $buahPrice = $buahKg > 0 ? $grossSales / $buahKg : 0;
            } elseif ($freshKg > 0) {
                $freshSubtotal = $grossSales;
                $freshPrice = $freshKg > 0 ? $grossSales / $freshKg : 0;
            }
        }

        $discount = $this->number($row, ['discount_amount', 'discount', 'diskon', 'sls_discount'], 0);
        $salesReturn = $this->number($row, [
            'sales_return_amount',
            'sales_return',
            'return_sales',
            'sales_retur',
            'retur_sales',
            'retur_penjualan',
        ], 0);
        $netSales = $this->number(
            $row,
            ['net_sales', 'sales_setelah_diskon', 'sales_after_discount', 'sales_incl_tax', 'sales_excl_tax'],
            max(0, $grossSales - $discount - $salesReturn),
        );

        $sale = Sale::firstOrNew([
            'outlet_id' => $outletId,
            'durian_variety_id' => $varietyId,
            'date' => $date,
        ]);

        $buahKg = (float) ($sale->buah_sold_kg ?? 0) + $buahKg;
        $buahSubtotal = (float) ($sale->buah_subtotal ?? 0) + $buahSubtotal;
        $freshKg = (float) ($sale->fresh_sold_kg ?? 0) + $freshKg;
        $freshSubtotal = (float) ($sale->fresh_subtotal ?? 0) + $freshSubtotal;
        $frozenKg = (float) ($sale->frozen_sold_kg ?? 0) + $frozenKg;
        $frozenSubtotal = (float) ($sale->frozen_subtotal ?? 0) + $frozenSubtotal;

        $sale->fill([
            'outlet_id' => $outletId,
            'durian_variety_id' => $varietyId,
            'date' => $date,
            'buah_sold_kg' => $buahKg,
            'buah_sold_butir' => (int) ($sale->buah_sold_butir ?? 0) + $this->integer($row, ['buah_sold_butir', 'buah_butir', 'butir_buah'], 0),
            'buah_price_per_kg' => $buahKg > 0 ? $buahSubtotal / $buahKg : $buahPrice,
            'buah_subtotal' => $buahSubtotal,
            'fresh_sold_kg' => $freshKg,
            'fresh_sold_pack' => (int) ($sale->fresh_sold_pack ?? 0) + $this->integer($row, ['fresh_sold_pack', 'fresh_pack', 'pack_fresh'], 0),
            'fresh_price_per_kg' => $freshKg > 0 ? $freshSubtotal / $freshKg : $freshPrice,
            'fresh_subtotal' => $freshSubtotal,
            'frozen_sold_kg' => $frozenKg,
            'frozen_sold_pack' => (int) ($sale->frozen_sold_pack ?? 0) + $this->integer($row, ['frozen_sold_pack', 'frozen_pack', 'durpas_pack'], 0),
            'frozen_price_per_kg' => $frozenKg > 0 ? $frozenSubtotal / $frozenKg : $frozenPrice,
            'frozen_subtotal' => $frozenSubtotal,
            'grand_total_revenue' => (float) ($sale->grand_total_revenue ?? 0) + $grossSales,
            'discount_amount' => (float) ($sale->discount_amount ?? 0) + $discount,
            'sales_return_amount' => (float) ($sale->sales_return_amount ?? 0) + $salesReturn,
            'net_sales' => (float) ($sale->net_sales ?? 0) + $netSales,
        ]);

        return $sale;
    }
}
