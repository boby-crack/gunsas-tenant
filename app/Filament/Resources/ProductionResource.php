<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProductionResource\Pages;
use App\Models\Production;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Section;
use Filament\Tables\Columns\TextColumn;

class ProductionResource extends Resource
{
    protected static ?string $model = Production::class;
    protected static ?string $navigationIcon = 'heroicon-o-beaker';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Select::make('outlet_id')->relationship('outlet', 'name')->label('Pilih Outlet')->required(),
                Select::make('durian_variety_id')->relationship('durianVariety', 'name')->label('Varian Durian')->required(),
                DatePicker::make('date')->label('Tanggal Produksi')->required()->default(now()),
                Select::make('source_type')
                    ->label('Sumber Buah')
                    ->options(Production::SOURCES)
                    ->default(Production::SOURCE_NORMAL)
                    ->required()
                    ->helperText('Pilih Buah Return kalau bahan bakunya berasal dari retur/no retur supplier.'),

                Section::make('1. Input Buah Utuh (Bahan Baku)')
                    ->schema([
                        TextInput::make('qty_buah_butir')->label('Qty Buah (Butir)')->numeric()->required(),
                        TextInput::make('qty_buah_kg')
                            ->label('Total Berat Buah Utuh (KG)')
                            ->numeric()->step('0.001')->required()->live(onBlur: true)
                            ->afterStateUpdated(fn (Forms\Set $set, Forms\Get $get) => self::hitungYieldGunsas($set, $get)),
                    ])->columns(2),

                Section::make('2. Hasil Pemilahan Daging (Output Timbangan)')
                    ->description('Masukkan hasil timbangan KG. Durian rusak/afkir tetap dimasukkan ke kolom Daging Olahan.')
                    ->schema([
                        // Kategori 1: Fresh
                        TextInput::make('qty_kupas_kg')
                            ->label('Daging KUPAS FRESH (KG)')
                            ->numeric()->step('0.001')->default(0)->live(onBlur: true)
                            ->afterStateUpdated(fn (Forms\Set $set, Forms\Get $get) => self::hitungYieldGunsas($set, $get)),
                        TextInput::make('qty_kupas_pack')->label('Kupas Fresh (Pack - Opsional)')->numeric()->default(0),

                        // Kategori 2: Olahan / Rusak
                        TextInput::make('qty_olahan_kg')
                            ->label('Daging OLAHAN / REJECT (KG)')
                            ->numeric()->step('0.001')->default(0)->live(onBlur: true)
                            ->afterStateUpdated(fn (Forms\Set $set, Forms\Get $get) => self::hitungYieldGunsas($set, $get)),
                        TextInput::make('qty_olahan_pack')->label('Daging Olahan (Pack - Opsional)')->numeric()->default(0),
                    ])->columns(2),

                Section::make('3. Hasil Analisis Efisiensi Produksi (Yield & Shrinkage)')
                    ->schema([
                        TextInput::make('total_usable_meat_kg')->label('Total Daging Diperoleh (KG)')->readOnly(),
                        TextInput::make('shrinkage_percentage')->label('Penyusutan Kulit & Biji (%)')->suffix('%')->readOnly(),
                        TextInput::make('multiplier_factor')->label('Angka Pengkali Modal')->readOnly(),
                    ])->columns(3),
            ]);
    }

    public static function hitungYieldGunsas(Forms\Set $set, Forms\Get $get)
    {
        $buahUtuhKg = floatval($get('qty_buah_kg') ?? 0);
        $kupasFreshKg = floatval($get('qty_kupas_kg') ?? 0);
        $olahanKg = floatval($get('qty_olahan_kg') ?? 0);

        // 1. Total daging didapat = Fresh + Olahan (karena yang rusak pun masuk olahan)
        $totalDaging = $kupasFreshKg + $olahanKg;
        $set('total_usable_meat_kg', round($totalDaging, 3));

        if ($buahUtuhKg > 0) {
            // 2. Penyusutan murni = hanya menghitung berat kulit dan biji yang terbuang
            $totalSusutBerat = $buahUtuhKg - $totalDaging;
            $persenSusut = ($totalSusutBerat / $buahUtuhKg) * 100;
            $set('shrinkage_percentage', round($persenSusut, 2));

            // 3. Angka pengkali modal = Berat Buah Utuh / Total Daging yang bisa dimanfaatkan
            if ($totalDaging > 0) {
                $set('multiplier_factor', round($buahUtuhKg / $totalDaging, 2));
            } else {
                $set('multiplier_factor', 0);
            }
        } else {
            $set('shrinkage_percentage', 0);
            $set('multiplier_factor', 0);
        }
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('outlet.name')->label('Outlet')->searchable()->sortable(),
                TextColumn::make('durianVariety.name')->label('Varian')->searchable()->sortable(),
                TextColumn::make('date')->label('Tanggal')->date()->sortable(),
                TextColumn::make('source_type')
                    ->label('Sumber')
                    ->badge()
                    ->formatStateUsing(fn (?string $state) => Production::SOURCES[$state ?: Production::SOURCE_NORMAL] ?? $state)
                    ->color(fn (?string $state) => $state === Production::SOURCE_RETURN ? 'warning' : 'gray')
                    ->sortable(),
                TextColumn::make('qty_buah_kg')->label('Buah Utuh (KG)')->numeric(3, decimalSeparator: ',', thousandsSeparator: '.')->sortable(),
                TextColumn::make('qty_kupas_kg')->label('Fresh (KG)')->numeric(3, decimalSeparator: ',', thousandsSeparator: '.')->sortable(),
                TextColumn::make('qty_olahan_kg')->label('Olahan/Reject (KG)')->numeric(3, decimalSeparator: ',', thousandsSeparator: '.')->sortable(),
                TextColumn::make('shrinkage_percentage')->label('Susut Kulit/Biji (%)')->suffix('%')->sortable(),
                TextColumn::make('multiplier_factor')->label('Pengkali')->sortable(),
            ])
            ->defaultSort('date', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('outlet_id')
                    ->label('Outlet')
                    ->relationship('outlet', 'name')
                    ->searchable()
                    ->preload(),

                Tables\Filters\SelectFilter::make('durian_variety_id')
                    ->label('Varian')
                    ->relationship('durianVariety', 'name')
                    ->searchable()
                    ->preload(),

                Tables\Filters\SelectFilter::make('source_type')
                    ->label('Sumber Buah')
                    ->options(Production::SOURCES),

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
            'index' => Pages\ListProductions::route('/'),
            'create' => Pages\CreateProduction::route('/create'),
            'edit' => Pages\EditProduction::route('/{record}/edit'),
        ];
    }
}
