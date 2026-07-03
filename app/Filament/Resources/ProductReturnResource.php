<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProductReturnResource\Pages;
use App\Models\ProductReturn;
use App\Models\Purchase;
use App\Models\Shipment;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Section;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ProductReturnResource extends Resource
{
    protected static ?string $model = ProductReturn::class;

    protected static ?string $navigationIcon = 'heroicon-o-arrow-uturn-left';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Tahap 1: Data Pengajuan Retur Toko')
                    ->description('Diisi saat fisik buah rusak dilaporkan oleh outlet untuk diklaim ke supplier.')
                    ->schema([
                        Grid::make(3)->schema([
                            Forms\Components\Select::make('outlet_id')
                                ->relationship('outlet', 'name')
                                ->label('Outlet Asal')
                                ->required()
                                ->live(),

                            Forms\Components\Select::make('shipment_id')
                                ->label('Kunci ke Nota Pengiriman')
                                ->placeholder(fn ($get) => $get('outlet_id') ? 'Pilih nota kedatangan' : 'Pilih outlet asal dulu')
                                ->options(function ($get) {
                                    $outletId = $get('outlet_id');

                                    if (! $outletId) {
                                        return [];
                                    }

                                    return Shipment::where('outlet_id', $outletId)
                                        ->latest('date')
                                        ->get()
                                        ->mapWithKeys(fn ($shipment) => [
                                            $shipment->id => 'Kirim: '
                                                . \Carbon\Carbon::parse($shipment->date)->format('d M Y')
                                                . ' | Modal: Rp '
                                                . number_format($shipment->modal_price, 0, ',', '.'),
                                        ]);
                                })
                                ->required()
                                ->live(),

                            Forms\Components\Select::make('durian_variety_id')
                                ->relationship('durianVariety', 'name')
                                ->label('Varian Durian')
                                ->required(),
                        ]),

                        Grid::make(2)->schema([
                            Forms\Components\Select::make('supplier_code')
                                ->label('Supplier Asal Barang')
                                ->options(fn () => Purchase::whereNotNull('supplier_code')
                                    ->distinct()
                                    ->pluck('supplier_code', 'supplier_code')
                                    ->toArray())
                                ->searchable()
                                ->placeholder('Cari dan pilih supplier')
                                ->required(),

                            Forms\Components\TextInput::make('paint_color')
                                ->label('Warna Cat di Buah')
                                ->placeholder('Contoh: Pilox Merah / Cat Biru di Tangkai'),
                        ]),

                        Grid::make(3)->schema([
                            Forms\Components\DatePicker::make('date')
                                ->label('Tanggal Retur')
                                ->required()
                                ->default(now()),

                            Forms\Components\TextInput::make('qty_butir')
                                ->label('Qty Retur (Butir)')
                                ->numeric()
                                ->required(),

                            Forms\Components\TextInput::make('qty_kg')
                                ->label('Qty Retur (KG)')
                                ->numeric()
                                ->step('0.001')
                                ->required(),
                        ]),

                        Forms\Components\Select::make('return_reason_type')
                            ->label('Alasan Rusak')
                            ->options([
                                'Buah Rusak / Asam' => 'Buah Rusak / Asam',
                                'Buah Bangkalan' => 'Buah Bangkalan',
                            ])
                            ->required(),

                        Forms\Components\TextInput::make('detailed_reason')
                            ->label('Catatan Tambahan Detail Kerusakan'),
                    ]),

                Section::make('Tahap 2: Barang Dikirim ke Supplier')
                    ->description('Opsional, diisi kalau jumlah yang dikirim ke supplier berbeda dari laporan outlet.')
                    ->collapsible()
                    ->schema([
                        Grid::make(2)->schema([
                            Forms\Components\TextInput::make('qty_to_supplier_butir')
                                ->label('Dikirim ke Supplier (Butir)')
                                ->numeric(),

                            Forms\Components\TextInput::make('qty_to_supplier_kg')
                                ->label('Dikirim ke Supplier (KG)')
                                ->numeric()
                                ->step('0.001'),
                        ]),
                    ]),

                Section::make('Tahap 3: Hasil Verifikasi dan Refund Supplier')
                    ->description('Diisi ketika klaim sudah dijawab dan diganti oleh supplier.')
                    ->collapsible()
                    ->schema([
                        Forms\Components\Select::make('status')
                            ->label('Status Keputusan Supplier')
                            ->options([
                                'pending' => 'Menunggu Pemeriksaan Supplier',
                                'approved_by_supplier' => 'Selesai, Diterima Semua/Sebagian',
                                'rejected_by_supplier' => 'Ditolak Total oleh Supplier',
                            ])
                            ->default('pending'),

                        Grid::make(3)->schema([
                            Forms\Components\TextInput::make('supplier_accepted_qty_butir')
                                ->label('Disetujui Supplier (Butir)')
                                ->numeric(),

                            Forms\Components\TextInput::make('supplier_accepted_qty_kg')
                                ->label('Disetujui Supplier (KG)')
                                ->numeric()
                                ->step('0.001'),

                            Forms\Components\TextInput::make('refund_amount')
                                ->label('Uang Tunai / Potongan Nota yang Kembali')
                                ->numeric()
                                ->prefix('Rp'),
                        ]),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('outlet.name')
                    ->label('Outlet / Varian')
                    ->sortable()
                    ->description(fn ($record) => $record->durianVariety?->name ?? '-'),

                Tables\Columns\TextColumn::make('supplier_code')
                    ->label('Supplier / Warna Cat')
                    ->sortable()
                    ->description(fn ($record) => $record->paint_color ? 'Cat: ' . $record->paint_color : 'Tanpa warna cat'),

                Tables\Columns\TextColumn::make('date')
                    ->label('Tanggal Retur')
                    ->date('d M Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('qty_kg')
                    ->label('Total Diretur')
                    ->getStateUsing(fn ($record) => number_format($record->qty_kg, 3, ',', '.') . ' KG')
                    ->description(fn ($record) => $record->qty_butir . ' Btr (Rp '
                        . number_format($record->qty_kg * ($record->shipment?->modal_price ?? 0), 0, ',', '.')
                        . ')'),

                Tables\Columns\TextColumn::make('rejeksi_supplier')
                    ->label('Ditolak Supplier')
                    ->getStateUsing(function ($record) {
                        if ($record->status === 'pending') {
                            return 'Menunggu QC';
                        }

                        $rejectedKg = max(0, $record->qty_kg - ($record->supplier_accepted_qty_kg ?? 0));

                        return number_format($rejectedKg, 3, ',', '.') . ' KG';
                    })
                    ->color(fn ($record) => $record->status === 'pending'
                        ? 'warning'
                        : ($record->qty_kg - ($record->supplier_accepted_qty_kg ?? 0) > 0 ? 'danger' : 'gray')),

                Tables\Columns\TextColumn::make('loss_amount')
                    ->label('Rugi Bersih (Rp)')
                    ->getStateUsing(function ($record) {
                        $modalPrice = $record->shipment?->modal_price ?? 0;
                        $initialAssetValue = $record->qty_kg * $modalPrice;
                        $refundAmount = $record->refund_amount ?? 0;

                        return 'Rp ' . number_format(max(0, $initialAssetValue - $refundAmount), 0, ',', '.');
                    })
                    ->description(fn ($record) => 'Refund: Rp ' . number_format($record->refund_amount ?? 0, 0, ',', '.'))
                    ->color('danger'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProductReturns::route('/'),
            'create' => Pages\CreateProductReturn::route('/create'),
            'edit' => Pages\EditProductReturn::route('/{record}/edit'),
        ];
    }
}
