<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PurchaseResource\Pages;
use App\Models\InventoryItem;
use App\Models\Purchase;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PurchaseResource extends Resource
{
    protected static ?string $model = Purchase::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\DatePicker::make('date')
                    ->label('Tanggal Pembelian')
                    ->required()
                    ->default(now()),

                Forms\Components\Select::make('purchase_mode')
                    ->label('Jenis Pembelian')
                    ->options([
                        'durian' => 'Buah Durian',
                        'inventory' => 'Produk Inventory',
                    ])
                    ->default('durian')
                    ->required()
                    ->live(),

                Forms\Components\TextInput::make('supplier_code')
                    ->label('Kode / Nama Supplier')
                    ->placeholder('Contoh: SPL-01 / Pak Haji'),

                Forms\Components\TextInput::make('supplier_name')
                    ->label('Nama Supplier / Kebun'),

                Forms\Components\Select::make('durian_variety_id')
                    ->relationship('durianVariety', 'name')
                    ->label('Varian Durian')
                    ->required(fn (Forms\Get $get) => $get('purchase_mode') !== 'inventory')
                    ->visible(fn (Forms\Get $get) => $get('purchase_mode') !== 'inventory'),

                Forms\Components\Select::make('inventory_item_id')
                    ->relationship(
                        'inventoryItem',
                        'name',
                        fn (Builder $query) => $query->whereNotIn('category', InventoryItem::FRUIT_CATEGORIES)->where('is_active', true)
                    )
                    ->label('Produk Inventory')
                    ->searchable()
                    ->preload()
                    ->required(fn (Forms\Get $get) => $get('purchase_mode') === 'inventory')
                    ->visible(fn (Forms\Get $get) => $get('purchase_mode') === 'inventory')
                    ->live()
                    ->afterStateUpdated(function ($state, Forms\Set $set, Forms\Get $get) {
                        $item = InventoryItem::find($state);

                        $set('generic_unit', $item?->unit);
                        $set('generic_unit_cost', $item?->default_unit_cost ?? 0);
                        self::calculateGenericTotal($set, $get);
                    }),

                Forms\Components\TextInput::make('qty_butir')
                    ->label('Jumlah (Butir)')
                    ->numeric()
                    ->default(0)
                    ->required(fn (Forms\Get $get) => $get('purchase_mode') !== 'inventory')
                    ->visible(fn (Forms\Get $get) => $get('purchase_mode') !== 'inventory'),

                Forms\Components\TextInput::make('qty_kg')
                    ->label('Total Berat (KG)')
                    ->numeric()
                    ->step('0.001')
                    ->default(0)
                    ->required(fn (Forms\Get $get) => $get('purchase_mode') !== 'inventory')
                    ->visible(fn (Forms\Get $get) => $get('purchase_mode') !== 'inventory')
                    ->live(onBlur: true)
                    ->afterStateUpdated(fn ($set, $get) => $set('total_amount', floatval($get('qty_kg') ?? 0) * floatval($get('price_per_kg') ?? 0))),

                Forms\Components\TextInput::make('price_per_kg')
                    ->label('Harga Beli / KG')
                    ->numeric()
                    ->prefix('Rp')
                    ->default(0)
                    ->required(fn (Forms\Get $get) => $get('purchase_mode') !== 'inventory')
                    ->visible(fn (Forms\Get $get) => $get('purchase_mode') !== 'inventory')
                    ->live(onBlur: true)
                    ->afterStateUpdated(fn ($set, $get) => $set('total_amount', floatval($get('qty_kg') ?? 0) * floatval($get('price_per_kg') ?? 0))),

                Forms\Components\TextInput::make('total_amount')
                    ->label('Total Nilai Nota')
                    ->numeric()
                    ->prefix('Rp')
                    ->default(0)
                    ->readOnly()
                    ->visible(fn (Forms\Get $get) => $get('purchase_mode') !== 'inventory'),

                Forms\Components\TextInput::make('generic_qty')
                    ->label('Jumlah Dibeli')
                    ->numeric()
                    ->step('0.001')
                    ->default(0)
                    ->required(fn (Forms\Get $get) => $get('purchase_mode') === 'inventory')
                    ->visible(fn (Forms\Get $get) => $get('purchase_mode') === 'inventory')
                    ->live(onBlur: true)
                    ->afterStateUpdated(fn (Forms\Set $set, Forms\Get $get) => self::calculateGenericTotal($set, $get)),

                Forms\Components\TextInput::make('generic_unit')
                    ->label('Satuan')
                    ->readOnly()
                    ->visible(fn (Forms\Get $get) => $get('purchase_mode') === 'inventory'),

                Forms\Components\TextInput::make('generic_unit_cost')
                    ->label('Harga / Satuan')
                    ->numeric()
                    ->prefix('Rp')
                    ->default(0)
                    ->required(fn (Forms\Get $get) => $get('purchase_mode') === 'inventory')
                    ->visible(fn (Forms\Get $get) => $get('purchase_mode') === 'inventory')
                    ->live(onBlur: true)
                    ->afterStateUpdated(fn (Forms\Set $set, Forms\Get $get) => self::calculateGenericTotal($set, $get)),

                Forms\Components\TextInput::make('generic_total_amount')
                    ->label('Total Nilai Nota')
                    ->numeric()
                    ->prefix('Rp')
                    ->default(0)
                    ->readOnly()
                    ->visible(fn (Forms\Get $get) => $get('purchase_mode') === 'inventory'),

                Forms\Components\TextInput::make('notes')->label('Catatan Nota'),
            ]);
    }

    public static function calculateGenericTotal(Forms\Set $set, Forms\Get $get): void
    {
        $set('generic_total_amount', round((float) ($get('generic_qty') ?? 0) * (float) ($get('generic_unit_cost') ?? 0), 2));
    }

