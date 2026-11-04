<?php

namespace App\Filament\Resources\ActivityLogs;

use App\Filament\Resources\ActivityLogs\Pages\ManageActivityLogs;
use App\Models\ActivityLog;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\KeyValueEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

class ActivityLogResource extends Resource
{
    protected static ?string $model = ActivityLog::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static string|UnitEnum|null $navigationGroup = 'Administration';

    protected static ?string $navigationLabel = 'Activity log';

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            TextEntry::make('created_at')->label('When')->dateTime('j M Y, H:i:s'),
            TextEntry::make('user.name')->label('User')->placeholder('System / visitor'),
            TextEntry::make('event'),
            TextEntry::make('description')->columnSpanFull(),
            TextEntry::make('ip_address')->label('IP address')->placeholder('—'),
            TextEntry::make('user_agent')->label('Browser')->placeholder('—')->columnSpanFull(),
            KeyValueEntry::make('properties')->columnSpanFull()
                ->state(fn (ActivityLog $record) => collect($record->properties ?? [])->map(fn ($v) => is_scalar($v) || $v === null ? (string) $v : json_encode($v))->all()),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')->label('When')->dateTime('j M Y, H:i')->sortable(),
                TextColumn::make('user.name')->label('User')->placeholder('System / visitor')->searchable(),
                TextColumn::make('event')->badge()->color('gray')->searchable(),
                TextColumn::make('description')->wrap()->searchable(),
            ])
            ->defaultSort('id', 'desc')
            ->filters([
                SelectFilter::make('event')->options(fn () => ActivityLog::query()->distinct()->orderBy('event')->pluck('event', 'event')->all()),
                SelectFilter::make('user_id')->label('User')->relationship('user', 'name'),
            ])
            ->recordActions([ViewAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageActivityLogs::route('/')];
    }
}
