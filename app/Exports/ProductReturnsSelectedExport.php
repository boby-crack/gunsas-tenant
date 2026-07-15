<?php

namespace App\Exports;

use App\Models\ProductReturn;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class ProductReturnsSelectedExport implements FromCollection, ShouldAutoSize, WithHeadings, WithMapping
{
    public function __construct(
        protected Collection $records,
    ) {}

    public function collection(): Collection
    {
        return $this->records->loadMissing(['outlet', 'durianVariety', 'shipment']);
    }

    public function headings(): array
    {
        return [
            'id',
            'tanggal',
            'outlet',
            'varian',
            'kode_supplier',
            'warna_cat',
            'butir_diajukan',
            'berat_kg_diajukan',
            'dikirim_supplier_butir',
            'dikirim_supplier_kg',
            'status_supplier',
            'diterima_supplier_butir',
            'diterima_supplier_kg',
            'refund_amount',
            'alasan',
            'catatan_supplier',
        ];
    }

    public function map($row): array
    {
        /** @var ProductReturn $row */
        return [
            $row->id,
            $row->date ? Carbon::parse($row->date)->toDateString() : null,
            $row->outlet?->name,
            $row->durianVariety?->name,
            $row->supplier_code,
            $row->paint_color,
            $row->qty_butir,
            (float) $row->qty_kg,
            $row->qty_to_supplier_butir,
            $row->qty_to_supplier_kg,
            $row->status,
            $row->supplier_accepted_qty_butir,
            $row->supplier_accepted_qty_kg,
            $row->refund_amount,
            $row->return_reason_type,
            $row->detailed_reason,
        ];
    }
}
