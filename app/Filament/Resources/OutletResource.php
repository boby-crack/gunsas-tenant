<?php

namespace App\Filament\Resources;

use App\Filament\Resources\OutletResource\Pages;
use App\Filament\Resources\OutletResource\RelationManagers\SalesTargetsRelationManager;
use App\Models\Outlet;
use App\Models\Shipment;
use App\Models\Production;
use App\Models\ProductConversion;
use App\Models\Sale;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;

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

                Select::make('group_name')
                    ->label('Grup Outlet')
                    ->options(Outlet::GROUPS)
                    ->placeholder('Belum dikelompokkan')
                    ->searchable(),
                    
                TextInput::make('location')
                    ->label('Lokasi / Alamat Outlet'),

                Textarea::make('aliases')
                    ->label('Alias / Singkatan Laporan WA')
                    ->rows(4)
                    ->helperText('Isi satu alias per baris atau pisahkan dengan koma. Contoh: p bambu, pondok bamu, pondok bambu.')
                    ->columnSpanFull(),

                TextInput::make('partner_share_percent')
                    ->label('Bagi Hasil Partner (%)')
                    ->numeric()
                    ->suffix('%')
                    ->default(15)
                    ->minValue(0)
                    ->maxValue(100)
                    ->required()
                    ->helperText('Isi 15 untuk TipTop, 20 untuk Total Buah. Pendapatan Gunsas otomatis 100% dikurangi angka ini.'),
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

                TextColumn::make('group_name')
                    ->label('Grup')
                    ->formatStateUsing(fn (?string $state) => $state ? (Outlet::GROUPS[$state] ?? $state) : '-')
                    ->badge()
                    ->color('gray')
                    ->sortable(),

                TextColumn::make('aliases')
                    ->label('Alias WA')
                    ->searchable()
                    ->limit(35)
                    ->toggleable(),

                TextColumn::make('partner_share_percent')
                    ->label('Bagi Hasil Partner')
                    ->formatStateUsing(fn ($state) => number_format((float) $state, 2, ',', '.') . '%')
                    ->sortable(),

                TextColumn::make('stok_buah_butir')
                    ->label('Sisa Buah (Btr Est.)')
                    ->getStateUsing(function (Outlet $record) {
                        $masuk = Shipment::where('outlet_id', $record->id)
                            ->where('shipment_direction', 'warehouse_to_outlet')
                            ->where(fn ($query) => $query->where('product_type', 'Buah Utuh')->orWhereNull('product_type'))
                            ->sum('qty_received_butir');
                        $kupas = Production::where('outlet_id', $record->id)->sum('qty_buah_butir');
                        $jualUtuh = Sale::where('outlet_id', $record->id)->sum('buah_sold_butir');

                        $salesBuahKg = Sale::where('outlet_id', $record->id)->sum('buah_sold_kg');
                        $shipmentKg = Shipment::where('outlet_id', $record->id)
                            ->where('shipment_direction', 'warehouse_to_outlet')
                            ->where(fn ($query) => $query->where('product_type', 'Buah Utuh')->orWhereNull('product_type'))
                            ->sum('qty_sent_kg');
                        $shipmentButir = Shipment::where('outlet_id', $record->id)
                            ->where('shipment_direction', 'warehouse_to_outlet')
                            ->where(fn ($query) => $query->where('product_type', 'Buah Utuh')->orWhereNull('product_type'))
                            ->sum('qty_received_butir');
                        $avgWeight = $shipmentButir > 0 ? $shipmentKg / $shipmentButir : 0;
                        $jualUtuhEstimasi = $avgWeight > 0 ? $salesBuahKg / $avgWeight : 0;

                        return number_format($masuk - $kupas - $jualUtuh - $jualUtuhEstimasi, 0, ',', '.') . ' Btr';
                    })->color('warning'),

                TextColumn::make('stok_buah_kg')
                    ->label('Sisa Buah (KG)')
                    ->getStateUsing(function (Outlet $record) {
                        $masuk = Shipment::where('outlet_id', $record->id)
                            ->where('shipment_direction', 'warehouse_to_outlet')
                            ->where(fn ($query) => $query->where('product_type', 'Buah Utuh')->orWhereNull('product_type'))
                            ->sum('qty_sent_kg');
                        $kupas = Production::where('outlet_id', $record->id)->sum('qty_buah_kg');
                        $jualUtuh = Sale::where('outlet_id', $record->id)->sum('buah_sold_kg');

                        return number_format($masuk - $kupas - $jualUtuh, 3, ',', '.') . ' Kg';
                    }),

                TextColumn::make('stok_fresh_kg')
                    ->label('Stok Fresh (KG)')
                    ->getStateUsing(function (Outlet $record) {
                        $hasilKupas = Production::where('outlet_id', $record->id)->sum('qty_kupas_kg');
                        $pindahKeFrozen = ProductConversion::where('outlet_id', $record->id)
                            ->where('conversion_type', 'Kupas Fresh ke Kupas Frozen')
                            ->sum('from_qty_kg');
                        $terjual = Sale::where('outlet_id', $record->id)->sum('fresh_sold_kg');
                        $masukShipment = Shipment::where('outlet_id', $record->id)
                            ->where('shipment_direction', 'warehouse_to_outlet')
                            ->where('product_type', 'Daging Fresh')
                            ->sum('qty_received_kg');
                        $tarikGudang = Shipment::where('outlet_id', $record->id)
                            ->where('shipment_direction', 'outlet_to_warehouse')
                            ->where('product_type', 'Daging Fresh')
                            ->sum('qty_sent_kg');

                        return number_format($hasilKupas + $masukShipment - $pindahKeFrozen - $terjual - $tarikGudang, 3, ',', '.') . ' Kg';
                    })->color('success'),

                TextColumn::make('stok_frozen_kg')
                    ->label('Stok Frozen (KG)')
                    ->getStateUsing(function (Outlet $record) {
                        $masukDariFresh = ProductConversion::where('outlet_id', $record->id)
                            ->where('conversion_type', 'Kupas Fresh ke Kupas Frozen')
                            ->sum('to_qty_kg');
                        $terjual = Sale::where('outlet_id', $record->id)->sum('frozen_sold_kg');
                        $masukShipment = Shipment::where('outlet_id', $record->id)
                            ->where('shipment_direction', 'warehouse_to_outlet')
                            ->where('product_type', 'Daging Frozen')
                            ->sum('qty_received_kg');
                        $tarikGudang = Shipment::where('outlet_id', $record->id)
                            ->where('shipment_direction', 'outlet_to_warehouse')
                            ->where('product_type', 'Daging Frozen')
                            ->sum('qty_sent_kg');

                        return number_format($masukDariFresh + $masukShipment - $terjual - $tarikGudang, 3, ',', '.') . ' Kg';
                    })->color('info'),

                TextColumn::make('stok_olahan_kg')
                    ->label('Gudang Olahan (KG)')
                    ->getStateUsing(function (Outlet $record) {
                        $olahanMasuk = Production::where('outlet_id', $record->id)->sum('qty_olahan_kg');
                        return number_format($olahanMasuk, 3, ',', '.') . ' Kg';
                    }),
            ])
            ->filters([
                SelectFilter::make('group_name')
                    ->label('Grup Outlet')
                    ->options(Outlet::GROUPS),

                Tables\Filters\Filter::make('no_group')
                    ->label('Belum Ada Grup')
                    ->query(fn ($query) => $query->whereNull('group_name')->orWhere('group_name', '')),
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

    public static function getRelations(): array
    {
        return [
            SalesTargetsRelationManager::class,
        ];
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
