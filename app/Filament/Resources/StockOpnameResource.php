<?php

namespace App\Filament\Resources;

use App\Filament\Resources\StockOpnameResource\Pages;
use App\Models\InventoryItem;
use App\Models\StockOpname;
use App\Services\InventoryItemStockCalculator;
use App\Services\StockSnapshotCalculator;
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

                Forms\Components\Select::make('opname_mode')
                    ->label('Jenis Stock Opname')
                    ->options([
                        'durian' => 'Produk Durian',
                        'inventory' => 'Produk Inventory',
                    ])
                    ->default(fn (?StockOpname $record) => $record?->inventory_item_id ? 'inventory' : 'durian')
                    ->dehydrated(false)
                    ->required()
                    ->live()
                    ->afterStateUpdated(function ($state, Forms\Set $set, Forms\Get $get) {
                        if ($state === 'inventory') {
                            $set('durian_variety_id', null);
                            $set('product_type', 'Inventory Item');
                        } else {
                            $set('inventory_item_id', null);
                            $set('generic_consumed_qty', 0);
                            $set('generic_consumed_amount', 0);
                        }

                        self::calculateTheoreticalStock($set, $get);
                    }),

                Forms\Components\Select::make('durian_variety_id')
                    ->relationship('durianVariety', 'name')
                    ->label('Varian')
                    ->required(fn (Forms\Get $get) => $get('opname_mode') !== 'inventory')
                    ->visible(fn (Forms\Get $get) => $get('opname_mode') !== 'inventory')
                    ->live()
                    ->afterStateUpdated(fn (Forms\Set $set, Forms\Get $get) => self::calculateTheoreticalStock($set, $get)),

                Forms\Components\Select::make('inventory_item_id')
                    ->relationship(
                        'inventoryItem',
                        'name',
                        fn ($query) => $query->whereNotIn('category', InventoryItem::FRUIT_CATEGORIES)->where('is_active', true)
                    )
                    ->label('Produk Inventory')
                    ->searchable()
                    ->preload()
                    ->required(fn (Forms\Get $get) => $get('opname_mode') === 'inventory')
                    ->visible(fn (Forms\Get $get) => $get('opname_mode') === 'inventory')
                    ->live()
                    ->afterStateUpdated(function ($state, Forms\Set $set, Forms\Get $get) {
                        $item = InventoryItem::find($state);
                        $unitCost = app(InventoryItemStockCalculator::class)->averageUnitCost((int) $state);

                        $set('product_type', 'Inventory Item');
                        $set('generic_unit', $item?->unit);
                        $set('generic_unit_cost', $unitCost ?: ($item?->default_unit_cost ?? 0));

                        self::calculateTheoreticalStock($set, $get);
                    }),

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
                        'Inventory Item' => 'Inventory Item',
                    ])
                    ->required()
                    ->dehydrated()
                    ->visible(fn (Forms\Get $get) => $get('opname_mode') !== 'inventory')
                    ->live()
                    ->afterStateUpdated(fn (Forms\Set $set, Forms\Get $get) => self::calculateTheoreticalStock($set, $get)),

                Forms\Components\TextInput::make('system_qty_kg')
                    ->label(fn (Forms\Get $get) => $get('opname_mode') === 'inventory' ? 'Qty Seharusnya di Aplikasi' : 'Berat Seharusnya di Aplikasi (KG)')
                    ->numeric()
                    ->required()
                    ->readOnly()
                    ->placeholder('Otomatis terhitung oleh sistem...'),

                Forms\Components\TextInput::make('physical_qty_kg')
                    ->label(fn (Forms\Get $get) => $get('opname_mode') === 'inventory' ? 'Qty Fisik di Outlet' : 'Timbangan Riil di Toko (KG)')
                    ->numeric()
                    ->step('0.001')
                    ->required()
                    ->live(onBlur: true)
                    ->afterStateUpdated(function ($state, Forms\Set $set, Forms\Get $get) {
                        $systemQty = (float) $get('system_qty_kg');
                        $physicalQty = (float) $state;
                        $difference = $physicalQty - $systemQty;
                        $unitCost = (float) $get('generic_unit_cost');

                        $set('difference_qty_kg', $difference);

                        if ($get('opname_mode') === 'inventory') {
                            $consumedQty = max(0, $systemQty - $physicalQty);
                            $set('generic_consumed_qty', round($consumedQty, 3));
                            $set('generic_consumed_amount', round($consumedQty * $unitCost, 2));
                        }
                    }),

                Forms\Components\TextInput::make('difference_qty_kg')
                    ->label(fn (Forms\Get $get) => $get('opname_mode') === 'inventory' ? 'Selisih Qty' : 'Selisih Berat (KG)')
                    ->numeric()
                    ->readOnly()
                    ->placeholder('Otomatis...'),

                Forms\Components\TextInput::make('generic_unit')
                    ->label('Satuan')
                    ->visible(fn (Forms\Get $get) => $get('opname_mode') === 'inventory'),

                Forms\Components\TextInput::make('generic_unit_cost')
                    ->label('Modal / Satuan')
                    ->numeric()
                    ->prefix('Rp')
                    ->default(0)
                    ->visible(fn (Forms\Get $get) => $get('opname_mode') === 'inventory')
                    ->live(onBlur: true)
                    ->afterStateUpdated(fn (Forms\Set $set, Forms\Get $get) => self::calculateInventoryUsage($set, $get)),

                Forms\Components\TextInput::make('generic_consumed_qty')
                    ->label('Qty Terpakai / Hilang')
                    ->numeric()
                    ->readOnly()
                    ->default(0)
                    ->visible(fn (Forms\Get $get) => $get('opname_mode') === 'inventory'),

                Forms\Components\TextInput::make('generic_consumed_amount')
                    ->label('Biaya Inventory Terpakai')
                    ->numeric()
                    ->prefix('Rp')
                    ->readOnly()
                    ->default(0)
                    ->visible(fn (Forms\Get $get) => $get('opname_mode') === 'inventory'),

                Forms\Components\Textarea::make('notes')
                    ->label('Catatan / Keterangan Selisih')
                    ->columnSpanFull(),
            ]);
    }

    public static function calculateTheoreticalStock(Forms\Set $set, Forms\Get $get): void
    {
        $outletId = $get('outlet_id');
        $varietyId = $get('durian_variety_id');
        $inventoryItemId = $get('inventory_item_id');
        $opnameMode = $get('opname_mode');
        $productType = $get('product_type');
        $date = $get('date');

        if ($opnameMode === 'inventory') {
            if (! $outletId || ! $inventoryItemId) {
                $set('system_qty_kg', 0);
                $set('difference_qty_kg', 0);

                return;
            }

            $systemQty = app(InventoryItemStockCalculator::class)->systemQty((int) $inventoryItemId, (int) $outletId, $date);
            $physicalQty = (float) $get('physical_qty_kg');

            $set('product_type', 'Inventory Item');
            $set('system_qty_kg', round($systemQty, 3));
            $set('difference_qty_kg', $physicalQty > 0 ? round($physicalQty - $systemQty, 3) : 0);

            $unitCost = (float) ($get('generic_unit_cost') ?? 0);
            $consumedQty = max(0, $systemQty - $physicalQty);
            $set('generic_consumed_qty', round($consumedQty, 3));
            $set('generic_consumed_amount', round($consumedQty * $unitCost, 2));

            return;
        }

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

        $systemQty = $theoreticalStock;
        $physicalQty = (float) $get('physical_qty_kg');

        $set('system_qty_kg', round($systemQty, 3));
        $set('difference_qty_kg', $physicalQty > 0 ? round($physicalQty - $systemQty, 3) : 0);
    }

    public static function calculateInventoryUsage(Forms\Set $set, Forms\Get $get): void
    {
        $systemQty = (float) ($get('system_qty_kg') ?? 0);
        $physicalQty = (float) ($get('physical_qty_kg') ?? 0);
        $unitCost = (float) ($get('generic_unit_cost') ?? 0);
        $consumedQty = max(0, $systemQty - $physicalQty);

        $set('generic_consumed_qty', round($consumedQty, 3));
        $set('generic_consumed_amount', round($consumedQty * $unitCost, 2));
    }

    private static function calculateWholeFruitStock(int $outletId, int $varietyId, mixed $date = null): float
    {
        if (! $date) {
            return 0;
        }

        return app(StockSnapshotCalculator::class)->durianStockForOpnameDate((string) $date, $outletId, $varietyId, 'Buah Utuh');
    }

    private static function calculateFreshStock(int $outletId, int $varietyId, mixed $date = null): float
    {
        if (! $date) {
            return 0;
        }

        return app(StockSnapshotCalculator::class)->durianStockForOpnameDate((string) $date, $outletId, $varietyId, 'Daging Fresh');
    }

    private static function calculateFrozenStock(int $outletId, int $varietyId, mixed $date = null): float
    {
        if (! $date) {
            return 0;
        }

        return app(StockSnapshotCalculator::class)->durianStockForOpnameDate((string) $date, $outletId, $varietyId, 'Daging Frozen');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')->label('ID')->sortable()->searchable()->toggleable(),
                Tables\Columns\TextColumn::make('outlet.name')->label('Outlet')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('date')->label('Tanggal')->date()->sortable(),
                Tables\Columns\TextColumn::make('durianVariety.name')->label('Varian')->searchable()->sortable()->placeholder('-'),
                Tables\Columns\TextColumn::make('inventoryItem.name')->label('Produk')->searchable()->sortable()->placeholder('-'),
                Tables\Columns\TextColumn::make('product_type')->label('Kategori')->sortable(),
                Tables\Columns\TextColumn::make('system_qty_kg')->label('Buku (KG)')->numeric(3, decimalSeparator: ',', thousandsSeparator: '.')->sortable(),
                Tables\Columns\TextColumn::make('physical_qty_kg')->label('Fisik Toko (KG)')->numeric(3, decimalSeparator: ',', thousandsSeparator: '.')->sortable(),
                Tables\Columns\TextColumn::make('difference_qty_kg')
                    ->label('Selisih / Susut')
                    ->numeric(3, decimalSeparator: ',', thousandsSeparator: '.')
                    ->color(fn ($state) => $state < 0 ? 'danger' : 'success')
                    ->sortable(),
                Tables\Columns\TextColumn::make('generic_consumed_qty')->label('Item Terpakai')->numeric(3, decimalSeparator: ',', thousandsSeparator: '.')->sortable(),
                Tables\Columns\TextColumn::make('generic_unit')->label('Satuan')->sortable(),
                Tables\Columns\TextColumn::make('generic_consumed_amount')->label('Biaya Item')->money('IDR')->sortable(),
            ])
            ->defaultSort('date', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('outlet_id')
                    ->label('Outlet')
                    ->relationship('outlet', 'name')
                    ->multiple()
                    ->searchable()
                    ->preload(),

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

                Tables\Filters\SelectFilter::make('product_type')
                    ->label('Kategori')
                    ->multiple()
                    ->options([
                        'Buah Utuh' => 'Buah Utuh',
                        'Daging Fresh' => 'Daging Fresh',
                        'Daging Frozen' => 'Daging Frozen',
                        'Inventory Item' => 'Inventory Item',
                    ]),

                Tables\Filters\Filter::make('minus')
                    ->label('Selisih Minus')
                    ->query(fn ($query) => $query->where('difference_qty_kg', '<', 0)),

                Tables\Filters\Filter::make('date')
                    ->form([
                        Forms\Components\DatePicker::make('from')->label('Dari Tanggal'),
                        Forms\Components\DatePicker::make('until')->label('Sampai Tanggal'),
                    ])
                    ->query(fn ($query, array $data) => $query
                        ->when($data['from'] ?? null, fn ($query, $date) => $query->whereDate('date', '>=', $date))
                        ->when($data['until'] ?? null, fn ($query, $date) => $query->whereDate('date', '<=', $date))),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
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
