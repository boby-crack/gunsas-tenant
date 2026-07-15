<?php

namespace App\Services;

use App\Models\Sale;
use App\Models\SalesTarget;
use Illuminate\Database\Eloquent\Builder;

class SalesTargetCalculator
{
    public function actualForTarget(SalesTarget $target): float
    {
        return $this->actual(
            $target->metric,
            $target->period_start?->toDateString(),
            $target->period_end?->toDateString(),
            $target->outlet_id,
        );
    }

    public function actual(string $metric, ?string $dateFrom, ?string $dateUntil, mixed $outletId = null): float
    {
        $query = Sale::query()
            ->when($outletId, fn (Builder $query) => $query->where('outlet_id', $outletId))
            ->when($dateFrom, fn (Builder $query) => $query->whereDate('date', '>=', $dateFrom))
            ->when($dateUntil, fn (Builder $query) => $query->whereDate('date', '<=', $dateUntil));

        if ($metric === 'gross_sales') {
            return (float) $query->sum('grand_total_revenue');
        }

        $netSalesExpression = 'CASE WHEN sales.net_sales > 0 THEN sales.net_sales ELSE GREATEST(sales.grand_total_revenue - sales.discount_amount - COALESCE(sales.sales_return_amount, 0), 0) END';

        if ($metric === 'gunsas_revenue') {
            return (float) $query
                ->leftJoin('outlets', 'sales.outlet_id', '=', 'outlets.id')
                ->selectRaw("SUM(({$netSalesExpression}) * (100 - COALESCE(outlets.partner_share_percent, 15)) / 100) as total")
                ->value('total');
        }

        return (float) $query
            ->selectRaw("SUM({$netSalesExpression}) as total")
            ->value('total');
    }

    public function targetAmount(string $metric, ?string $dateFrom, ?string $dateUntil, mixed $outletId = null): float
    {
        return (float) SalesTarget::query()
            ->where('metric', $metric)
            ->when($outletId, fn (Builder $query) => $query->where('outlet_id', $outletId))
            ->when($dateFrom, fn (Builder $query, $date) => $query->whereDate('period_end', '>=', $date))
            ->when($dateUntil, fn (Builder $query, $date) => $query->whereDate('period_start', '<=', $date))
            ->sum('target_amount');
    }
}
