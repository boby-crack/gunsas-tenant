<?php

namespace App\Services;

use App\Models\DurianVariety;
use App\Models\ProductReturn;
use App\Models\Purchase;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

class SupplierPerformanceCalculator
{
    public function calculate(array $filters): array
    {
        $filters = $this->normalizeFilters($filters);
        $purchases = $this->purchaseRows($filters);
        $returns = $this->returnRows($filters);
        $supplierNames = $this->supplierNames();
        $keys = collect(array_merge(array_keys($purchases), array_keys($returns)))
            ->filter()
            ->unique()
            ->sort()
            ->values();

        $rows = $keys->map(function (string $supplier) use ($purchases, $returns, $supplierNames): array {
            $purchase = $purchases[$supplier] ?? [];
            $return = $returns[$supplier] ?? [];
            $purchaseKg = (float) ($purchase['purchase_kg'] ?? 0);
            $purchaseAmount = (float) ($purchase['purchase_amount'] ?? 0);
            $avgPrice = $purchaseKg > 0 ? $purchaseAmount / $purchaseKg : 0;
            $submittedKg = (float) ($return['submitted_kg'] ?? 0);
            $finalKg = (float) ($return['final_kg'] ?? 0);
            $acceptedKg = (float) ($return['accepted_kg'] ?? 0);
            $refund = (float) ($return['refund'] ?? 0);
            $finalAsset = $finalKg * $avgPrice;
            $lossFinal = max(0, $finalAsset - $refund);
            $rejectedKg = max(0, $finalKg - $acceptedKg);
            $returnRate = $purchaseKg > 0 ? ($submittedKg / $purchaseKg) * 100 : 0;
            $acceptedRate = $finalKg > 0 ? ($acceptedKg / $finalKg) * 100 : 100;
            $refundCoverage = $finalAsset > 0 ? ($refund / $finalAsset) * 100 : 0;
            $lossRate = $purchaseAmount > 0 ? ($lossFinal / $purchaseAmount) * 100 : 0;
            $score = $this->score($returnRate, $lossRate, $acceptedRate, $refundCoverage);

            return [
                'supplier_code' => $supplier,
                'supplier_name' => $supplierNames[$supplier] ?? $supplier,
                'purchase_records' => (int) ($purchase['purchase_records'] ?? 0),
                'purchase_butir' => (float) ($purchase['purchase_butir'] ?? 0),
                'purchase_kg' => $purchaseKg,
                'purchase_amount' => $purchaseAmount,
                'avg_price_per_kg' => $avgPrice,
                'return_records' => (int) ($return['return_records'] ?? 0),
                'submitted_kg' => $submittedKg,
                'final_kg' => $finalKg,
                'accepted_kg' => $acceptedKg,
                'rejected_kg' => $rejectedKg,
                'refund' => $refund,
                'loss_final' => $lossFinal,
                'return_rate' => $returnRate,
                'accepted_rate' => $acceptedRate,
                'refund_coverage' => $refundCoverage,
                'loss_rate' => $lossRate,
                'score' => $score,
                'status' => $this->status($score),
            ];
        })
            ->sortByDesc('loss_final')
            ->values()
            ->all();

        return [
            'filters' => [
                ...$filters,
                'period_label' => Carbon::parse($filters['date_from'])->format('d M Y') . ' - ' . Carbon::parse($filters['date_until'])->format('d M Y'),
                'varian_label' => $filters['durian_variety_id']
                    ? DurianVariety::query()->whereKey($filters['durian_variety_id'])->value('name')
                    : 'Semua Varian',
            ],
            'summary' => $this->summary($rows),
            'rows' => $rows,
            'best_suppliers' => collect($rows)->where('purchase_kg', '>', 0)->sortByDesc('score')->take(5)->values()->all(),
            'risk_suppliers' => collect($rows)->where('return_records', '>', 0)->sortByDesc('loss_final')->take(5)->values()->all(),
        ];
    }

    private function normalizeFilters(array $filters): array
    {
        return [
            'date_from' => $filters['date_from'] ?? now()->startOfMonth()->toDateString(),
            'date_until' => $filters['date_until'] ?? now()->toDateString(),
            'durian_variety_id' => $filters['durian_variety_id'] ?? null,
            'supplier_code' => filled($filters['supplier_code'] ?? null) ? trim((string) $filters['supplier_code']) : null,
        ];
    }

