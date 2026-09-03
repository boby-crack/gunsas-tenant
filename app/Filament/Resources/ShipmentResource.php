<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ShipmentResource\Pages;
use App\Models\InventoryItem;
use App\Models\Shipment;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Database\Eloquent\Builder;

class ShipmentResource extends Resource
{
    protected static ?string $model = Shipment::class;

    protected static ?string $navigationIcon = 'heroicon-o-truck';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Select::make('outlet_id')
                    ->relationship('outlet', 'name')
                    ->label(fn (Forms\Get $get) => $get('shipment_direction') === 'outlet_to_warehouse' ? 'Outlet Asal' : 'Pilih Outlet')
                    ->required(),

                Select::make('shipment_mode')
                    ->label('Jenis Pengiriman')
                    ->options([
                        'durian' => 'Buah Durian',
                        'inventory' => 'Produk Inventory',
                    ])
                    ->default('durian')
                    ->required()
                    ->live(),

                Select::make('shipment_direction')
                    ->label('Arah Pengiriman')
                    ->options([
                        'warehouse_to_outlet' => 'Gudang Besar ke Outlet',
                        'outlet_to_warehouse' => 'Outlet ke Gudang Besar',
                    ])
                    ->default('warehouse_to_outlet')
                    ->required()
                    ->live(),

                Select::make('product_type')
                    ->label('Kategori Produk Durian')
                    ->options([
                        'Buah Utuh' => 'Buah Utuh',
                        'Daging Fresh' => 'Kupas Fresh',
                        'Daging Frozen' => 'Durpas Frozen',
                    ])
                    ->default('Buah Utuh')
                    ->required(fn (Forms\Get $get) => $get('shipment_mode') !== 'inventory')
                    ->visible(fn (Forms\Get $get) => $get('shipment_mode') !== 'inventory')
                    ->live(),

                Select::make('durian_variety_id')
                    ->relationship('durianVariety', 'name')
                    ->label('Varian Durian')
                    ->required(fn (Forms\Get $get) => $get('shipment_mode') !== 'inventory')
                    ->visible(fn (Forms\Get $get) => $get('shipment_mode') !== 'inventory'),

                Select::make('inventory_item_id')
                    ->relationship(
                        'inventoryItem',
                        'name',
                        fn (Builder $query) => $query->whereNotIn('category', InventoryItem::FRUIT_CATEGORIES)->where('is_active', true)
                    )
                    ->label('Produk Inventory')
                    ->searchable()
                    ->preload()
                    ->required(fn (Forms\Get $get) => $get('shipment_mode') === 'inventory')
                    ->visible(fn (Forms\Get $get) => $get('shipment_mode') === 'inventory')
                    ->live()
                    ->afterStateUpdated(function ($state, Forms\Set $set, Forms\Get $get) {
                        $item = InventoryItem::find($state);

                        $set('generic_unit', $item?->unit);
                        $set('generic_unit_cost', $item?->default_unit_cost ?? 0);
                        self::hitungInventory($set, $get);
                    }),
                    
                DatePicker::make('date')
                    ->label('Tanggal Pengiriman')
                    ->required()
                    ->default(now()),
                    
                TextInput::make('modal_price')
                    ->label('Harga Modal (Per KG)')
                    ->numeric()
                    ->prefix('Rp')
                    ->default(0)
                    ->required(fn (Forms\Get $get) => $get('shipment_mode') !== 'inventory' && $get('product_type') === 'Buah Utuh')
                    ->visible(fn (Forms\Get $get) => $get('shipment_mode') !== 'inventory' && $get('product_type') === 'Buah Utuh')
                    ->live(onBlur: true)
                    ->afterStateUpdated(fn (Forms\Set $set, Forms\Get $get) => self::hitungOtomatis($set, $get)),
                    