public static function table(Table $table): Table
{
    return $table
        ->columns([
            Tables\Columns\TextColumn::make('id')->label('ID')->sortable()->searchable()->toggleable(),
            Tables\Columns\TextColumn::make('date')->label('Tanggal')->date()->sortable(),
            Tables\Columns\TextColumn::make('purchase_mode')->label('Jenis')->badge()->sortable(),
            Tables\Columns\TextColumn::make('supplier_code')
                ->label('Supplier')
                ->sortable()
                ->searchable(),
            Tables\Columns\TextColumn::make('durianVariety.name')->label('Varian Durian')->placeholder('-')->searchable()->sortable(),
            Tables\Columns\TextColumn::make('inventoryItem.name')->label('Produk')->placeholder('-')->searchable()->sortable(),
            Tables\Columns\TextColumn::make('qty_butir')->label('Butir')->sortable(),
            Tables\Columns\TextColumn::make('qty_kg')->label('Berat (KG)')->numeric(3, decimalSeparator: ',', thousandsSeparator: '.')->sortable(),
            Tables\Columns\TextColumn::make('generic_qty')->label('Qty Item')->numeric(3, decimalSeparator: ',', thousandsSeparator: '.')->sortable(),
            Tables\Columns\TextColumn::make('generic_unit')->label('Satuan')->sortable(),
            Tables\Columns\TextColumn::make('total_amount')->label('Total Durian')->money('IDR')->sortable(),
            Tables\Columns\TextColumn::make('generic_total_amount')->label('Total Item')->money('IDR')->sortable(),
            Tables\Columns\TextColumn::make('supplier_name')->label('Nama Supplier')->searchable()->sortable(),
        ])
        ->defaultSort('date', 'desc')
        ->filters([
            Tables\Filters\SelectFilter::make('purchase_mode')
                ->label('Jenis Pembelian')
                ->multiple()
                ->options([
                    'durian' => 'Buah Durian',
                    'inventory' => 'Produk Inventory',
                ]),

            Tables\Filters\SelectFilter::make('durian_variety_id')
                ->label('Varian')
                ->relationship('durianVariety', 'name')
                ->multiple()
                ->searchable()
                ->preload(),

            Tables\Filters\SelectFilter::make('inventory_item_id')
                ->label('Produk Inventory')
                ->relationship('inventoryItem', 'name')
                ->multiple()
                ->searchable()
                ->preload(),

            Tables\Filters\SelectFilter::make('supplier_code')
                ->label('Supplier')
                ->options(fn () => Purchase::query()
                    ->whereNotNull('supplier_code')
                    ->orderBy('supplier_code')
                    ->pluck('supplier_code', 'supplier_code')
                    ->all())
                ->multiple()
                ->searchable(),

            Tables\Filters\Filter::make('date')
                ->form([
                    Forms\Components\DatePicker::make('from')->label('Dari Tanggal'),
                    Forms\Components\DatePicker::make('until')->label('Sampai Tanggal'),
                ])
                ->query(fn ($query, array $data) => $query
                    ->when($data['from'] ?? null, fn ($query, $date) => $query->whereDate('date', '>=', $date))
                    ->when($data['until'] ?? null, fn ($query, $date) => $query->whereDate('date', '<=', $date))),
        ])
        ->actions([Tables\Actions\EditAction::make()])
        ->bulkActions([Tables\Actions\BulkActionGroup::make([Tables\Actions\DeleteBulkAction::make()])]);
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
