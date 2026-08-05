<?php

namespace App\Filament\Pages;

use App\Exports\OwnerBusinessReportExport;
use App\Models\DurianVariety;
use App\Models\InventoryItem;
use App\Models\Outlet;
use App\Services\BusinessInsightsCalculator;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Pages\Page;
use Maatwebsite\Excel\Facades\Excel;

class BusinessInsights extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-chart-bar-square';

    protected static ?string $navigationLabel = 'Business Insights';

    protected static ?string $title = 'Business Insights';

    protected static ?int $navigationSort = 2;

    protected static string $view = 'filament.pages.business-insights';

    public ?array $filters = [];

    public array $insightsData = [];

    public function mount(): void
    {
        $this->form->fill([
            'outlet_group' => null,
            'outlet_id' => null,
            'product_category' => null,
            'durian_variety_id' => null,
            'product_type' => null,
            'inventory_item_id' => null,
            'date_from' => now()->startOfMonth()->toDateString(),
            'date_until' => now()->toDateString(),
        ]);

        $this->refreshInsights();
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Filter Analisis')
                    ->schema([
                        Select::make('outlet_group')
                            ->label('Grup Outlet')
                            ->options(Outlet::GROUPS)
                            ->placeholder('Semua Grup'),

                        Select::make('outlet_id')
                            ->label('Outlet')
                            ->options(Outlet::query()->orderBy('name')->pluck('name', 'id'))
                            ->placeholder('Semua Outlet'),

                        Select::make('product_category')
                            ->label('Kategori Produk')
                            ->options([
                                'durian' => 'Durian',
                                'non_durian' => 'Non-durian',
                            ])
                            ->placeholder('Semua Kategori')
                            ->live()
                            ->afterStateUpdated(function (Set $set): void {
                                $set('product_type', null);
                                $set('inventory_item_id', null);
                            }),

                        Select::make('product_type')
                            ->label('Produk')
                            ->options([
                                'Buah Utuh' => 'Buah Utuh',
                                'Daging Fresh' => 'Kupas Fresh',
                                'Daging Frozen' => 'Durpas Frozen',
                            ])
                            ->placeholder('Semua Produk Durian')
                            ->visible(fn (Get $get): bool => $get('product_category') !== 'non_durian'),

                        Select::make('inventory_item_id')
                            ->label('Produk')
                            ->options(InventoryItem::query()
                                ->where('is_sellable', true)
                                ->where('is_active', true)
                                ->orderBy('name')
                                ->pluck('name', 'id'))
                            ->placeholder('Semua Produk Jualan')
                            ->searchable()
                            ->helperText('Untuk produk non-durian seperti pancake, brulee, dan produk jualan lain.')
                            ->visible(fn (Get $get): bool => $get('product_category') === 'non_durian'),

                        Select::make('durian_variety_id')
                            ->label('Varian')
                            ->options(DurianVariety::query()->orderBy('name')->pluck('name', 'id'))
                            ->placeholder('Semua Varian')
                            ->searchable()
                            ->visible(fn (Get $get): bool => $get('product_category') !== 'non_durian'),

                        DatePicker::make('date_from')
                            ->label('Tanggal Mulai'),

                        DatePicker::make('date_until')
                            ->label('Tanggal Akhir'),
                    ])
                    ->columns(3),
            ])
            ->statePath('filters');
    }

    public function applyFilters(): void
    {
        $this->refreshInsights();
    }

    public function getInsightsProperty(): array
    {
        if ($this->insightsData === []) {
            $this->refreshInsights();
        }

        return $this->insightsData;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('printOwnerReport')
                ->label('Cetak / PDF Laporan')
                ->icon('heroicon-o-printer')
                ->url(fn () => route('reports.owner-business.print', $this->reportFilters()))
                ->openUrlInNewTab(),

            Action::make('downloadOwnerReport')
                ->label('Download Laporan')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('success')
                ->action(fn () => Excel::download(
                    new OwnerBusinessReportExport($this->insightsData ?: $this->insights),
                    $this->ownerReportFilename('xlsx'),
                )),
        ];
    }

    private function refreshInsights(): void
    {
        $this->insightsData = app(BusinessInsightsCalculator::class)->calculate($this->filters ?? [], includeOperationalReports: true);
    }

    private function reportFilters(): array
    {
        return collect($this->filters ?? [])
            ->filter(fn ($value) => filled($value))
            ->all();
    }

    private function ownerReportFilename(string $extension): string
    {
        $filters = $this->insightsData['filters'] ?? [];
        $from = $filters['date_from'] ?? now()->startOfMonth()->toDateString();
        $until = $filters['date_until'] ?? now()->toDateString();

        return "laporan-bisnis-gunsas-{$from}-sd-{$until}.{$extension}";
    }
}