    private function purchaseRows(array $filters): array
    {
        return $this->dateFilter(Purchase::query(), $filters)
            ->when($filters['durian_variety_id'], fn (Builder $query, $id) => $query->where('durian_variety_id', $id))
            ->when($filters['supplier_code'], fn (Builder $query, $supplier) => $query->where('supplier_code', $supplier))
            ->where('qty_kg', '>', 0)
            ->selectRaw("
                COALESCE(NULLIF(supplier_code, ''), NULLIF(supplier_name, ''), 'Tanpa Supplier') as supplier_key,
                COUNT(*) as purchase_records,
                COALESCE(SUM(qty_butir), 0) as purchase_butir,
                COALESCE(SUM(qty_kg), 0) as purchase_kg,
                COALESCE(SUM(CASE WHEN total_amount > 0 THEN total_amount ELSE qty_kg * price_per_kg END), 0) as purchase_amount
            ")
            ->groupBy('supplier_key')
            ->get()
            ->keyBy('supplier_key')
            ->map(fn ($row) => [
                'purchase_records' => (int) $row->purchase_records,
                'purchase_butir' => (float) $row->purchase_butir,
                'purchase_kg' => (float) $row->purchase_kg,
                'purchase_amount' => (float) $row->purchase_amount,
            ])
            ->all();
    }

    private function returnRows(array $filters): array
    {
        return $this->dateFilter(ProductReturn::query(), $filters)
            ->when($filters['durian_variety_id'], fn (Builder $query, $id) => $query->where('durian_variety_id', $id))
            ->when($filters['supplier_code'], fn (Builder $query, $supplier) => $query->where('supplier_code', $supplier))
            ->selectRaw("
                COALESCE(NULLIF(supplier_code, ''), 'Tanpa Supplier') as supplier_key,
                COUNT(*) as return_records,
                COALESCE(SUM(qty_kg), 0) as submitted_kg,
                COALESCE(SUM(CASE WHEN status <> 'pending' THEN qty_kg ELSE 0 END), 0) as final_kg,
                COALESCE(SUM(CASE WHEN status <> 'pending' THEN supplier_accepted_qty_kg ELSE 0 END), 0) as accepted_kg,
                COALESCE(SUM(CASE WHEN status <> 'pending' THEN refund_amount ELSE 0 END), 0) as refund
            ")
            ->groupBy('supplier_key')
            ->get()
            ->keyBy('supplier_key')
            ->map(fn ($row) => [
                'return_records' => (int) $row->return_records,
                'submitted_kg' => (float) $row->submitted_kg,
                'final_kg' => (float) $row->final_kg,
                'accepted_kg' => (float) $row->accepted_kg,
                'refund' => (float) $row->refund,
            ])
            ->all();
    }

    private function dateFilter(Builder $query, array $filters): Builder
    {
        return $query
            ->when($filters['date_from'], fn (Builder $query, $date) => $query->where('date', '>=', $date))
            ->when($filters['date_until'], fn (Builder $query, $date) => $query->where('date', '<=', $date));
    }

    private function supplierNames(): array
    {
        return Purchase::query()
            ->whereNotNull('supplier_code')
            ->whereNotNull('supplier_name')
            ->orderByDesc('date')
            ->get(['supplier_code', 'supplier_name'])
            ->mapWithKeys(fn (Purchase $purchase) => [$purchase->supplier_code => $purchase->supplier_name])
            ->all();
    }

    private function summary(array $rows): array
    {
        $purchaseKg = collect($rows)->sum('purchase_kg');
        $purchaseAmount = collect($rows)->sum('purchase_amount');
        $submittedKg = collect($rows)->sum('submitted_kg');
        $lossFinal = collect($rows)->sum('loss_final');

        return [
            'supplier_count' => count($rows),
            'purchase_kg' => $purchaseKg,
            'purchase_amount' => $purchaseAmount,
            'avg_price_per_kg' => $purchaseKg > 0 ? $purchaseAmount / $purchaseKg : 0,
            'submitted_kg' => $submittedKg,
            'return_rate' => $purchaseKg > 0 ? ($submittedKg / $purchaseKg) * 100 : 0,
            'refund' => collect($rows)->sum('refund'),
            'loss_final' => $lossFinal,
            'loss_rate' => $purchaseAmount > 0 ? ($lossFinal / $purchaseAmount) * 100 : 0,
        ];
    }

    private function score(float $returnRate, float $lossRate, float $acceptedRate, float $refundCoverage): float
    {
        $score = 100;
        $score -= min(40, $returnRate * 1.5);
        $score -= min(35, $lossRate * 3);
        $score -= min(15, max(0, 90 - $acceptedRate) / 2);
        $score += min(10, max(0, $refundCoverage - 80) / 2);

        return round(max(0, min(100, $score)), 2);
    }

    private function status(float $score): string
    {
        return match (true) {
            $score >= 85 => 'Bagus',
            $score >= 70 => 'Perlu Dipantau',
            default => 'Risiko Tinggi',
        };
    }
}
