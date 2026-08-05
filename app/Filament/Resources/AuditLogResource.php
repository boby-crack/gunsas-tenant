<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AuditLogResource\Pages;
use App\Models\AuditLog;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class AuditLogResource extends Resource
{
    protected static ?string $model = AuditLog::class;

    protected static ?string $navigationIcon = 'heroicon-o-shield-check';

    protected static ?string $navigationLabel = 'Audit Trail';

    protected static ?string $modelLabel = 'Audit Log';

    protected static ?string $pluralModelLabel = 'Audit Trail';

    protected static ?string $navigationGroup = 'System';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Waktu')
                    ->dateTime('d M Y H:i')
                    ->sortable(),

                Tables\Columns\TextColumn::make('user.name')
                    ->label('User')
                    ->placeholder('System')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('module')
                    ->label('Modul')
                    ->badge()
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('event')
                    ->label('Event')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'created' => 'success',
                        'deleted' => 'danger',
                        'status_changed' => 'warning',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        'created' => 'Created',
                        'updated' => 'Updated',
                        'deleted' => 'Deleted',
                        'status_changed' => 'Approval / Status',
                        default => str($state)->headline(),
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('summary')
                    ->label('Ringkasan')
                    ->searchable()
                    ->sortable()
                    ->wrap(),

                Tables\Columns\TextColumn::make('ip_address')
                    ->label('IP')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('module')
                    ->label('Modul')
                    ->options(fn () => AuditLog::query()
                        ->select('module')
                        ->distinct()
                        ->orderBy('module')
                        ->pluck('module', 'module')
                        ->all())
                    ->multiple(),

                Tables\Filters\SelectFilter::make('event')
                    ->label('Event')
                    ->multiple()
                    ->options([
                        'created' => 'Created',
                        'updated' => 'Updated',
                        'deleted' => 'Deleted',
                        'status_changed' => 'Approval / Status',
                    ]),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
            ])
            ->bulkActions([]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Ringkasan')
                    ->schema([
                        Infolists\Components\TextEntry::make('created_at')->label('Waktu')->dateTime('d M Y H:i:s'),
                        Infolists\Components\TextEntry::make('user.name')->label('User')->placeholder('System'),
                        Infolists\Components\TextEntry::make('module')->label('Modul'),
                        Infolists\Components\TextEntry::make('event')->label('Event'),
                        Infolists\Components\TextEntry::make('summary')->label('Ringkasan')->columnSpanFull(),
                        Infolists\Components\TextEntry::make('ip_address')->label('IP Address'),
                    ])
                    ->columns(2),

                Infolists\Components\Section::make('Perubahan Data')
                    ->schema([
                        Infolists\Components\TextEntry::make('old_values')
                            ->label('Sebelum')
                            ->formatStateUsing(fn ($state) => $state ? json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : '-')
                            ->prose()
                            ->columnSpanFull(),

                        Infolists\Components\TextEntry::make('new_values')
                            ->label('Sesudah')
                            ->formatStateUsing(fn ($state) => $state ? json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : '-')
                            ->prose()
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with('user');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAuditLogs::route('/'),
            'view' => Pages\ViewAuditLog::route('/{record}'),
        ];
    }
}
