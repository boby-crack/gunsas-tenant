<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SaleResource\Pages;
use App\Models\Sale;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Section;
use Filament\Tables\Columns\TextColumn;

class SaleResource extends Resource
{
    protected static ?string $model = Sale::class;
    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Select::make('outlet_id')->relationship('outlet', 'name')->label('Pilih Outlet')->required(),
                Select::make('durian_variety_id')->relationship('durianVariety', 'name')->label('Varian Durian')->required(),
                DatePicker::make('date')->label('Tanggal Transaksi Penjualan')->required()->default(now()),

                Section::make('1. Omset BUAH UTUH (UoM: KG)')
                    ->schema([
                        TextInput::make('buah_sold_kg')->label('Berat Terjual (KG)')->numeric()->step('0.001')->default(0)->live(onBlur: true)->afterStateUpdated(fn ($set, $get) => self::hitungOmsetGunsas($set, $get)),
                        TextInput::make('buah_sold_butir')->label('Jumlah Terjual (Butir - Helper)')->numeric()->default(0),
                        TextInput::make('buah_price_per_kg')->label('Harga Jual Buah / KG')->numeric()->prefix('Rp')->default(0)->live(onBlur: true)->afterStateUpdated(fn ($set, $get) => self::hitungOmsetGunsas($set, $get)),
                        TextInput::make('buah_subtotal')->label('Subtotal Buah Utuh')->numeric()->prefix('Rp')->readOnly(),
                    ])->columns(2),

                Section::make('2. Omset KUPAS FRESH (UoM: KG)')
                    ->schema([
                        TextInput::make('fresh_sold_kg')->label('Berat Terjual (KG)')->numeric()->step('0.001')->default(0)->live(onBlur: true)->afterStateUpdated(fn ($set, $get) => self::hitungOmsetGunsas($set, $get)),
                        TextInput::make('fresh_sold_pack')->label('Jumlah Terjual (Pack - Helper)')->numeric()->default(0),
                        TextInput::make('fresh_price_per_kg')->label('Harga Jual Fresh / KG')->numeric()->prefix('Rp')->default(0)->live(onBlur: true)->afterStateUpdated(fn ($set, $get) => self::hitungOmsetGunsas($set, $get)),
                        TextInput::make('fresh_subtotal')->label('Subtotal Kupas Fresh')->numeric()->prefix('Rp')->readOnly(),
                    ])->columns(2),

                Section::make('3. Omset DURPAS FROZEN (UoM: KG)')
                    ->schema([
                        TextInput::make('frozen_sold_kg')->label('Berat Terjual (KG)')->numeric()->step('0.001')->default(0)->live(onBlur: true)->afterStateUpdated(fn ($set, $get) => self::hitungOmsetGunsas($set, $get)),
                        TextInput::make('frozen_sold_pack')->label('Jumlah Terjual (Pack - Helper)')->numeric()->default(0),
                        TextInput::make('frozen_price_per_kg')->label('Harga Jual Frozen / KG')->numeric()->prefix('Rp')->default(0)->live(onBlur: true)->afterStateUpdated(fn ($set, $get) => self::hitungOmsetGunsas($set, $get)),
                        TextInput::make('frozen_subtotal')->label('Subtotal Durpas Frozen')->numeric()->prefix('Rp')->readOnly(),
                    ])->columns(2),

                Section::make('Total Pendapatan Harian Outlet')
                    ->schema([
                        TextInput::make('grand_total_revenue')->label('Grand Total Pendapatan Kasir')->numeric()->prefix('Rp')->readOnly(),
                    ]),
            ]);
    }

    public static function hitungOmsetGunsas(Forms\Set $set, Forms\Get $get)
    {
        // 1. Hitung Buah Utuh
        $buahKg = floatval($get('buah_sold_kg') ?? 0);
        $buahPrice = floatval($get('buah_price_per_kg') ?? 0);
        $buahSub = $buahKg * $buahPrice;
        $set('buah_subtotal', $buahSub);

        // 2. Hitung Daging Fresh
        $freshKg = floatval($get('fresh_sold_kg') ?? 0);
        $freshPrice = floatval($get('fresh_price_per_kg') ?? 0);
        $freshSub = $freshKg * $freshPrice;
        $set('fresh_subtotal', $freshSub);

        // 3. Hitung Durpas Frozen
        $frozenKg = floatval($get('frozen_sold_kg') ?? 0);
        $frozenPrice = floatval($get('frozen_price_per_kg') ?? 0);
        $frozenSub = $frozenKg * $frozenPrice;
        $set('frozen_subtotal', $frozenSub);

        // 4. Kalkulasi Grand Total Akhir
        $set('grand_total_revenue', $buahSub + $freshSub + $frozenSub);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('outlet.name')->label('Outlet')->sortable(),
                TextColumn::make('durianVariety.name')->label('Varian')->sortable(),
                TextColumn::make('date')->label('Tanggal')->date()->sortable(),
                TextColumn::make('buah_sold_kg')->label('Buah Utuh (KG)')->numeric(3, decimalSeparator: ',', thousandsSeparator: '.'),
                TextColumn::make('fresh_sold_kg')->label('Daging Fresh (KG)')->numeric(3, decimalSeparator: ',', thousandsSeparator: '.'),
                TextColumn::make('frozen_sold_kg')->label('Durpas Frozen (KG)')->numeric(3, decimalSeparator: ',', thousandsSeparator: '.'),
                TextColumn::make('grand_total_revenue')->label('Total Omset')->money('IDR'),
            ])
            ->actions([Tables\Actions\EditAction::make()])
            ->bulkActions([Tables\Actions\BulkActionGroup::make([Tables\Actions\DeleteBulkAction::make()])]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSales::route('/'),
            'create' => Pages\CreateSale::route('/create'),
            'edit' => Pages\EditSale::route('/{record}/edit'),
        ];
    }
}