                TextInput::make('qty_sent_butir')
                    ->label('Qty Dikirim (Butir)')
                    ->numeric()
                    ->default(0)
                    ->required(fn (Forms\Get $get) => $get('shipment_mode') !== 'inventory' && $get('product_type') === 'Buah Utuh')
                    ->visible(fn (Forms\Get $get) => $get('shipment_mode') !== 'inventory' && $get('product_type') === 'Buah Utuh')
                    ->live(onBlur: true)
                    ->afterStateUpdated(fn (Forms\Set $set, Forms\Get $get) => self::hitungOtomatis($set, $get)),
                    
                TextInput::make('qty_received_butir')
                    ->label('Qty Diterima (Butir)')
                    ->numeric()
                    ->default(0)
                    ->required(fn (Forms\Get $get) => $get('shipment_mode') !== 'inventory' && $get('product_type') === 'Buah Utuh')
                    ->visible(fn (Forms\Get $get) => $get('shipment_mode') !== 'inventory' && $get('product_type') === 'Buah Utuh')
                    ->live(onBlur: true)
                    ->afterStateUpdated(fn (Forms\Set $set, Forms\Get $get) => self::hitungOtomatis($set, $get)),
                    
                TextInput::make('qty_sent_kg')
                    ->label(fn (Forms\Get $get) => $get('shipment_direction') === 'outlet_to_warehouse' ? 'Qty Ditarik dari Outlet (KG)' : 'Qty Dikirim (KG)')
                    ->numeric()
                    ->step('0.001') 
                    ->default(0)
                    ->required(fn (Forms\Get $get) => $get('shipment_mode') !== 'inventory')
                    ->visible(fn (Forms\Get $get) => $get('shipment_mode') !== 'inventory')
                    ->live(onBlur: true)
                    ->afterStateUpdated(fn (Forms\Set $set, Forms\Get $get) => self::hitungOtomatis($set, $get)),

                TextInput::make('qty_received_kg')
                    ->label(fn (Forms\Get $get) => $get('shipment_direction') === 'outlet_to_warehouse' ? 'Qty Diterima Gudang (KG)' : 'Qty Diterima Outlet (KG)')
                    ->numeric()
                    ->step('0.001')
                    ->default(0)
                    ->required(fn (Forms\Get $get) => $get('shipment_mode') !== 'inventory')
                    ->visible(fn (Forms\Get $get) => $get('shipment_mode') !== 'inventory')
                    ->live(onBlur: true)
                    ->afterStateUpdated(fn (Forms\Set $set, Forms\Get $get) => self::hitungOtomatis($set, $get)),
                    
                TextInput::make('average_weight')
                    ->label('Rata-rata Berat (KG)')
                    ->numeric()
                    ->step('0.001')
                    ->default(0)
                    ->readOnly()
                    ->placeholder('Otomatis terhitung...')
                    ->visible(fn (Forms\Get $get) => $get('shipment_mode') !== 'inventory' && $get('product_type') === 'Buah Utuh'),
                    
                TextInput::make('value_purchase')
                    ->label('Value Purchase (Total Modal)')
                    ->numeric()
                    ->prefix('Rp')
                    ->default(0)
                    ->readOnly()
                    ->placeholder('Otomatis terhitung...')
                    ->visible(fn (Forms\Get $get) => $get('shipment_mode') !== 'inventory' && $get('product_type') === 'Buah Utuh'),

                TextInput::make('generic_qty_sent')
                    ->label('Qty Dikirim')
                    ->numeric()
                    ->step('0.001')
                    ->default(0)
                    ->required(fn (Forms\Get $get) => $get('shipment_mode') === 'inventory')
                    ->visible(fn (Forms\Get $get) => $get('shipment_mode') === 'inventory')
                    ->live(onBlur: true)
                    ->afterStateUpdated(fn (Forms\Set $set, Forms\Get $get) => self::hitungInventory($set, $get)),

