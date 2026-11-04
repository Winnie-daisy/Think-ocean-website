<?php

namespace App\Filament\Support;

use App\Enums\ReviewStatus;
use App\Services\ContentWorkflow;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Columns and filters shared by publishable content tables. Status is always
 * shown as text (with an icon), never by colour alone.
 */
class WorkflowTable
{
    public static function titleColumn(): TextColumn
    {
        return TextColumn::make('title')
            ->state(fn (Model $record) => $record->draft_content['title'] ?? $record->title)
            ->description(fn (Model $record) => '/'.($record->draft_content['slug'] ?? $record->slug))
            ->searchable()
            ->sortable()
            ->wrap();
    }

    /** @return array<int, TextColumn> */
    public static function statusColumns(): array
    {
        return [
            TextColumn::make('visibility')
                ->label('On website')
                ->badge()
                ->state(fn (Model $record): string => match (true) {
                    $record->isScheduled() => 'Scheduled',
                    $record->isLive() => 'Live',
                    default => 'Not published',
                })
                ->color(fn (string $state): string => match ($state) {
                    'Live' => 'success',
                    'Scheduled' => 'info',
                    default => 'gray',
                })
                ->icon(fn (string $state) => match ($state) {
                    'Live' => 'heroicon-o-globe-alt',
                    'Scheduled' => 'heroicon-o-calendar',
                    default => 'heroicon-o-eye-slash',
                })
                ->description(fn (Model $record): ?string => $record->is_published && $record->hasPendingDraft() ? 'Unpublished changes pending' : null),
            TextColumn::make('review_status')
                ->label('Working copy')
                ->badge()
                ->sortable(),
            TextColumn::make('author.name')
                ->label('Author')
                ->toggleable(),
            TextColumn::make('updated_at')
                ->label('Last edited')
                ->since()
                ->dateTimeTooltip()
                ->sortable(),
        ];
    }

    public static function filters(): array
    {
        return [
            SelectFilter::make('review_status')->label('Working copy')->options(ReviewStatus::class),
            TernaryFilter::make('is_published')->label('On website')->trueLabel('Published')->falseLabel('Not published'),
            Filter::make('pending_draft')
                ->label('Has unpublished changes')
                ->query(fn (Builder $query) => $query->whereNotNull('draft_content')),
            Filter::make('mine')
                ->label('Created by me')
                ->query(fn (Builder $query) => $query->where('author_id', auth()->id())),
            TrashedFilter::make(),
        ];
    }

    /** Restores binned content as an unpublished draft. */
    public static function restoreAction(): Action
    {
        return Action::make('restoreContent')
            ->label('Restore')
            ->icon(Heroicon::OutlinedArrowUturnLeft)
            ->color('gray')
            ->visible(fn (Model $record): bool => $record->trashed() && (auth()->user()?->can('restore', $record) ?? false))
            ->requiresConfirmation()
            ->modalDescription('The item is restored as an unpublished draft.')
            ->action(function (Model $record, ContentWorkflow $workflow): void {
                $workflow->restore($record, auth()->user());
                Notification::make()->success()->title('Restored as a draft.')->send();
            });
    }
}
