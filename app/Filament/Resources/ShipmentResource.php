<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ShipmentResource\Pages;
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
                    ->label('Pilih Outlet')
                    ->required(),

                Select::make('durian_variety_id')
                    ->relationship('durianVariety', 'name')
                    ->label('Varian Durian')
                    ->required(),
                    
                DatePicker::make('date')
                    ->label('Tanggal Pengiriman')
                    ->required()
                    ->default(now()),
                    
                TextInput::make('modal_price')
                    ->label('Harga Modal (Per KG)')
                    ->numeric()
                    ->prefix('Rp')
                    ->required()
                    ->live(onBlur: true)
                    ->afterStateUpdated(fn (Forms\Set $set, Forms\Get $get) => self::hitungOtomatis($set, $get)),
                    
                TextInput::make('qty_sent_butir')
                    ->label('Qty Dikirim (Butir)')
                    ->numeric()
                    ->required()
                    ->live(onBlur: true)
                    ->afterStateUpdated(fn (Forms\Set $set, Forms\Get $get) => self::hitungOtomatis($set, $get)),
                    
                TextInput::make('qty_received_butir')
                    ->label('Qty Diterima (Butir)')
                    ->numeric()
                    ->required()
                    ->live(onBlur: true)
                    ->afterStateUpdated(fn (Forms\Set $set, Forms\Get $get) => self::hitungOtomatis($set, $get)),
                    
                TextInput::make('qty_sent_kg')
                    ->label('Qty Dikirim (KG)')
                    ->numeric()
                    ->step('0.001') 
                    ->required()
                    ->live(onBlur: true)
                    ->afterStateUpdated(fn (Forms\Set $set, Forms\Get $get) => self::hitungOtomatis($set, $get)),
                    
                TextInput::make('average_weight')
                    ->label('Rata-rata Berat (KG)')
                    ->numeric()
                    ->step('0.001')
                    ->readOnly()
                    ->placeholder('Otomatis terhitung...'),
                    
                TextInput::make('value_purchase')
                    ->label('Value Purchase (Total Modal)')
                    ->numeric()
                    ->prefix('Rp')
                    ->readOnly()
                    ->placeholder('Otomatis terhitung...'),
            ]);
    }

    public static function hitungOtomatis(Forms\Set $set, Forms\Get $get)
    {
        $modalPrice = floatval($get('modal_price') ?? 0);
        $qtySentButir = intval($get('qty_sent_butir') ?? 0);
        $qtySentKg = floatval($get('qty_sent_kg') ?? 0);

        if ($qtySentButir > 0) {
            $set('average_weight', round($qtySentKg / $qtySentButir, 3));
        } else {
            $set('average_weight', 0);
        }

        $set('value_purchase', round($qtySentKg * $modalPrice, 2));
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('outlet.name')->label('Outlet')->sortable(),
                TextColumn::make('durianVariety.name')->label('Varian')->sortable(),
                TextColumn::make('date')->label('Tanggal')->date()->sortable(),
                TextColumn::make('qty_sent_butir')->label('Kirim (Btr)'),
                TextColumn::make('qty_received_butir')->label('Terima (Btr)'),
                TextColumn::make('qty_difference_butir')->label('Selisih (Btr)')->color('danger'),
                TextColumn::make('average_weight')->label('Avg Berat (KG)')->numeric(3, decimalSeparator: ',', thousandsSeparator: '.'),
                TextColumn::make('value_purchase')->label('Total Modal')->money('IDR'),
            ])
            ->filters([])
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
