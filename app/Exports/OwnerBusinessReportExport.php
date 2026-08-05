<?php

namespace App\Exports;

use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class OwnerBusinessReportExport implements FromArray, ShouldAutoSize, WithStyles
{
    public function __construct(
        private readonly array $insights,
    ) {}

    public function array(): array
    {
        $rows = [
            ['LAPORAN BISNIS - GUNSAS JAYA BERKAH'],
            ['Periode', $this->periodLabel()],
            ['Area', $this->insights['filters']['outlet_name'] ?? 'Semua Outlet'],
            ['Dibuat', now()->format('d M Y H:i')],
            [],
            ['RINGKASAN UTAMA'],
            ['Metrik', 'Nilai', 'Catatan'],
            ['Omset Kasir', $this->money($this->sales('gross_sales')), 'Penjualan bruto di kasir'],
            ['Sales Net', $this->money($this->sales('net_sales')), 'Setelah diskon dan sales return'],
            ['Pendapatan Gunsas', $this->money($this->sales('gunsas_revenue')), 'Setelah bagi hasil partner'],
            ['Profit Bersih', $this->money($this->profit('net_profit')), 'Setelah HPP, expense, inventory terpakai, retur final, dan opname'],
            ['Margin Bersih', $this->percent($this->profit('net_margin')), 'Profit bersih / pendapatan Gunsas'],
            ['Recovery Fresh Terjual', $this->kg($this->returnRecovery('sold_kg')), 'Fresh dari retur yang sudah diasumsikan terjual'],
            ['HPP Tidak Dibebankan Recovery', $this->money($this->returnRecovery('hpp_saved_amount')), 'HPP fresh tidak ditambahkan lagi karena modal buah retur sudah masuk loss retur'],
            ['Profit + Inventory', $this->money($this->profit('net_asset_position')), 'Profit bersih ditambah inventory valuation'],
            [],
            ['BIAYA, LOSS, DAN ASET'],
            ['Metrik', 'Nilai', 'Catatan'],
            ['HPP Penjualan', $this->money($this->cost('hpp_sales')), 'Modal barang yang sudah terjual'],
            ['Expense', $this->money($this->cost('expenses')), 'Direct outlet + alokasi global'],
            ['Inventory Terpakai Operasional', $this->money($this->cost('inventory_usage')), 'Packaging/supply yang habis terpakai'],
            ['Loss Produk Jualan', $this->money($this->opnameLoss('inventory_item_amount')), 'Produk jualan non-durian yang kurang saat stok opname'],
            ['Loss Retur Final', $this->money($this->returns('loss_final')), 'Klaim retur yang tidak tertutup refund supplier'],
            ['Loss Opname', $this->money($this->cost('opname_loss')), 'Selisih minus stok opname'],
            ['Nilai Stok Tersisa', $this->money($this->inventory('amount')), 'Inventory valuation sebagai aset terpisah'],
            [],
            ['CATATAN MANAJEMEN'],
        ];

        foreach ($this->ownerNotes() as $note) {
            $rows[] = ['-', $note];
        }

        $rows[] = [];
        $rows[] = ['PENJUALAN PER PRODUK'];
        $rows[] = ['Produk', 'Kategori', 'Varian', 'KG', 'Omset', 'Sales Net', 'Bagian Gunsas', 'Avg/Kg'];

        foreach ($this->insights['sales_by_product'] ?? [] as $row) {
            $rows[] = [
                $row['product'] ?? '-',
                $row['category'] ?? '-',
                $row['variety'] ?? '-',
                $this->kg($row['kg'] ?? 0),
                $this->money($row['gross_sales'] ?? 0),
                $this->money($row['net_sales'] ?? 0),
                $this->money($row['gunsas_revenue'] ?? 0),
                $this->money($row['avg_price_per_kg'] ?? 0) . ' / Kg',
            ];
        }

        $rows[] = [];
        $rows[] = ['PROFIT PER OUTLET'];
        $rows[] = ['Outlet', 'Grup', 'Sales Net', 'Pendapatan Gunsas', 'HPP', 'Expense', 'Retur Loss', 'Opname Loss', 'Inventory', 'Profit Bersih', 'Margin'];

        foreach ($this->insights['profit_by_outlet'] ?? [] as $row) {
            $rows[] = [
                $row['outlet_name'] ?? '-',
                $row['group_name'] ?? '-',
                $this->money($row['net_sales'] ?? 0),
                $this->money($row['gunsas_revenue'] ?? 0),
                $this->money($row['hpp_sales'] ?? 0),
                $this->money($row['expenses'] ?? 0),
                $this->money($row['return_loss'] ?? 0),
                $this->money($row['opname_loss'] ?? 0),
                $this->money($row['inventory_usage'] ?? 0),
                $this->money($row['net_profit'] ?? 0),
                $this->percent($row['net_margin'] ?? 0),
            ];
        }

        $rows[] = [];
        $rows[] = ['EXPENSE TERBESAR'];
        $rows[] = ['Kategori', 'Nominal'];

        foreach ($this->insights['expense_categories'] ?? [] as $row) {
            $rows[] = [
                $row['category'] ?? '-',
                $this->money($row['total'] ?? 0),
            ];
        }

        $rows[] = [];
        $rows[] = ['RETUR SUPPLIER'];
        $rows[] = ['Metrik', 'Nilai'];
        $rows[] = ['Retur Diajukan', $this->money($this->returns('asset_submitted'))];
        $rows[] = ['KG Diajukan', $this->kg($this->returns('submitted_kg'))];
        $rows[] = ['Refund Diterima', $this->money($this->returns('refund_received'))];
        $rows[] = ['Loss Final', $this->money($this->returns('loss_final'))];
        $rows[] = ['KG Ditolak Supplier', $this->kg($this->returns('rejected_kg'))];
        $rows[] = ['Fresh dari Return', $this->kg($this->returnRecovery('fresh_kg'))];
        $rows[] = ['Fresh Recovery Terjual', $this->kg($this->returnRecovery('sold_kg'))];
        $rows[] = ['Fresh Recovery Tersisa', $this->kg($this->returnRecovery('remaining_kg'))];
        $rows[] = ['Olahan dari Return', $this->kg($this->returnRecovery('olahan_kg'))];
        $rows[] = ['HPP Tidak Dibebankan Recovery', $this->money($this->returnRecovery('hpp_saved_amount'))];
        $rows[] = ['Rugi Final Setelah Refund', $this->money($this->returns('loss_final'))];
        $rows[] = ['Klaim Pending', $this->money($this->returns('pending_asset'))];
        $rows[] = ['Jumlah Pending', ($this->returns('pending_count')) . ' retur'];

        $rows[] = [];
        $rows[] = ['EFISIENSI PRODUKSI'];
        $rows[] = ['Metrik', 'Nilai'];
        $rows[] = ['Input Buah Utuh', $this->kg($this->production('input_kg'))];
        $rows[] = ['Total Daging Diperoleh', $this->kg($this->production('usable_kg'))];
        $rows[] = ['Kupas Fresh', $this->kg($this->production('fresh_kg')) . ' / ' . $this->percent($this->production('fresh_yield_percentage'))];
        $rows[] = ['Olahan / Reject', $this->kg($this->production('olahan_kg')) . ' / ' . $this->percent($this->production('olahan_yield_percentage'))];
        $rows[] = ['Susut Kulit & Biji', $this->kg($this->production('shrink_kg')) . ' / ' . $this->percent($this->production('shrinkage_percentage'))];
        $rows[] = ['Yield Daging', $this->percent($this->production('yield_percentage'))];
        $rows[] = ['Pengkali Produksi Fisik', number_format((float) $this->production('multiplier_factor'), 2, ',', '.') . 'x'];

        return $rows;
    }

    public function styles(Worksheet $sheet): array
    {
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
        $sheet->getStyle('A:K')->getAlignment()->setVertical('top');

        foreach ($sheet->getRowIterator() as $row) {
            $cell = 'A' . $row->getRowIndex();
            $value = (string) $sheet->getCell($cell)->getValue();

            if ($value !== '' && strtoupper($value) === $value && ! str_contains($value, ' - ')) {
                $sheet->getStyle($row->getRowIndex() . ':' . $row->getRowIndex())->getFont()->setBold(true);
            }
        }

        return [];
    }

    private function ownerNotes(): array
    {
        $notes = [];
        $netProfit = $this->profit('net_profit');
        $margin = $this->profit('net_margin');
        $largestExpense = collect($this->insights['expense_categories'] ?? [])->sortByDesc('total')->first();
        $worstOutlet = collect($this->insights['profit_by_outlet'] ?? [])->sortBy('net_profit')->first();

        $notes[] = $netProfit >= 0
            ? 'Profit periode ini positif sebesar ' . $this->money($netProfit) . ' dengan margin ' . $this->percent($margin) . '.'
            : 'Profit periode ini masih rugi ' . $this->money(abs($netProfit)) . '; perlu cek HPP, expense, retur, dan loss opname.';

        if ($largestExpense) {
            $notes[] = 'Expense terbesar: ' . ($largestExpense['category'] ?? '-') . ' sebesar ' . $this->money($largestExpense['total'] ?? 0) . '.';
        }

        if ($this->returns('pending_count') > 0) {
            $notes[] = 'Masih ada ' . $this->returns('pending_count') . ' klaim retur pending senilai ' . $this->money($this->returns('pending_asset')) . '.';
        }

        if ($this->inventory('amount') > 0) {
            $notes[] = 'Stok tersisa bernilai ' . $this->money($this->inventory('amount')) . '; ini aset, bukan laba bersih.';
        }

        if ($worstOutlet && ($worstOutlet['net_profit'] ?? 0) < 0) {
            $notes[] = 'Outlet paling perlu dicek: ' . ($worstOutlet['outlet_name'] ?? '-') . ' rugi ' . $this->money(abs($worstOutlet['net_profit'] ?? 0)) . '.';
        }

        return $notes;
    }

    private function periodLabel(): string
    {
        $from = $this->insights['filters']['date_from'] ?? now()->startOfMonth()->toDateString();
        $until = $this->insights['filters']['date_until'] ?? now()->toDateString();

        return Carbon::parse($from)->format('d M Y') . ' - ' . Carbon::parse($until)->format('d M Y');
    }

    private function money(float|int|null $value): string
    {
        return 'Rp ' . number_format((float) $value, 0, ',', '.');
    }

    private function kg(float|int|null $value): string
    {
        return number_format((float) $value, 3, ',', '.') . ' Kg';
    }

    private function percent(float|int|null $value): string
    {
        return number_format((float) $value, 2, ',', '.') . '%';
    }

    private function sales(string $key): float
    {
        return (float) ($this->insights['sales'][$key] ?? 0);
    }

    private function cost(string $key): float
    {
        return (float) ($this->insights['costs'][$key] ?? 0);
    }

    private function opnameLoss(string $key): float
    {
        return (float) ($this->insights['costs']['opname_loss_kg'][$key] ?? 0);
    }

    private function profit(string $key): float
    {
        return (float) ($this->insights['profit'][$key] ?? 0);
    }

    private function returns(string $key): float
    {
        return (float) ($this->insights['returns'][$key] ?? 0);
    }

    private function returnRecovery(string $key): float
    {
        return (float) ($this->insights['returns']['recovery'][$key] ?? 0);
    }

    private function inventory(string $key): float
    {
        return (float) ($this->insights['inventory'][$key] ?? 0);
    }

    private function production(string $key): float
    {
        return (float) ($this->insights['production_efficiency'][$key] ?? 0);
    }
}
