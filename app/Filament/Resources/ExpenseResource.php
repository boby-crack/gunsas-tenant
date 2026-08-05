<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ExpenseResource\Pages;
use App\Filament\Resources\ExpenseResource\RelationManagers;
use App\Models\Expense;
use App\Models\Outlet;
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

    private const CATEGORIES = [
        'Bensin & Tol' => 'Bensin & Tol',
        'Parkir' => 'Parkir',
        'Logistik / Kurir' => 'Logistik / Kurir',
        'Biaya Partner / Settlement' => 'Biaya Partner / Settlement',
        'Listrik & Air' => 'Listrik & Air',
        'Gaji / Lemburan Staff' => 'Gaji / Lemburan Staff',
        'Sewa Tempat / Tenant' => 'Sewa Tempat / Tenant',
        'Perlengkapan & Packaging' => 'Perlengkapan & Packaging',
        'Lain-lain' => 'Lain-lain',
    ];

    private const ALLOCATION_SCOPES = [
        'all' => 'Semua outlet aktif',
        'group' => 'Grup outlet tertentu',
        'none' => 'Pusat saja / tidak dibagi',
    ];

    public static function form(Form $form): Form
{
    return $form
        ->schema([
            Forms\Components\DatePicker::make('date')->label('Tanggal Pengeluaran')->required()->default(now()),
            Forms\Components\Select::make('outlet_id')
                ->relationship('outlet', 'name')
                ->label('Alokasi Cabang')
                ->placeholder('Biaya Operasional Pusat / Global')
                ->live()
                ->afterStateUpdated(function (Forms\Set $set, mixed $state): void {
                    if (filled($state)) {
                        $set('allocation_scope', null);
                        $set('allocation_group', null);
                    }
                })
                ->helperText('Pilih outlet untuk beban langsung seperti parkir cabang. Kosongkan untuk biaya pusat/global.'),
            Forms\Components\Select::make('allocation_scope')
                ->label('Scope Alokasi Pusat')
                ->options(self::ALLOCATION_SCOPES)
                ->placeholder('Semua outlet aktif')
                ->visible(fn (Forms\Get $get): bool => blank($get('outlet_id')))
                ->live()
                ->afterStateUpdated(function (Forms\Set $set, mixed $state): void {
                    if ($state !== 'group') {
                        $set('allocation_group', null);
                    }
                })
                ->helperText('Kosong/semua = dibagi ke outlet aktif. Grup = hanya grup tertentu. Pusat saja = dicatat, tapi tidak dibagi ke outlet.'),
            Forms\Components\Select::make('allocation_group')
                ->label('Grup yang Dibebankan')
                ->options(Outlet::GROUPS)
                ->visible(fn (Forms\Get $get): bool => blank($get('outlet_id')) && $get('allocation_scope') === 'group')
                ->required(fn (Forms\Get $get): bool => blank($get('outlet_id')) && $get('allocation_scope') === 'group'),
            Forms\Components\Select::make('category')
                ->label('Kategori Biaya')
                ->options(self::CATEGORIES)
                ->required(),
            Forms\Components\TextInput::make('amount')->label('Nominal Biaya (Rp)')->numeric()->prefix('Rp')->required(),
            Forms\Components\TextInput::make('notes')->label('Keterangan Detail')->placeholder('Misal: Bensin kurir antar barang ke TipTop'),
        ]);
}

public static function table(Table $table): Table
{
    return $table
        ->columns([
            Tables\Columns\TextColumn::make('id')->label('ID')->sortable()->searchable()->toggleable(),
            Tables\Columns\TextColumn::make('date')->label('Tanggal')->date()->sortable(),
            Tables\Columns\TextColumn::make('outlet.name')
                ->label('Lokasi/Cabang')
                ->default('Pusat / Global')
                ->description(fn (Expense $record): string => self::allocationDescription($record))
                ->searchable()
                ->sortable(),
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
                ->multiple()
                ->searchable()
                ->preload(),

            Tables\Filters\SelectFilter::make('category')
                ->label('Kategori')
                ->multiple()
                ->options(self::CATEGORIES),

            Tables\Filters\SelectFilter::make('allocation_scope')
                ->label('Scope Pusat')
                ->multiple()
                ->options(self::ALLOCATION_SCOPES),

            Tables\Filters\SelectFilter::make('allocation_group')
                ->label('Grup Alokasi')
                ->multiple()
                ->options(Outlet::GROUPS),

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

    private static function allocationDescription(Expense $record): string
    {
        if ($record->outlet_id) {
            return 'Beban langsung outlet';
        }

        if ($record->allocation_scope === 'group') {
            $group = $record->allocation_group
                ? (Outlet::GROUPS[$record->allocation_group] ?? $record->allocation_group)
                : 'grup belum dipilih';

            return "Dibagi ke {$group}";
        }

        if ($record->allocation_scope === 'none') {
            return 'Pusat saja, tidak dibagi';
        }

        return 'Dibagi ke semua outlet aktif';
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
