<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ExpenseResource\Pages;
use App\Filament\Resources\ExpenseResource\RelationManagers;
use App\Models\Expense;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class ExpenseResource extends Resource
{
    protected static ?string $model = Expense::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

   public static function form(Form $form): Form
{
    return $form
        ->schema([
            Forms\Components\DatePicker::make('date')->label('Tanggal Pengeluaran')->required()->default(now()),
            Forms\Components\Select::make('outlet_id')
                ->relationship('outlet', 'name')
                ->label('Alokasi Cabang')
                ->placeholder('Biaya Operasional Pusat / Global'),
            Forms\Components\Select::make('category')
                ->label('Kategori Biaya')
                ->options([
                    'Bensin & Tol' => 'Bensin & Tol',
                    'Parkir' => 'Parkir',
                    'Logistik / Kurir' => 'Logistik / Kurir',
                    'Listrik & Air' => 'Listrik & Air',
                    'Gaji / Lemburan Staff' => 'Gaji / Lemburan Staff',
                    'Sewa Tempat / Tenant' => 'Sewa Tempat / Tenant',
                    'Perlengkapan & Packaging' => 'Perlengkapan & Packaging',
                    'Lain-lain' => 'Lain-lain',
                ])->required(),
            Forms\Components\TextInput::make('amount')->label('Nominal Biaya (Rp)')->numeric()->prefix('Rp')->required(),
            Forms\Components\TextInput::make('notes')->label('Keterangan Detail')->placeholder('Misal: Bensin kurir antar barang ke TipTop'),
        ]);
}

public static function table(Table $table): Table
{
    return $table
        ->columns([
            Tables\Columns\TextColumn::make('date')->label('Tanggal')->date()->sortable(),
            Tables\Columns\TextColumn::make('outlet.name')->label('Lokasi/Cabang')->default('Pusat / Global')->searchable()->sortable(),
            Tables\Columns\TextColumn::make('category')->label('Kategori')->searchable()->sortable(),
            Tables\Columns\TextColumn::make('amount')->label('Nominal')->money('IDR')->sortable(),
            Tables\Columns\TextColumn::make('notes')->label('Keterangan')->searchable()->sortable(),
        ])
        ->defaultSort('date', 'desc')
        ->filters([
            Tables\Filters\SelectFilter::make('outlet_id')
                ->label('Alokasi Cabang')
                ->relationship('outlet', 'name')
                ->placeholder('Pusat / Global')
                ->searchable()
                ->preload(),

            Tables\Filters\SelectFilter::make('category')
                ->label('Kategori')
                ->options([
                    'Bensin & Tol' => 'Bensin & Tol',
                    'Parkir' => 'Parkir',
                    'Logistik / Kurir' => 'Logistik / Kurir',
                    'Listrik & Air' => 'Listrik & Air',
                    'Gaji / Lemburan Staff' => 'Gaji / Lemburan Staff',
                    'Sewa Tempat / Tenant' => 'Sewa Tempat / Tenant',
                    'Perlengkapan & Packaging' => 'Perlengkapan & Packaging',
                    'Lain-lain' => 'Lain-lain',
                ]),

            Tables\Filters\Filter::make('pusat')
                ->label('Pusat / Global')
                ->query(fn ($query) => $query->whereNull('outlet_id')),

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
    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListExpenses::route('/'),
            'create' => Pages\CreateExpense::route('/create'),
            'edit' => Pages\EditExpense::route('/{record}/edit'),
        ];
    }
}
