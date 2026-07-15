<?php

namespace App\Filament\Resources;

use App\Filament\Resources\InventoryItemResource\Pages;
use App\Models\InventoryItem;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class InventoryItemResource extends Resource
{
    protected static ?string $model = InventoryItem::class;

    protected static ?string $navigationIcon = 'heroicon-o-cube';

    protected static ?string $navigationLabel = 'Master Produk';

    protected static ?string $modelLabel = 'Produk Inventory';

    protected static ?string $pluralModelLabel = 'Master Produk';

    protected static ?string $navigationGroup = 'Inventory';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->label('Nama Produk')
                    ->required()
                    ->maxLength(255),

                Forms\Components\TextInput::make('sku')
                    ->label('Kode / SKU')
                    ->maxLength(255),

                Forms\Components\Select::make('category')
                    ->label('Kategori')
                    ->options([
                        'buah_utuh' => 'Buah Utuh',
                        'kupas_fresh' => 'Kupas Fresh',
                        'durpas_frozen' => 'Durpas Frozen',
                        'produk_olahan' => 'Produk Olahan',
                        'packaging' => 'Packaging',
                        'stiker' => 'Stiker',
                        'bahan_baku' => 'Bahan Baku',
                        'operasional' => 'Operasional',
                        'lainnya' => 'Lainnya',
                    ])
                    ->required()
                    ->default('lainnya')
                    ->live(),

                Forms\Components\Select::make('unit')
                    ->label('Satuan')
                    ->options([
                        'kg' => 'Kg',
                        'unit' => 'Unit',
                        'pcs' => 'Pcs',
                        'pack' => 'Pack',
                        'box' => 'Box',
                        'roll' => 'Roll',
                        'lembar' => 'Lembar',
                        'botol' => 'Botol',
                        'liter' => 'Liter',
                        'gram' => 'Gram',
                        'dus' => 'Dus',
                        'karung' => 'Karung',
                    ])
                    ->required()
                    ->default('pcs'),

                Forms\Components\Select::make('durian_variety_id')
                    ->relationship('durianVariety', 'name')
                    ->label('Varian Durian')
                    ->helperText('Opsional. Isi hanya kalau produk memang perlu dilacak per varian durian.')
                    ->visible(fn (Forms\Get $get) => in_array($get('category'), InventoryItem::DURIAN_VARIANT_CATEGORIES, true)),

                Forms\Components\TextInput::make('default_unit_cost')
                    ->label('Harga Default / Satuan')
                    ->numeric()
                    ->prefix('Rp')
                    ->default(0),

                Forms\Components\Toggle::make('is_active')
                    ->label('Aktif')
                    ->default(true),

                Forms\Components\Textarea::make('notes')
                    ->label('Catatan')
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->label('Produk')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('sku')->label('SKU')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('category')->label('Kategori')->badge()->sortable(),
                Tables\Columns\TextColumn::make('unit')->label('Satuan')->badge()->sortable(),
                Tables\Columns\TextColumn::make('default_unit_cost')->label('Harga Default')->money('IDR')->sortable(),
                Tables\Columns\IconColumn::make('is_active')->label('Aktif')->boolean(),
            ])
            ->defaultSort('name')
            ->filters([
                Tables\Filters\SelectFilter::make('category')
                    ->label('Kategori')
                    ->options([
                        'buah_utuh' => 'Buah Utuh',
                        'kupas_fresh' => 'Kupas Fresh',
                        'durpas_frozen' => 'Durpas Frozen',
                        'produk_olahan' => 'Produk Olahan',
                        'packaging' => 'Packaging',
                        'stiker' => 'Stiker',
                        'bahan_baku' => 'Bahan Baku',
                        'operasional' => 'Operasional',
                        'lainnya' => 'Lainnya',
                    ]),

                Tables\Filters\SelectFilter::make('unit')
                    ->label('Satuan')
                    ->options([
                        'kg' => 'Kg',
                        'unit' => 'Unit',
                        'pcs' => 'Pcs',
                        'pack' => 'Pack',
                        'box' => 'Box',
                        'roll' => 'Roll',
                        'lembar' => 'Lembar',
                        'botol' => 'Botol',
                        'liter' => 'Liter',
                        'gram' => 'Gram',
                        'dus' => 'Dus',
                        'karung' => 'Karung',
                    ]),

                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Status Aktif')
                    ->trueLabel('Aktif')
                    ->falseLabel('Nonaktif')
                    ->native(false),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
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
            'index' => Pages\ListInventoryItems::route('/'),
            'create' => Pages\CreateInventoryItem::route('/create'),
            'edit' => Pages\EditInventoryItem::route('/{record}/edit'),
        ];
    }
}
