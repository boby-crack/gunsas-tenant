<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProductConversionResource\Pages;
use App\Models\ProductConversion;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ProductConversionResource extends Resource
{
    protected static ?string $model = ProductConversion::class;
    protected static ?string $navigationIcon = 'heroicon-o-arrows-right-left';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('outlet_id')
                    ->relationship('outlet', 'name')
                    ->label('Pilih Outlet')
                    ->required(),

                Forms\Components\Select::make('durian_variety_id')
                    ->relationship('durianVariety', 'name')
                    ->label('Varian Durian')
                    ->required(),
                    
                Forms\Components\DatePicker::make('date')
                    ->label('Tanggal Pemindahan/Konversi')
                    ->required()
                    ->default(now()),
                    
                Forms\Components\Select::make('conversion_type')
                    ->label('Tipe Alur Konversi')
                    ->options(ProductConversion::conversionTypeOptions())
                    ->default(ProductConversion::TYPE_FRESH_TO_FROZEN)
                    ->live()
                    ->afterStateUpdated(function (?string $state, Set $set): void {
                        if ($state === ProductConversion::TYPE_FRESH_LOSS) {
                            $set('to_qty_kg', 0);
                            $set('to_qty_pack', 0);
                        }
                    })
                    ->required(),

                Forms\Components\Section::make('Daging Fresh yang Dikurangi (Asal)')
                    ->description('Isi jumlah fresh yang keluar dari stok, baik karena dibekukan maupun karena rusak/olahan.')
                    ->schema([
                        Forms\Components\TextInput::make('from_qty_kg')
                            ->label('Berat Daging Fresh (KG)')
                            ->numeric()
                            ->step('0.001') // Akurasi 3 angka belakang koma
                            ->required()
                            ->placeholder('Contoh: 5.250'),
                            
                        Forms\Components\TextInput::make('from_qty_pack')
                            ->label('Jumlah Pack Fresh (Pembantu)')
                            ->numeric()
                            ->default(0),
                    ])->columns(2),

                Forms\Components\Section::make('Daging Frozen yang Didapatkan (Target)')
                    ->description('Diisi hanya kalau fresh benar-benar berubah menjadi durpas frozen.')
                    ->schema([
                        Forms\Components\TextInput::make('to_qty_kg')
                            ->label('Berat Jadi Frozen (KG)')
                            ->numeric()
                            ->step('0.001')
                            ->required(fn (Get $get): bool => $get('conversion_type') === ProductConversion::TYPE_FRESH_TO_FROZEN)
                            ->default(0)
                            ->placeholder('Contoh: 5.245'),
                            
                        Forms\Components\TextInput::make('to_qty_pack')
                            ->label('Jumlah Pack Frozen (Pembantu)')
                            ->numeric()
                            ->default(0),
                    ])
                    ->visible(fn (Get $get): bool => $get('conversion_type') === ProductConversion::TYPE_FRESH_TO_FROZEN)
                    ->columns(2),

                Forms\Components\TextInput::make('notes')
                    ->label('Catatan Alasan Konversi')
                    ->placeholder('Misal: Sisa display telat frozen, jadi olahan/busuk.')
                    ->maxLength(255),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')->label('ID')->sortable()->searchable()->toggleable(),
                Tables\Columns\TextColumn::make('outlet.name')->label('Outlet')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('durianVariety.name')->label('Varian')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('date')->label('Tanggal')->date()->sortable(),
                Tables\Columns\TextColumn::make('conversion_type')->label('Tipe')->searchable()->sortable()->toggleable(),
                Tables\Columns\TextColumn::make('from_qty_kg')->label('Fresh Berkurang (KG)')->numeric(3, decimalSeparator: ',', thousandsSeparator: '.')->color('danger')->sortable(),
                Tables\Columns\TextColumn::make('to_qty_kg')->label('Frozen Bertambah (KG)')->numeric(3, decimalSeparator: ',', thousandsSeparator: '.')->color('success')->sortable(),
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

                Tables\Filters\SelectFilter::make('conversion_type')
                    ->label('Tipe Konversi')
                    ->multiple()
                    ->options(ProductConversion::conversionTypeOptions()),

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
            'index' => Pages\ListProductConversions::route('/'),
            'create' => Pages\CreateProductConversion::route('/create'),
            'edit' => Pages\EditProductConversion::route('/{record}/edit'),
        ];
    }
}
