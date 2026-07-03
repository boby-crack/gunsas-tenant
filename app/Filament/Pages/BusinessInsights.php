<?php

namespace App\Filament\Pages;

use App\Models\Outlet;
use App\Services\BusinessInsightsCalculator;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Pages\Page;

class BusinessInsights extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-chart-bar-square';

    protected static ?string $navigationLabel = 'Business Insights';

    protected static ?string $title = 'Business Insights';

    protected static ?int $navigationSort = 2;

    protected static string $view = 'filament.pages.business-insights';

    public ?array $filters = [];

    public function mount(): void
    {
        $this->form->fill([
            'outlet_id' => null,
            'date_from' => now()->startOfMonth()->toDateString(),
            'date_until' => now()->toDateString(),
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Filter Analisis')
                    ->schema([
                        Select::make('outlet_id')
                            ->label('Outlet')
                            ->options(Outlet::query()->orderBy('name')->pluck('name', 'id'))
                            ->placeholder('Semua Outlet')
                            ->live(),

                        DatePicker::make('date_from')
                            ->label('Tanggal Mulai')
                            ->live(),

                        DatePicker::make('date_until')
                            ->label('Tanggal Akhir')
                            ->live(),
                    ])
                    ->columns(3),
            ])
            ->statePath('filters');
    }

    public function getInsightsProperty(): array
    {
        return app(BusinessInsightsCalculator::class)->calculate($this->filters ?? []);
    }
}
