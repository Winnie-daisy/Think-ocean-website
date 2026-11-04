<?php

namespace App\Filament\RelationManagers;

use App\Models\ContentRevision;
use Filament\Actions\Action;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/** Read-only revision history for publishable content. */
class RevisionsRelationManager extends RelationManager
{
    protected static string $relationship = 'revisions';

    protected static ?string $title = 'Revision history';

    public function isReadOnly(): bool
    {
        return true;
    }

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return auth()->user()?->can('viewRevisions', $ownerRecord) ?? false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('created_at')->label('When')->dateTime('j M Y, H:i')->sortable(),
                TextColumn::make('action')->badge()->color('gray'),
                TextColumn::make('user.name')->label('By')->placeholder('System'),
                TextColumn::make('comment')->limit(80)->wrap()->placeholder('—'),
                TextColumn::make('changed_fields')
                    ->label('Fields changed')
                    ->state(fn (ContentRevision $record) => implode(', ', array_keys($record->changes())) ?: '—')
                    ->limit(60),
            ])
            ->recordActions([
                Action::make('details')
                    ->label('Compare')
                    ->icon(Heroicon::OutlinedArrowsRightLeft)
                    ->modalHeading(fn (ContentRevision $record) => $record->action->getLabel().' — '.$record->created_at->format('j M Y, H:i'))
                    ->modalContent(fn (ContentRevision $record) => view('filament.revision-diff', ['revision' => $record]))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close')
                    ->modalWidth('5xl'),
            ]);
    }
}
