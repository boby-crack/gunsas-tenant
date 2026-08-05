<?php

namespace App\Filament\Resources\Concerns;

use Illuminate\Contracts\View\View;
use Illuminate\Database\Query\Builder as QueryBuilder;

trait HasListSummaryHeader
{
    protected function summaryHeader(array $items): View
    {
        return view('filament.components.table-summary-row', ['items' => $items]);
    }

    protected function filteredSummaryQuery(): QueryBuilder
    {
        return (clone $this->getFilteredTableQuery())
            ->toBase()
            ->cloneWithout(['columns', 'orders', 'limit', 'offset'])
            ->cloneWithoutBindings(['select', 'order']);
    }

    protected function kg(float $value): string
    {
        return number_format($value, 3, ',', '.') . ' Kg';
    }

    protected function qty(float $value, string $unit = ''): string
    {
        return number_format($value, 3, ',', '.') . ($unit !== '' ? ' ' . $unit : '');
    }

    protected function whole(float $value, string $unit = ''): string
    {
        return number_format($value, 0, ',', '.') . ($unit !== '' ? ' ' . $unit : '');
    }

    protected function rupiah(float $value): string
    {
        return 'IDR ' . number_format($value, 2, '.', ',');
    }

    protected function percent(float $value): string
    {
        return number_format($value, 2, ',', '.') . '%';
    }
}
