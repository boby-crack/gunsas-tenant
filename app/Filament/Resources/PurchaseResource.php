<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PurchaseResource\Pages;
use App\Filament\Resources\PurchaseResource\RelationManagers;
use App\Models\Purchase;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class PurchaseResource extends Resource
{
    protected static ?string $model = Purchase::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
{
    return $form
        ->schema([
            Forms\Components\DatePicker::make('date')->label('Tanggal Pembelian')->required()->default(now()),
            Forms\Components\TextInput::make('supplier_code')
            ->label('Kode / Nama Supplier')
            ->placeholder('Contoh: SPL-01 / Pak Haji'),
            Forms\Components\Select::make('durian_variety_id')->relationship('durianVariety', 'name')->label('Varian Durian')->required(),
            Forms\Components\TextInput::make('supplier_name')->label('Nama Supplier / Kebun'),
            Forms\Components\TextInput::make('qty_butir')->label('Jumlah (Butir)')->numeric()->required(),
            Forms\Components\TextInput::make('qty_kg')
                ->label('Total Berat (KG)')
                ->numeric()->step('0.001')->required()
                ->live(onBlur: true)
                ->afterStateUpdated(fn ($set, $get) => $set('total_amount', floatval($get('qty_kg') ?? 0) * floatval($get('price_per_kg') ?? 0))),
            Forms\Components\TextInput::make('price_per_kg')
                ->label('Harga Beli / KG')
                ->numeric()->prefix('Rp')->required()
                ->live(onBlur: true)
                ->afterStateUpdated(fn ($set, $get) => $set('total_amount', floatval($get('qty_kg') ?? 0) * floatval($get('price_per_kg') ?? 0))),
            Forms\Components\TextInput::make('total_amount')->label('Total Nilai Nota')->numeric()->prefix('Rp')->readOnly(),
            Forms\Components\TextInput::make('notes')->label('Catatan Nota'),
        ]);
}

public static function table(Table $table): Table
{
    return $table
        ->columns([
            Tables\Columns\TextColumn::make('date')->label('Tanggal')->date()->sortable(),
            Tables\Columns\TextColumn::make('supplier_code')
            ->label('Supplier')
            ->sortable()
            ->searchable(),
            Tables\Columns\TextColumn::make('durianVariety.name')->label('Varian Durian'),
            Tables\Columns\TextColumn::make('qty_butir')->label('Butir'),
            Tables\Columns\TextColumn::make('qty_kg')->label('Berat (KG)')->numeric(3, decimalSeparator: ',', thousandsSeparator: '.'),
            Tables\Columns\TextColumn::make('total_amount')->label('Total Pengeluaran Nota')->money('IDR'),
            Tables\Columns\TextColumn::make('supplier_name')->label('Supplier'),
        ]);
}

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPurchases::route('/'),
            'create' => Pages\CreatePurchase::route('/create'),
            'edit' => Pages\EditPurchase::route('/{record}/edit'),
        ];
    }
}
