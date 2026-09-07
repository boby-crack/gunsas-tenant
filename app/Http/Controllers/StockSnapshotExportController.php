<?php

namespace App\Http\Controllers;

use App\Exports\StockSnapshotExport;
use App\Services\StockSnapshotCalculator;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class StockSnapshotExportController extends Controller
{
    public function __invoke(Request $request)
    {
        $filters = $request->only([
            'date_from',
            'date_until',
            'outlet_group',
            'outlet_ids',
            'product_category',
            'product_type',
            'durian_variety_id',
            'inventory_item_id',
        ]);

        if (isset($filters['outlet_ids']) && ! is_array($filters['outlet_ids'])) {
            $filters['outlet_ids'] = [$filters['outlet_ids']];
        }

        $snapshot = app(StockSnapshotCalculator::class)->calculate($filters);
        $snapshotFilters = $snapshot['filters'] ?? [];
        $dateFrom = $snapshotFilters['date_from'] ?? $snapshotFilters['date'] ?? now()->toDateString();
        $dateUntil = $snapshotFilters['date_until'] ?? $dateFrom;

        return Excel::download(
            new StockSnapshotExport($snapshot),
            "laporan-sisa-stok-{$dateFrom}-{$dateUntil}.xlsx",
        );
    }
}
