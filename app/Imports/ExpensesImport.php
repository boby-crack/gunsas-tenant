<?php

namespace App\Imports;

use App\Models\Expense;
use Illuminate\Database\Eloquent\Model;

class ExpensesImport extends BaseExcelImport
{
    protected function makeModel(array $row): ?Model
    {
        $rawCategory = $this->text($row, ['category', 'kategori', 'jenis_biaya'], 'Lain-lain');
        $notes = $this->text($row, ['notes', 'catatan', 'keterangan', 'deskripsi']);

        return new Expense([
            'date' => $this->date($row, ['date', 'tanggal', 'tgl', 'tanggal_biaya', 'tgl_biaya']),
            'outlet_id' => $this->resolveExpenseOutletId($this->value($row, ['outlet_id', 'outlet', 'nama_outlet', 'cabang'])),
            'category' => $this->normalizeExpenseCategory($rawCategory, $notes),
            'amount' => $this->number($row, ['amount', 'nominal', 'biaya', 'total'], 0),
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
}
