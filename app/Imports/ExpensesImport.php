<?php

namespace App\Imports;

use App\Models\Expense;
use Illuminate\Database\Eloquent\Model;

class ExpensesImport extends BaseExcelImport
{
    protected function makeModel(array $row): ?Model
    {
        return new Expense([
            'date' => $this->date($row, ['date', 'tanggal', 'tgl', 'tanggal_biaya', 'tgl_biaya']),
            'outlet_id' => $this->resolveOutletId($this->value($row, ['outlet_id', 'outlet', 'nama_outlet', 'cabang']), required: false),
            'category' => $this->text($row, ['category', 'kategori', 'jenis_biaya'], 'Lain-lain'),
            'amount' => $this->number($row, ['amount', 'nominal', 'biaya', 'total'], 0),
            'notes' => $this->text($row, ['notes', 'catatan', 'keterangan', 'deskripsi']),
        ]);
    }
}
