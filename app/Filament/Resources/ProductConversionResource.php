<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProductConversionResource\Pages;
use App\Models\ProductConversion;
use Filament\Forms;
use Filament\Forms\Form;
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
                    ->options([
                        'Kupas Fresh ke Kupas Frozen' => 'Kupas Fresh ke Kupas Frozen (Umur Pajang 1-2 Hari)',
                        'Lainnya' => 'Lainnya',
                    ])
                    ->default('Kupas Fresh ke Kupas Frozen')
                    ->required(),

                Forms\Components\Section::make('Daging Fresh yang Dikurangi (Asal)')
                    ->description('Fokus utama pada timbangan KG baku, jumlah pack digunakan sebagai pembantu pelaporan.')
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
                    ->description('Timbangan setelah masuk proses pembekuan.')
                    ->schema([
                        Forms\Components\TextInput::make('to_qty_kg')
                            ->label('Berat Jadi Frozen (KG)')
                            ->numeric()
                            ->step('0.001')
                            ->required()
                            ->placeholder('Contoh: 5.245'),
                            
                        Forms\Components\TextInput::make('to_qty_pack')
                            ->label('Jumlah Pack Frozen (Pembantu)')
                            ->numeric()
                            ->default(0),
                    ])->columns(2),

                Forms\Components\TextInput::make('notes')
                    ->label('Catatan Alasan Konversi')
                    ->placeholder('Misal: Sisa display tidak habis dalam 2 hari, dipindah ke freezer.')
                    ->maxLength(255),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('outlet.name')->label('Outlet')->sortable(),
                Tables\Columns\TextColumn::make('durianVariety.name')->label('Varian')->sortable(),
                Tables\Columns\TextColumn::make('date')->label('Tanggal')->date()->sortable(),
                Tables\Columns\TextColumn::make('from_qty_kg')->label('Fresh Berkurang (KG)')->numeric(3, decimalSeparator: ',', thousandsSeparator: '.')->color('danger'),
                Tables\Columns\TextColumn::make('to_qty_kg')->label('Frozen Bertambah (KG)')->numeric(3, decimalSeparator: ',', thousandsSeparator: '.')->color('success'),
            ])
            ->filters([])
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
