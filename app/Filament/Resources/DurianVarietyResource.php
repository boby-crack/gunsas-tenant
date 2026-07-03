<?php

namespace App\Filament\Resources;

use App\Filament\Resources\DurianVarietyResource\Pages;
use App\Filament\Resources\DurianVarietyResource\RelationManagers;
use App\Models\DurianVariety;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class DurianVarietyResource extends Resource
{
    protected static ?string $model = DurianVariety::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
{
    return $form->schema([
        Forms\Components\TextInput::make('name')
            ->label('Nama Varian Durian')
            ->required()
            ->placeholder('Contoh: MONTHONG / BAWOR / MASMUAR'),
    ]);
}

public static function table(Table $table): Table
{
    return $table
        ->columns([
            Tables\Columns\TextColumn::make('name')->label('Nama Varian Durian')->weight('bold'),
            
            Tables\Columns\TextColumn::make('stok_pusat_butir')
                ->label('Stok Pusat (Butir)')
                ->getStateUsing(function (DurianVariety $record) {
                    $beli = \App\Models\Purchase::where('durian_variety_id', $record->id)->sum('qty_butir');
                    $kirim = \App\Models\Shipment::where('durian_variety_id', $record->id)->sum('qty_received_butir');
                    return ($beli - $kirim) . ' Btr';
                })->color('warning'),

            Tables\Columns\TextColumn::make('stok_pusat_kg')
                ->label('Stok Pusat (KG)')
                ->getStateUsing(function (DurianVariety $record) {
                    $beli = \App\Models\Purchase::where('durian_variety_id', $record->id)->sum('qty_kg');
                    $kirim = \App\Models\Shipment::where('durian_variety_id', $record->id)->sum('qty_sent_kg');
                    return number_format($beli - $kirim, 3, ',', '.') . ' Kg';
                }),
        ]);
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
            'index' => Pages\ListDurianVarieties::route('/'),
            'create' => Pages\CreateDurianVariety::route('/create'),
            'edit' => Pages\EditDurianVariety::route('/{record}/edit'),
        ];
    }
}
