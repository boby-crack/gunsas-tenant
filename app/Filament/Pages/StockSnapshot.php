<?php

namespace App\Filament\Pages;

use App\Models\DurianVariety;
use App\Models\InventoryItem;
use App\Models\Outlet;
use App\Services\StockSnapshotCalculator;
use Filament\Pages\Page;

class StockSnapshot extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-check';

    protected static ?string $navigationLabel = 'Sisa Stok';

    protected static ?string $navigationGroup = 'Inventory';

    protected static ?string $title = 'Sisa Stok';

    protected static ?int $navigationSort = 2;

    protected static string $view = 'filament.pages.stock-snapshot';

    public ?array $filters = [];

    public array $snapshotData = [];

    public function mount(): void
    {
        $this->filters = $this->filtersFromRequest();

        $this->refreshSnapshot();
    }

    public function getOutletGroupOptions(): array
    {
        return Outlet::GROUPS;
    }

    public function getOutletOptions(): array
    {
        return Outlet::query()
            ->when(filled($this->filters['outlet_group'] ?? null), fn ($query) => $query->where('group_name', Outlet::normalizeGroupName($this->filters['outlet_group'])))
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    public function getProductCategoryOptions(): array
    {
        return [
            'durian' => 'Produk Durian',
            'non_durian' => 'Produk Non-durian',
        ];
    }

    public function getDurianProductOptions(): array
    {
        return [
            'Buah Utuh' => 'Buah Utuh',
            'Daging Fresh' => 'Kupas Fresh',
            'Daging Frozen' => 'Durpas Frozen',
            'Daging Olahan' => 'Daging Olahan / Reject',
        ];
    }

    public function getDurianVarietyOptions(): array
    {
        return DurianVariety::query()->orderBy('name')->pluck('name', 'id')->all();
    }

    public function getInventoryItemOptions(): array
    {
        return InventoryItem::query()->orderBy('name')->pluck('name', 'id')->all();
    }

    public function exportUrl(): string
    {
        return route('reports.stock-snapshot.export', $this->exportFilters());
    }

    public function getSnapshotProperty(): array
    {
        if ($this->snapshotData === []) {
            $this->refreshSnapshot();
        }

        return $this->snapshotData;
    }

    private function refreshSnapshot(): void
    {
        $this->snapshotData = app(StockSnapshotCalculator::class)->calculate($this->filters ?? []);
    }

    private function filtersFromRequest(): array
    {
        $filters = request()->input('filters', []);
        $outletIds = $filters['outlet_ids'] ?? [];

        if (! is_array($outletIds)) {
            $outletIds = filled($outletIds) ? [$outletIds] : [];
        }

        $productCategory = in_array($filters['product_category'] ?? null, ['durian', 'non_durian'], true)
            ? $filters['product_category']
            : null;

        $productType = in_array($filters['product_type'] ?? null, array_keys($this->getDurianProductOptions()), true)
            ? $filters['product_type']
            : null;

        return [
            'date_from' => filled($filters['date_from'] ?? null) ? $filters['date_from'] : now()->toDateString(),
            'date_until' => filled($filters['date_until'] ?? null) ? $filters['date_until'] : now()->toDateString(),
            'outlet_group' => Outlet::normalizeGroupName($filters['outlet_group'] ?? null),
            'outlet_ids' => collect($outletIds)->filter(fn ($id) => filled($id))->map(fn ($id) => (string) $id)->values()->all(),
            'product_category' => $productCategory,
            'product_type' => $productCategory === 'non_durian' ? null : $productType,
            'durian_variety_id' => $productCategory === 'non_durian' ? null : (filled($filters['durian_variety_id'] ?? null) ? (string) $filters['durian_variety_id'] : null),
            'inventory_item_id' => $productCategory === 'durian' ? null : (filled($filters['inventory_item_id'] ?? null) ? (string) $filters['inventory_item_id'] : null),
        ];
    }

    private function exportFilters(): array
    {
        return collect($this->filters ?? [])
            ->reject(fn ($value): bool => $value === null || $value === '' || $value === [])
            ->all();
    }
}
