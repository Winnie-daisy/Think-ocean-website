<?php

namespace App\Filament\Resources\ContactSubmissions;

use App\Filament\Resources\ContactSubmissions\Pages\ListContactSubmissions;
use App\Filament\Resources\ContactSubmissions\Pages\ViewContactSubmission;
use App\Models\ContactSubmission;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

class ContactSubmissionResource extends Resource
{
    protected static ?string $model = ContactSubmission::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedInbox;

    protected static string|UnitEnum|null $navigationGroup = 'Enquiries';

    protected static ?string $navigationLabel = 'Contact messages';

    protected static ?string $modelLabel = 'contact message';

    public static function getNavigationBadge(): ?string
    {
        $count = ContactSubmission::query()->where('status', 'unread')->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Unread messages';
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Message')
                ->schema([
                    TextEntry::make('subject')->placeholder('No subject'),
                    TextEntry::make('message')->columnSpanFull()->extraAttributes(['style' => 'white-space:pre-wrap']),
                ])
                ->columnSpan(2),
            Section::make('From')
                ->schema([
                    TextEntry::make('name'),
                    TextEntry::make('email')->copyable(),
                    TextEntry::make('organisation')->placeholder('—'),
                    TextEntry::make('created_at')->label('Received')->dateTime('j M Y, H:i'),
                    TextEntry::make('status')->badge()->formatStateUsing(fn (string $state) => ContactSubmission::STATUSES[$state] ?? $state),
                    TextEntry::make('reviewer.name')->label('Reviewed by')->placeholder('—'),
                    TextEntry::make('internal_notes')->label('Internal notes')->placeholder('—'),
                ])
                ->columnSpan(1),
        ])->columns(3);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => ContactSubmission::STATUSES[$state] ?? $state)
                    ->icon(fn (string $state) => match ($state) {
                        'unread' => 'heroicon-o-envelope',
                        'reviewed' => 'heroicon-o-check-circle',
                        'spam' => 'heroicon-o-no-symbol',
                        default => 'heroicon-o-envelope-open',
                    })
                    ->color(fn (string $state) => match ($state) {
                        'unread' => 'info',
                        'reviewed' => 'success',
                        'spam' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('name')->searchable()->weight(fn (ContactSubmission $record) => $record->status === 'unread' ? 'bold' : null),
                TextColumn::make('email')->searchable()->toggleable(),
                TextColumn::make('subject')->limit(50)->placeholder('—')->searchable(),
                TextColumn::make('created_at')->label('Received')->since()->dateTimeTooltip()->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([SelectFilter::make('status')->options(ContactSubmission::STATUSES)])
            ->recordActions([ViewAction::make(), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListContactSubmissions::route('/'),
            'view' => ViewContactSubmission::route('/{record}'),
        ];
    }
}