                TextInput::make('generic_qty_received')
                    ->label('Qty Diterima Outlet')
                    ->numeric()
                    ->step('0.001')
                    ->default(0)
                    ->required(fn (Forms\Get $get) => $get('shipment_mode') === 'inventory')
                    ->visible(fn (Forms\Get $get) => $get('shipment_mode') === 'inventory')
                    ->live(onBlur: true)
                    ->afterStateUpdated(fn (Forms\Set $set, Forms\Get $get) => self::hitungInventory($set, $get)),

                TextInput::make('generic_unit')
                    ->label('Satuan')
                    ->readOnly()
                    ->visible(fn (Forms\Get $get) => $get('shipment_mode') === 'inventory'),

                TextInput::make('generic_unit_cost')
                    ->label('Harga Modal / Satuan')
                    ->numeric()
                    ->prefix('Rp')
                    ->default(0)
                    ->required(fn (Forms\Get $get) => $get('shipment_mode') === 'inventory')
                    ->readOnly()
                    ->visible(fn (Forms\Get $get) => $get('shipment_mode') === 'inventory')
                    ->live(onBlur: true)
                    ->afterStateUpdated(fn (Forms\Set $set, Forms\Get $get) => self::hitungInventory($set, $get)),

                TextInput::make('generic_total_amount')
                    ->label('Total Modal Item')
                    ->numeric()
                    ->prefix('Rp')
                    ->default(0)
                    ->readOnly()
                    ->visible(fn (Forms\Get $get) => $get('shipment_mode') === 'inventory'),
            ]);
    }

    public static function hitungOtomatis(Forms\Set $set, Forms\Get $get)
    {
        $modalPrice = floatval($get('modal_price') ?? 0);
        $qtySentButir = intval($get('qty_sent_butir') ?? 0);
        $qtySentKg = floatval($get('qty_sent_kg') ?? 0);
        $productType = $get('product_type') ?? 'Buah Utuh';

        if ($productType === 'Buah Utuh' && $qtySentButir > 0) {
            $set('average_weight', round($qtySentKg / $qtySentButir, 3));
        } else {
            $set('average_weight', 0);
        }

        $set('value_purchase', round($qtySentKg * $modalPrice, 2));

        if ((float) ($get('qty_received_kg') ?? 0) <= 0) {
            $set('qty_received_kg', $qtySentKg);
        }
    }

    public static function hitungInventory(Forms\Set $set, Forms\Get $get): void
    {
        $qtySent = (float) ($get('generic_qty_sent') ?? 0);
        $qtyReceived = (float) ($get('generic_qty_received') ?? 0);
        $unitCost = (float) ($get('generic_unit_cost') ?? 0);

        if ($qtyReceived <= 0 && $qtySent > 0) {
            $qtyReceived = $qtySent;
            $set('generic_qty_received', $qtyReceived);
        }

        $set('generic_total_amount', round($qtyReceived * $unitCost, 2));
    }

    private static function formatQuantity(float $value, int $decimals = 3): string
    {
        return number_format($value, $decimals, ',', '.');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')->label('ID')->sortable()->searchable()->toggleable(),
                TextColumn::make('outlet.name')->label('Outlet')->searchable()->sortable(),
                TextColumn::make('shipment_mode')
                    ->label('Jenis')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => $state === 'inventory' ? 'Produk Inventory' : 'Buah Durian')
                    ->sortable(),
                TextColumn::make('shipment_direction')
                    ->label('Arah')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => $state === 'outlet_to_warehouse' ? 'Outlet -> Gudang' : 'Gudang -> Outlet')
                    ->color(fn (?string $state): string => $state === 'outlet_to_warehouse' ? 'warning' : 'success')
                    ->sortable(),
                TextColumn::make('product_display')
                    ->label('Produk')
                    ->state(fn (Shipment $record): string => $record->shipment_mode === 'inventory'
                        ? ($record->inventoryItem?->name ?? '-')
                        : (($record->product_type ?: 'Buah Utuh') . ' ' . ($record->durianVariety?->name ?? '-')))
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query
                            ->whereHas('durianVariety', fn (Builder $query) => $query->where('name', 'like', "%{$search}%"))
                            ->orWhereHas('inventoryItem', fn (Builder $query) => $query->where('name', 'like', "%{$search}%"));
                    })
                    ->sortable(query: fn (Builder $query, string $direction): Builder => $query
                        ->orderBy('shipment_mode', $direction)
                        ->orderBy('product_type', $direction)
                        ->orderBy('durian_variety_id', $direction)
                        ->orderBy('inventory_item_id', $direction)),
                TextColumn::make('date')->label('Tanggal')->date()->sortable(),
                TextColumn::make('qty_sent_display')
                    ->label('Qty Kirim')
                    ->state(fn (Shipment $record): string => $record->shipment_mode === 'inventory'
                        ? self::formatQuantity((float) $record->generic_qty_sent) . ' ' . ($record->generic_unit ?: '-')
                        : (($record->product_type ?: 'Buah Utuh') === 'Buah Utuh'
                            ? self::formatQuantity((float) $record->qty_sent_butir, 0) . ' btr / ' . self::formatQuantity((float) $record->qty_sent_kg) . ' Kg'
                            : self::formatQuantity((float) $record->qty_sent_kg) . ' Kg'))
                    ->sortable(query: fn (Builder $query, string $direction): Builder => $query
                        ->orderByRaw("CASE WHEN shipment_mode = 'inventory' THEN generic_qty_sent ELSE qty_sent_kg END {$direction}")),
                TextColumn::make('qty_received_display')
                    ->label('Qty Terima')
                    ->state(fn (Shipment $record): string => $record->shipment_mode === 'inventory'
                        ? self::formatQuantity((float) $record->generic_qty_received) . ' ' . ($record->generic_unit ?: '-')
                        : (($record->product_type ?: 'Buah Utuh') === 'Buah Utuh'
                            ? self::formatQuantity((float) $record->qty_received_butir, 0) . ' btr / ' . self::formatQuantity((float) ($record->qty_received_kg ?: $record->qty_sent_kg)) . ' Kg'
                            : self::formatQuantity((float) ($record->qty_received_kg ?: $record->qty_sent_kg)) . ' Kg'))
                    ->sortable(query: fn (Builder $query, string $direction): Builder => $query
                        ->orderByRaw("CASE WHEN shipment_mode = 'inventory' THEN generic_qty_received WHEN COALESCE(product_type, 'Buah Utuh') = 'Buah Utuh' THEN qty_received_butir ELSE COALESCE(NULLIF(qty_received_kg, 0), qty_sent_kg) END {$direction}")),
                TextColumn::make('unit_display')
                    ->label('Satuan')
                    ->state(fn (Shipment $record): string => $record->shipment_mode === 'inventory' ? ($record->generic_unit ?: '-') : (($record->product_type ?: 'Buah Utuh') === 'Buah Utuh' ? 'butir / kg' : 'kg'))
                    ->sortable(query: fn (Builder $query, string $direction): Builder => $query
                        ->orderByRaw("CASE WHEN shipment_mode = 'inventory' THEN generic_unit WHEN COALESCE(product_type, 'Buah Utuh') = 'Buah Utuh' THEN 'butir / kg' ELSE 'kg' END {$direction}")),
                TextColumn::make('difference_display')
                    ->label('Selisih')
                    ->state(function (Shipment $record): string {
                        $difference = $record->shipment_mode === 'inventory'
                            ? (float) $record->generic_qty_received - (float) $record->generic_qty_sent
                            : (($record->product_type ?: 'Buah Utuh') === 'Buah Utuh'
                                ? min(
                                    (float) $record->qty_received_butir - (float) $record->qty_sent_butir,
                                    (float) ($record->qty_received_kg ?: $record->qty_sent_kg) - (float) $record->qty_sent_kg
                                )
                                : (float) ($record->qty_received_kg ?: $record->qty_sent_kg) - (float) $record->qty_sent_kg);

                        if ($record->shipment_mode !== 'inventory' && ($record->product_type ?: 'Buah Utuh') === 'Buah Utuh') {
                            $differenceButir = (float) $record->qty_received_butir - (float) $record->qty_sent_butir;
                            $differenceKg = (float) ($record->qty_received_kg ?: $record->qty_sent_kg) - (float) $record->qty_sent_kg;

                            return self::formatQuantity($differenceButir, 0) . ' btr / ' . self::formatQuantity($differenceKg) . ' Kg';
                        }

                        return self::formatQuantity($difference, 3);
                    })
                    ->color(fn (Shipment $record): string => (
                        $record->shipment_mode === 'inventory'
                            ? (float) $record->generic_qty_received - (float) $record->generic_qty_sent
                            : (($record->product_type ?: 'Buah Utuh') === 'Buah Utuh'
                                ? min(
                                    (float) $record->qty_received_butir - (float) $record->qty_sent_butir,
                                    (float) ($record->qty_received_kg ?: $record->qty_sent_kg) - (float) $record->qty_sent_kg
                                )
                                : (float) ($record->qty_received_kg ?: $record->qty_sent_kg) - (float) $record->qty_sent_kg)
                    ) < 0 ? 'danger' : 'gray')
                    ->sortable(query: fn (Builder $query, string $direction): Builder => $query
                        ->orderByRaw("CASE WHEN shipment_mode = 'inventory' THEN generic_qty_received - generic_qty_sent WHEN COALESCE(product_type, 'Buah Utuh') = 'Buah Utuh' THEN qty_received_butir - qty_sent_butir ELSE COALESCE(NULLIF(qty_received_kg, 0), qty_sent_kg) - qty_sent_kg END {$direction}")),
                TextColumn::make('average_weight')
                    ->label('Avg Berat')
                    ->state(fn (Shipment $record): string => $record->shipment_mode === 'inventory' ? '-' : self::formatQuantity((float) $record->average_weight) . ' Kg')
                    ->sortable(),
                TextColumn::make('total_modal_display')
                    ->label('Total Modal')
                    ->money('IDR')
                    ->state(fn (Shipment $record): float => $record->shipment_mode === 'inventory'
                        ? (float) $record->generic_total_amount
                        : (float) $record->value_purchase)
                    ->sortable(query: fn (Builder $query, string $direction): Builder => $query
                        ->orderByRaw("CASE WHEN shipment_mode = 'inventory' THEN generic_total_amount ELSE value_purchase END {$direction}")),
            ])
            ->defaultSort('date', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('outlet_id')
                    ->label('Outlet')
                    ->relationship('outlet', 'name')
                    ->multiple()
                    ->searchable()
                    ->preload(),

                Tables\Filters\SelectFilter::make('shipment_mode')
                    ->label('Jenis')
                    ->multiple()
                    ->options([
                        'durian' => 'Buah Durian',
                        'inventory' => 'Produk Inventory',
                    ]),

                Tables\Filters\SelectFilter::make('shipment_direction')
                    ->label('Arah')
                    ->multiple()
                    ->options([
                        'warehouse_to_outlet' => 'Gudang Besar ke Outlet',
                        'outlet_to_warehouse' => 'Outlet ke Gudang Besar',
                    ]),

                Tables\Filters\SelectFilter::make('product_type')
                    ->label('Kategori Durian')
                    ->multiple()
                    ->options([
                        'Buah Utuh' => 'Buah Utuh',
                        'Daging Fresh' => 'Kupas Fresh',
                        'Daging Frozen' => 'Durpas Frozen',
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

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListShipments::route('/'),
            'create' => Pages\CreateShipment::route('/create'),
            'edit' => Pages\EditShipment::route('/{record}/edit'),
        ];
    }
}
