<?php

namespace App\Imports;

use App\Models\Expense;
use App\Models\Outlet;
use Illuminate\Database\Eloquent\Model;

class ExpensesImport extends BaseExcelImport
{
    protected function makeModel(array $row): ?Model
    {
        $id = (int) $this->number($row, ['id', 'expense_id', 'biaya_id'], 0);
        $expense = $id > 0 ? Expense::find($id) : null;

        if ($id > 0 && ! $expense) {
            throw new \InvalidArgumentException("expense ID {$id} tidak ditemukan");
        }

        $settlementAmount = $this->value($row, ['total_biaya', 'biaya_settlement', 'partner_fee', 'fee_partner']);
        $rawCategory = $this->text($row, ['category', 'kategori', 'jenis_biaya'], $settlementAmount !== null ? 'Biaya Partner / Settlement' : 'Lain-lain');
        $notes = $this->text($row, ['notes', 'catatan', 'keterangan', 'deskripsi', 'kode_referensi', 'referensi']);

        return ($expense ?? new Expense())->fill([
            'date' => $this->date($row, ['date', 'tanggal', 'tgl', 'tanggal_biaya', 'tgl_biaya']),
            'outlet_id' => $this->resolveExpenseOutletId($this->value($row, ['outlet_id', 'outlet', 'nama_outlet', 'cabang'])),
            'allocation_scope' => $this->normalizeAllocationScope($this->value($row, ['allocation_scope', 'scope_alokasi', 'scope', 'alokasi_scope'])),
            'allocation_group' => $this->normalizeAllocationGroup($this->value($row, ['allocation_group', 'grup_alokasi', 'group', 'grup'])),
            'category' => $this->normalizeExpenseCategory($rawCategory, $notes),
            'amount' => $this->number($row, ['amount', 'nominal', 'biaya', 'total_biaya', 'biaya_settlement', 'partner_fee', 'fee_partner', 'total'], 0),
            'notes' => $notes ?: $rawCategory,
        ]);
    }

    private function resolveExpenseOutletId(mixed $value): ?int
    {
        $text = strtolower(trim((string) $value));

        if ($text === '' || in_array($text, ['pusat', 'global', 'head office', 'ho'], true)) {
            return null;
        }

        $shortcuts = [
            'gede' => 'p gede',
            'bambu' => 'p bambu',
        ];

        return $this->resolveOutletId($shortcuts[$text] ?? $value, required: false);
    }

    private function normalizeExpenseCategory(?string $value, ?string $notes = null): string
    {
        $text = strtolower(trim((string) $value . ' ' . (string) $notes));

        if (str_contains($text, 'parkir') || str_contains($text, 'pak ogah') || str_contains($text, 'parkr')) {
            return 'Parkir';
        }

        if (
            str_contains($text, 'settlement')
            || str_contains($text, 'biaya partner')
            || str_contains($text, 'partner fee')
            || str_contains($text, 'fee partner')
            || str_contains($text, 'total buah')
            || str_contains($text, 'potongan partner')
            || str_contains($text, 'biaya platform')
        ) {
            return 'Biaya Partner / Settlement';
        }

        if (
            str_contains($text, 'logistik')
            || str_contains($text, 'kurir')
            || str_contains($text, 'lalamove')
            || str_contains($text, 'ongkir')
            || str_contains($text, 'gosend')
            || str_contains($text, 'go-send')
            || str_contains($text, 'grab')
            || str_contains($text, 'delivery')
        ) {
            return 'Logistik / Kurir';
        }

        if (str_contains($text, 'bensin') || str_contains($text, 'tol') || str_contains($text, 'e-toll') || str_contains($text, 'grand max') || str_contains($text, 'grandmax')) {
            return 'Bensin & Tol';
        }

        if (str_contains($text, 'listrik') || str_contains($text, 'air')) {
            return 'Listrik & Air';
        }

        if (str_contains($text, 'gaji') || str_contains($text, 'lembur') || str_contains($text, 'staff')) {
            return 'Gaji / Lemburan Staff';
        }

        if (str_contains($text, 'sewa') || str_contains($text, 'tenant')) {
            return 'Sewa Tempat / Tenant';
        }

        if (str_contains($text, 'packaging') || str_contains($text, 'thinwall') || str_contains($text, 'stiker') || str_contains($text, 'tusuk') || str_contains($text, 'print')) {
            return 'Perlengkapan & Packaging';
        }

        return 'Lain-lain';
    }

    private function normalizeAllocationScope(mixed $value): ?string
    {
        $text = strtolower(trim((string) $value));

        if ($text === '' || in_array($text, ['semua', 'all', 'global', 'semua outlet', 'semua outlet aktif'], true)) {
            return null;
        }

        if (in_array($text, ['group', 'grup', 'grup outlet', 'outlet group', 'kelompok'], true)) {
            return 'group';
        }

        if (in_array($text, ['none', 'pusat', 'pusat saja', 'tidak dibagi', 'no allocation'], true)) {
            return 'none';
        }

        return null;
    }

    private function normalizeAllocationGroup(mixed $value): ?string
    {
        $group = Outlet::normalizeGroupName((string) $value);

        return $group && array_key_exists($group, Outlet::GROUPS) ? $group : null;
    }
}
