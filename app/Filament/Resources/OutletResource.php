<?php

namespace App\Filament\Resources;

use App\Filament\Resources\OutletResource\Pages;
use App\Models\Outlet;
use App\Models\Shipment;
use App\Models\Production;
use App\Models\ProductReturn;
use App\Models\ProductConversion;
use App\Models\Sale;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Tables\Columns\TextColumn;

class OutletResource extends Resource
{
    protected static ?string $model = Outlet::class;
    
    // Ikon toko/outlet untuk sidebar
    protected static ?string $navigationIcon = 'heroicon-o-building-storefront'; 

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('name')
                    ->label('Nama Outlet/Tenant')
                    ->required()
                    ->maxLength(255),
                    
                TextInput::make('location')
                    ->label('Lokasi / Alamat Tiptop'),

                Textarea::make('aliases')
                    ->label('Alias / Singkatan Laporan WA')
                    ->rows(4)
                    ->helperText('Isi satu alias per baris atau pisahkan dengan koma. Contoh: p bambu, pondok bamu, pondok bambu.')
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Nama Outlet')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('aliases')
                    ->label('Alias WA')
                    ->limit(35)
                    ->toggleable(),

                TextColumn::make('stok_buah_butir')
                    ->label('Sisa Buah (Butir)')
                    ->getStateUsing(function (Outlet $record) {
                        $masuk = Shipment::where('outlet_id', $record->id)->sum('qty_received_butir');
                        $kupas = Production::where('outlet_id', $record->id)->sum('qty_buah_butir');
                        $retur = ProductReturn::where('outlet_id', $record->id)->sum('qty_butir');
                        $jualUtuh = Sale::where('outlet_id', $record->id)->sum('buah_sold_butir');

                        return ($masuk - $kupas - $retur - $jualUtuh) . ' Btr';
                    })->color('warning'),

                TextColumn::make('stok_buah_kg')
                    ->label('Sisa Buah (KG)')
                    ->getStateUsing(function (Outlet $record) {
                        $masuk = Shipment::where('outlet_id', $record->id)->sum('qty_sent_kg');
                        $kupas = Production::where('outlet_id', $record->id)->sum('qty_buah_kg');
                        $retur = ProductReturn::where('outlet_id', $record->id)->sum('qty_kg');
                        $jualUtuh = Sale::where('outlet_id', $record->id)->sum('buah_sold_kg');

                        return number_format($masuk - $kupas - $retur - $jualUtuh, 3, ',', '.') . ' Kg';
                    }),

                TextColumn::make('stok_fresh_kg')
                    ->label('Stok Fresh (KG)')
                    ->getStateUsing(function (Outlet $record) {
                        $hasilKupas = Production::where('outlet_id', $record->id)->sum('qty_kupas_kg');
                        $pindahKeFrozen = ProductConversion::where('outlet_id', $record->id)
                            ->where('conversion_type', 'Kupas Fresh ke Kupas Frozen')
                            ->sum('from_qty_kg');
                        $terjual = Sale::where('outlet_id', $record->id)->sum('fresh_sold_kg');

                        return number_format($hasilKupas - $pindahKeFrozen - $terjual, 3, ',', '.') . ' Kg';
                    })->color('success'),

                TextColumn::make('stok_frozen_kg')
                    ->label('Stok Frozen (KG)')
                    ->getStateUsing(function (Outlet $record) {
                        $masukDariFresh = ProductConversion::where('outlet_id', $record->id)
                            ->where('conversion_type', 'Kupas Fresh ke Kupas Frozen')
                            ->sum('to_qty_kg');
                        $terjual = Sale::where('outlet_id', $record->id)->sum('frozen_sold_kg');

                        return number_format($masukDariFresh - $terjual, 3, ',', '.') . ' Kg';
                    })->color('info'),

                TextColumn::make('stok_olahan_kg')
                    ->label('Gudang Olahan (KG)')
                    ->getStateUsing(function (Outlet $record) {
                        $olahanMasuk = Production::where('outlet_id', $record->id)->sum('qty_olahan_kg');
                        return number_format($olahanMasuk, 3, ',', '.') . ' Kg';
                    }),
            ])
            ->filters([])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOutlets::route('/'),
            'create' => Pages\CreateOutlet::route('/create'),
            'edit' => Pages\EditOutlet::route('/{record}/edit'),
        ];
    }
}
