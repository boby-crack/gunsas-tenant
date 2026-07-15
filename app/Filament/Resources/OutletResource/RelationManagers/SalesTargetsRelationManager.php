<?php

namespace App\Filament\Resources\OutletResource\RelationManagers;

use App\Models\SalesTarget;
use App\Services\SalesTargetCalculator;
use Carbon\Carbon;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class SalesTargetsRelationManager extends RelationManager
{
    protected static string $relationship = 'salesTargets';

    protected static ?string $title = 'Target Sales Bulanan';

    protected static ?string $modelLabel = 'Target Sales';

    protected static ?string $pluralModelLabel = 'Target Sales';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Hidden::make('period_type')
                    ->default('monthly'),

                Forms\Components\DatePicker::make('period_start')
                    ->label('Bulan Target')
                    ->default(now()->startOfMonth())
                    ->required()
                    ->live()
                    ->helperText('Pilih tanggal apa pun di bulan target. Sistem otomatis pakai awal sampai akhir bulan.')
                    ->afterStateUpdated(fn ($state, Forms\Set $set, Forms\Get $get) => self::setMonthlyPeriod($set, $get)),

                Forms\Components\DatePicker::make('period_end')
                    ->label('Akhir Periode')
                    ->default(now()->endOfMonth())
                    ->required()
                    ->readOnly(),

                Forms\Components\Select::make('metric')
                    ->label('Basis Target')
                    ->options([
                        'net_sales' => 'Sales Setelah Diskon',
                        'gross_sales' => 'Gross Sales',
                        'gunsas_revenue' => 'Pendapatan Gunsas',
                    ])
                    ->default('net_sales')
                    ->required(),

                Forms\Components\TextInput::make('target_amount')
                    ->label('Target Sales')
                    ->numeric()
                    ->prefix('Rp')
                    ->required(),

                Forms\Components\Textarea::make('notes')
                    ->label('Catatan')
                    ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('period_start')
                    ->label('Bulan')
                    ->date('M Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('period_end')
                    ->label('Akhir')
                    ->date('d M Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('metric')
                    ->label('Basis')
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        'gross_sales' => 'Gross Sales',
                        'gunsas_revenue' => 'Pendapatan Gunsas',
                        default => 'Sales Setelah Diskon',
                    })
                    ->badge()
                    ->sortable(),

                Tables\Columns\TextColumn::make('target_amount')
                    ->label('Target')
                    ->money('IDR')
                    ->sortable(),

                Tables\Columns\TextColumn::make('actual_amount')
                    ->label('Realisasi')
                    ->getStateUsing(fn (SalesTarget $record) => app(SalesTargetCalculator::class)->actualForTarget($record))
                    ->money('IDR'),

                Tables\Columns\TextColumn::make('achievement')
                    ->label('Tercapai')
                    ->getStateUsing(fn (SalesTarget $record) => $record->achievementPercentage())
                    ->formatStateUsing(fn ($state) => number_format((float) $state, 1, ',', '.') . '%')
                    ->color(fn ($state) => (float) $state >= 100 ? 'success' : ((float) $state >= 80 ? 'warning' : 'danger')),

                Tables\Columns\TextColumn::make('gap')
                    ->label('Sisa / Lebih')
                    ->getStateUsing(function (SalesTarget $record) {
                        return app(SalesTargetCalculator::class)->actualForTarget($record) - $record->target_amount;
                    })
                    ->money('IDR')
                    ->color(fn ($state) => (float) $state >= 0 ? 'success' : 'danger'),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('Tambah Target Bulanan')
                    ->mutateFormDataUsing(fn (array $data) => self::normalizeMonthlyData($data)),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->mutateFormDataUsing(fn (array $data) => self::normalizeMonthlyData($data)),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    private static function setMonthlyPeriod(Forms\Set $set, Forms\Get $get): void
    {
        $start = $get('period_start');

        if (! $start) {
            return;
        }

        $date = Carbon::parse($start);

        $set('period_start', $date->copy()->startOfMonth()->toDateString());
        $set('period_end', $date->copy()->endOfMonth()->toDateString());
    }

    private static function normalizeMonthlyData(array $data): array
    {
        $date = Carbon::parse($data['period_start'] ?? now());

        $data['period_type'] = 'monthly';
        $data['period_start'] = $date->copy()->startOfMonth()->toDateString();
        $data['period_end'] = $date->copy()->endOfMonth()->toDateString();

        return $data;
    }
}
