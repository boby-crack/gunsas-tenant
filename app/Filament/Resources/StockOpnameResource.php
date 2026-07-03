<?php

namespace App\Filament\Resources;

use App\Filament\Resources\StockOpnameResource\Pages;
use App\Models\ProductConversion;
use App\Models\ProductReturn;
use App\Models\Production;
use App\Models\Sale;
use App\Models\Shipment;
use App\Models\StockOpname;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class StockOpnameResource extends Resource
{
    protected static ?string $model = StockOpname::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-check';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('outlet_id')
                    ->relationship('outlet', 'name')
                    ->label('Outlet')
                    ->required()
                    ->live()
                    ->afterStateUpdated(fn (Forms\Set $set, Forms\Get $get) => self::calculateTheoreticalStock($set, $get)),

                Forms\Components\Select::make('durian_variety_id')
                    ->relationship('durianVariety', 'name')
                    ->label('Varian')
                    ->required()
                    ->live()
                    ->afterStateUpdated(fn (Forms\Set $set, Forms\Get $get) => self::calculateTheoreticalStock($set, $get)),

                Forms\Components\DatePicker::make('date')
                    ->label('Tanggal Cek Fisik')
                    ->required()
                    ->default(now())
                    ->live()
                    ->afterStateUpdated(fn (Forms\Set $set, Forms\Get $get) => self::calculateTheoreticalStock($set, $get)),

                Forms\Components\Select::make('product_type')
                    ->label('Kategori Produk yang Dicek')
                    ->options([
                        'Buah Utuh' => 'Buah Utuh',
                        'Daging Fresh' => 'Daging Fresh',
                        'Daging Frozen' => 'Daging Frozen',
                    ])
                    ->required()
                    ->live()
                    ->afterStateUpdated(fn (Forms\Set $set, Forms\Get $get) => self::calculateTheoreticalStock($set, $get)),

                Forms\Components\TextInput::make('system_qty_kg')
                    ->label('Berat Seharusnya di Aplikasi (KG)')
                    ->numeric()
                    ->required()
                    ->readOnly()
                    ->placeholder('Otomatis terhitung oleh sistem...'),

                Forms\Components\TextInput::make('physical_qty_kg')
                    ->label('Timbangan Riil di Toko (KG)')
                    ->numeric()
                    ->step('0.001')
                    ->required()
                    ->live(onBlur: true)
                    ->afterStateUpdated(function ($state, Forms\Set $set, Forms\Get $get) {
                        $systemQty = (float) $get('system_qty_kg');
                        $physicalQty = (float) $state;

                        $set('difference_qty_kg', $physicalQty - $systemQty);
                    }),

                Forms\Components\TextInput::make('difference_qty_kg')
                    ->label('Selisih Berat (KG)')
                    ->numeric()
                    ->readOnly()
                    ->placeholder('Otomatis...'),

                Forms\Components\Textarea::make('notes')
                    ->label('Catatan / Keterangan Selisih')
                    ->columnSpanFull(),
            ]);
    }

    public static function calculateTheoreticalStock(Forms\Set $set, Forms\Get $get): void
    {
        $outletId = $get('outlet_id');
        $varietyId = $get('durian_variety_id');
        $productType = $get('product_type');
        $date = $get('date');

        if (! $outletId || ! $varietyId || ! $productType) {
            $set('system_qty_kg', 0);
            $set('difference_qty_kg', 0);

            return;
        }

        $theoreticalStock = match ($productType) {
            'Buah Utuh' => self::calculateWholeFruitStock((int) $outletId, (int) $varietyId, $date),
            'Daging Fresh' => self::calculateFreshStock((int) $outletId, (int) $varietyId, $date),
            'Daging Frozen' => self::calculateFrozenStock((int) $outletId, (int) $varietyId, $date),
            default => 0,
        };

        $systemQty = max(0, $theoreticalStock);
        $physicalQty = (float) $get('physical_qty_kg');

        $set('system_qty_kg', round($systemQty, 3));
        $set('difference_qty_kg', $physicalQty > 0 ? round($physicalQty - $systemQty, 3) : 0);
    }

    private static function calculateWholeFruitStock(int $outletId, int $varietyId, mixed $date = null): float
    {
        $shipmentKg = Shipment::where('outlet_id', $outletId)
            ->where('durian_variety_id', $varietyId)
            ->when($date, fn ($query) => $query->whereDate('date', '<=', $date))
            ->sum('qty_sent_kg');

        $soldKg = Sale::where('outlet_id', $outletId)
            ->where('durian_variety_id', $varietyId)
            ->when($date, fn ($query) => $query->whereDate('date', '<=', $date))
            ->sum('buah_sold_kg');

        $returnedKg = ProductReturn::where('outlet_id', $outletId)
            ->where('durian_variety_id', $varietyId)
            ->when($date, fn ($query) => $query->whereDate('date', '<=', $date))
            ->sum('qty_kg');

        $peeledKg = Production::where('outlet_id', $outletId)
            ->where('durian_variety_id', $varietyId)
            ->when($date, fn ($query) => $query->whereDate('date', '<=', $date))
            ->sum('qty_buah_kg');

        return $shipmentKg - $soldKg - $returnedKg - $peeledKg;
    }

    private static function calculateFreshStock(int $outletId, int $varietyId, mixed $date = null): float
    {
        $producedKg = Production::where('outlet_id', $outletId)
            ->where('durian_variety_id', $varietyId)
            ->when($date, fn ($query) => $query->whereDate('date', '<=', $date))
            ->sum('qty_kupas_kg');

        $soldKg = Sale::where('outlet_id', $outletId)
            ->where('durian_variety_id', $varietyId)
            ->when($date, fn ($query) => $query->whereDate('date', '<=', $date))
            ->sum('fresh_sold_kg');

        $convertedKg = ProductConversion::where('outlet_id', $outletId)
            ->where('durian_variety_id', $varietyId)
            ->where('conversion_type', 'Kupas Fresh ke Kupas Frozen')
            ->when($date, fn ($query) => $query->whereDate('date', '<=', $date))
            ->sum('from_qty_kg');

        return $producedKg - $soldKg - $convertedKg;
    }

    private static function calculateFrozenStock(int $outletId, int $varietyId, mixed $date = null): float
    {
        $producedKg = ProductConversion::where('outlet_id', $outletId)
            ->where('durian_variety_id', $varietyId)
            ->where('conversion_type', 'Kupas Fresh ke Kupas Frozen')
            ->when($date, fn ($query) => $query->whereDate('date', '<=', $date))
            ->sum('to_qty_kg');

        $soldKg = Sale::where('outlet_id', $outletId)
            ->where('durian_variety_id', $varietyId)
            ->when($date, fn ($query) => $query->whereDate('date', '<=', $date))
            ->sum('frozen_sold_kg');

        return $producedKg - $soldKg;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('outlet.name')->label('Outlet')->sortable(),
                Tables\Columns\TextColumn::make('durianVariety.name')->label('Varian')->sortable(),
                Tables\Columns\TextColumn::make('product_type')->label('Kategori'),
                Tables\Columns\TextColumn::make('system_qty_kg')->label('Buku (KG)')->numeric(3, decimalSeparator: ',', thousandsSeparator: '.'),
                Tables\Columns\TextColumn::make('physical_qty_kg')->label('Fisik Toko (KG)')->numeric(3, decimalSeparator: ',', thousandsSeparator: '.'),
                Tables\Columns\TextColumn::make('difference_qty_kg')
                    ->label('Selisih / Susut')
                    ->numeric(3, decimalSeparator: ',', thousandsSeparator: '.')
                    ->color(fn ($state) => $state < 0 ? 'danger' : 'success'),
            ])
            ->actions([Tables\Actions\EditAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListStockOpnames::route('/'),
            'create' => Pages\CreateStockOpname::route('/create'),
            'edit' => Pages\EditStockOpname::route('/{record}/edit'),
        ];
    }
}
