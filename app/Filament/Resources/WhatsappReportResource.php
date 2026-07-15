<?php

namespace App\Filament\Resources;

use App\Filament\Resources\WhatsappReportResource\Pages;
use App\Models\WhatsappReport;
use App\Services\WhatsappReportApprover;
use App\Services\WhatsappReportParser;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class WhatsappReportResource extends Resource
{
    protected static ?string $model = WhatsappReport::class;

    protected static ?string $navigationIcon = 'heroicon-o-chat-bubble-left-right';

    protected static ?string $navigationLabel = 'WA Draft Reports';

    protected static ?string $modelLabel = 'WA Draft Report';

    protected static ?string $pluralModelLabel = 'WA Draft Reports';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Pesan WhatsApp')
                    ->schema([
                        Forms\Components\TextInput::make('sender')
                            ->label('Pengirim')
                            ->maxLength(255),

                        Forms\Components\Textarea::make('raw_message')
                            ->label('Pesan Mentah')
                            ->required()
                            ->rows(5)
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Hasil Parsing')
                    ->schema([
                        Forms\Components\Select::make('report_type')
                            ->label('Jenis Laporan')
                            ->options([
                                'retur' => 'Retur',
                                'rijek' => 'Data Rijek',
                                'kupas' => 'Buah ke Kupas Fresh',
                                'frozen' => 'Kupas Fresh ke Durpas Frozen',
                                'opname' => 'Stock Opname',
                            ]),

                        Forms\Components\Select::make('status')
                            ->label('Status')
                            ->options([
                                'needs_review' => 'Needs Review',
                                'pending_approval' => 'Pending Approval',
                                'approved' => 'Approved',
                                'rejected' => 'Rejected',
                            ])
                            ->required(),

                        Forms\Components\TextInput::make('confidence')
                            ->label('Confidence')
                            ->numeric()
                            ->suffix('%'),

                        Forms\Components\Textarea::make('error_notes')
                            ->label('Catatan Error')
                            ->rows(2)
                            ->columnSpanFull(),

                        Forms\Components\Textarea::make('parsed_payload_preview')
                            ->label('Payload Terbaca')
                            ->formatStateUsing(fn (?WhatsappReport $record) => json_encode($record?->parsed_payload ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE))
                            ->disabled()
                            ->dehydrated(false)
                            ->rows(12)
                            ->columnSpanFull(),
                    ])
                    ->columns(3),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('ID')
                    ->formatStateUsing(fn (int $state) => '#' . $state)
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Masuk')
                    ->dateTime('d M Y H:i')
                    ->sortable(),

                Tables\Columns\TextColumn::make('sender')
                    ->label('Pengirim')
                    ->searchable()
                    ->sortable()
                    ->placeholder('-'),

                Tables\Columns\TextColumn::make('report_type')
                    ->label('Jenis')
                    ->badge()
                    ->sortable()
                    ->colors([
                        'warning' => 'retur',
                        'danger' => 'rijek',
                        'success' => 'kupas',
                        'info' => 'frozen',
                        'primary' => 'opname',
                    ]),

                Tables\Columns\TextColumn::make('confidence')
                    ->label('Confidence')
                    ->suffix('%')
                    ->sortable(),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->sortable()
                    ->colors([
                        'danger' => 'needs_review',
                        'warning' => 'pending_approval',
                        'success' => 'approved',
                        'gray' => 'rejected',
                    ]),

                Tables\Columns\TextColumn::make('raw_message')
                    ->label('Pesan')
                    ->limit(80)
                    ->searchable()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('report_type')
                    ->label('Jenis Laporan')
                    ->options([
                        'retur' => 'Retur',
                        'rijek' => 'Data Rijek',
                        'kupas' => 'Buah ke Kupas Fresh',
                        'frozen' => 'Kupas Fresh ke Durpas Frozen',
                        'opname' => 'Stock Opname',
                    ]),

                Tables\Filters\SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'needs_review' => 'Needs Review',
                        'pending_approval' => 'Pending Approval',
                        'approved' => 'Approved',
                        'rejected' => 'Rejected',
                    ]),

                Tables\Filters\Filter::make('high_confidence')
                    ->label('Confidence 90%+')
                    ->query(fn ($query) => $query->where('confidence', '>=', 90)),

                Tables\Filters\Filter::make('created_at')
                    ->form([
                        Forms\Components\DatePicker::make('from')->label('Masuk Dari'),
                        Forms\Components\DatePicker::make('until')->label('Masuk Sampai'),
                    ])
                    ->query(fn ($query, array $data) => $query
                        ->when($data['from'] ?? null, fn ($query, $date) => $query->whereDate('created_at', '>=', $date))
                        ->when($data['until'] ?? null, fn ($query, $date) => $query->whereDate('created_at', '<=', $date))),
            ])
            ->actions([
                Tables\Actions\Action::make('reparse')
                    ->label('Parse Ulang')
                    ->icon('heroicon-o-arrow-path')
                    ->visible(fn () => auth()->user()?->canOperate() ?? false)
                    ->action(function (WhatsappReport $record) {
                        $parsed = app(WhatsappReportParser::class)->parse($record->raw_message);

                        $record->update([
                            'report_type' => $parsed['report_type'],
                            'parsed_payload' => $parsed['parsed_payload'],
                            'confidence' => $parsed['confidence'],
                            'status' => $parsed['status'],
                            'error_notes' => $parsed['error_notes'],
                        ]);

                        Notification::make()
                            ->title('Pesan berhasil diparse ulang')
                            ->success()
                            ->send();
                    }),

                Tables\Actions\Action::make('approve')
                    ->label('Approve')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (WhatsappReport $record) => $record->status !== 'approved' && (auth()->user()?->canOperate() ?? false))
                    ->requiresConfirmation()
                    ->action(fn (WhatsappReport $record) => self::approveReport($record)),

                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function approveReport(WhatsappReport $report): void
    {
        $result = app(WhatsappReportApprover::class)->approve($report);

        if (! $result['ok']) {
            Notification::make()
                ->title('Draft belum bisa di-approve')
                ->body($result['message'])
                ->danger()
                ->send();

            return;
        }

        Notification::make()
            ->title('Draft WA berhasil dijadikan transaksi')
            ->body($result['target_label'])
            ->success()
            ->send();
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListWhatsappReports::route('/'),
            'create' => Pages\CreateWhatsappReport::route('/create'),
            'edit' => Pages\EditWhatsappReport::route('/{record}/edit'),
        ];
    }
}
