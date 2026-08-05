<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class UpdateRecordsExport implements FromCollection, ShouldAutoSize, WithHeadings, WithMapping
{
    public function __construct(
        private readonly Collection $records,
        private readonly array $columns,
    ) {}

    public function collection(): Collection
    {
        return $this->records;
    }

    public function headings(): array
    {
        return array_keys($this->columns);
    }

    public function map($record): array
    {
        return collect($this->columns)
            ->map(fn ($column) => is_callable($column) ? $column($record) : data_get($record, $column))
            ->values()
            ->all();
    }
}
