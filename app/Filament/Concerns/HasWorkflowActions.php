<?php

namespace App\Filament\Concerns;

use App\Enums\ReviewStatus;
use App\Services\ContentWorkflow;
use App\Services\WorkflowException;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Header actions for the publishing workflow, used on edit and view pages.
 *
 * Visibility is a convenience only: every action is also authorised by
 * Filament (->authorize) and again inside ContentWorkflow.
 */
trait HasWorkflowActions
{
    /** Called before a workflow action runs. Edit pages save the form first. */
    protected function beforeWorkflowAction(): void {}

    protected function workflowActions(): array
    {
        return [
            $this->previewAction(),
            $this->viewLiveAction(),
            $this->submitAction(),
            $this->publishAction(),
            ActionGroup::make([
                $this->requestChangesAction(),
                $this->rejectAction(),
                $this->discardDraftAction(),
                $this->unpublishAction(),
                $this->deleteContentAction(),
            ])->label('More')->icon(Heroicon::OutlinedEllipsisVertical)->button()->color('gray'),
        ];
    }

    protected function previewAction(): Action
    {
        return Action::make('preview')
            ->label('Preview')
            ->icon(Heroicon::OutlinedEye)
            ->color('gray')
            ->url(fn () => route('admin.preview', [
                'type' => $this->getRecord()::contentTypeKey(),
                'id' => $this->getRecord()->getKey(),
            ]))
            ->openUrlInNewTab()
            ->authorize('view', $this->getRecord());
    }

    protected function viewLiveAction(): Action
    {
        return Action::make('viewLive')
            ->label('View live')
            ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
            ->color('gray')
            ->url(fn () => $this->getRecord()->publicUrl())
            ->openUrlInNewTab()
            ->visible(fn () => $this->getRecord()->isLive());
    }

    protected function submitAction(): Action
    {
        return Action::make('submitForReview')
            ->label('Submit for review')
            ->icon(Heroicon::OutlinedPaperAirplane)
            ->color('info')
            ->authorize('submit', $this->getRecord())
            ->visible(fn () => Gate::allows('submit', $this->getRecord())
                && ! Gate::allows('publish', $this->getRecord())
                && (! $this->getRecord()->is_published || $this->getRecord()->hasPendingDraft()))
            ->modalDescription('An editor will review your changes before they appear on the website. You will not be able to edit while the review is in progress.')
            ->schema([
                Textarea::make('note')->label('Note for the reviewer (optional)')->maxLength(2000),
            ])
            ->action(function (array $data, ContentWorkflow $workflow): void {
                $this->runWorkflow(fn () => $workflow->submit($this->getRecord(), auth()->user(), $data['note'] ?? null), 'Submitted for review.');
            });
    }

    protected function publishAction(): Action
    {
        return Action::make('publish')
            ->label(fn () => $this->getRecord()->review_status === ReviewStatus::InReview ? 'Approve and publish' : 'Publish')
            ->icon(Heroicon::OutlinedCheckBadge)
            ->color('success')
            ->authorize('publish', $this->getRecord())
            ->visible(fn () => Gate::allows('publish', $this->getRecord())
                && (! $this->getRecord()->is_published
                    || $this->getRecord()->hasPendingDraft()
                    || $this->getRecord()->review_status !== ReviewStatus::Published))
            ->requiresConfirmation()
            ->modalHeading('Publish to the website?')
            ->modalDescription(fn () => $this->getRecord()->is_published
                ? 'The pending draft will replace the version currently on the website.'
                : 'This will make the content visible to the public.')
            ->schema([
                Textarea::make('comment')->label('Review comment (optional)')->maxLength(2000),
            ])
            ->action(function (array $data, ContentWorkflow $workflow): void {
                $this->runWorkflow(fn () => $workflow->publish($this->getRecord(), auth()->user(), $data['comment'] ?? null), 'Published.');
            });
    }

    protected function requestChangesAction(): Action
    {
        return Action::make('requestChanges')
            ->label('Return for changes')
            ->icon(Heroicon::OutlinedArrowUturnLeft)
            ->color('warning')
            ->authorize('review', $this->getRecord())
            ->schema([
                Textarea::make('comment')->label('What needs to change?')->required()->maxLength(2000),
            ])
            ->action(function (array $data, ContentWorkflow $workflow): void {
                $this->runWorkflow(fn () => $workflow->requestChanges($this->getRecord(), auth()->user(), $data['comment']), 'Returned to the author for changes.');
            });
    }

    protected function rejectAction(): Action
    {
        return Action::make('reject')
            ->label('Reject')
            ->icon(Heroicon::OutlinedXCircle)
            ->color('danger')
            ->authorize('review', $this->getRecord())
            ->schema([
                Textarea::make('reason')->label('Reason for rejection')->required()->maxLength(2000),
            ])
            ->action(function (array $data, ContentWorkflow $workflow): void {
                $this->runWorkflow(fn () => $workflow->reject($this->getRecord(), auth()->user(), $data['reason']), 'Submission rejected.');
            });
    }

    protected function discardDraftAction(): Action
    {
        return Action::make('discardDraft')
            ->label('Discard draft changes')
            ->icon(Heroicon::OutlinedTrash)
            ->color('danger')
            ->authorize('update', $this->getRecord())
            ->visible(fn () => $this->getRecord()->hasPendingDraft() && Gate::allows('update', $this->getRecord()))
            ->requiresConfirmation()
            ->modalDescription('Your unpublished changes will be removed and the editor will show the live version again. This is recorded in the revision history.')
            ->action(function (ContentWorkflow $workflow): void {
                $this->runWorkflow(fn () => $workflow->discardDraft($this->getRecord(), auth()->user()), 'Draft discarded.');
            });
    }

    protected function unpublishAction(): Action
    {
        return Action::make('unpublish')
            ->label('Unpublish')
            ->icon(Heroicon::OutlinedEyeSlash)
            ->color('danger')
            ->authorize('unpublish', $this->getRecord())
            ->requiresConfirmation()
            ->modalDescription('This removes the content from the public website. It can be published again later.')
            ->schema([
                Textarea::make('reason')->label('Reason (optional)')->maxLength(2000),
            ])
            ->action(function (array $data, ContentWorkflow $workflow): void {
                $this->runWorkflow(fn () => $workflow->unpublish($this->getRecord(), auth()->user(), $data['reason'] ?? null), 'Unpublished.');
            });
    }

    protected function deleteContentAction(): Action
    {
        return Action::make('deleteContent')
            ->label('Delete')
            ->icon(Heroicon::OutlinedTrash)
            ->color('danger')
            ->authorize('delete', $this->getRecord())
            ->requiresConfirmation()
            ->modalDescription('The item is moved to the bin and removed from the website. An editor can restore it.')
            ->action(function (ContentWorkflow $workflow): void {
                try {
                    $workflow->delete($this->getRecord(), auth()->user());
                } catch (WorkflowException $e) {
                    Notification::make()->danger()->title($e->getMessage())->send();

                    return;
                }

                Notification::make()->success()->title('Deleted.')->send();
                $this->redirect(static::getResource()::getUrl('index'));
            });
    }

    private function runWorkflow(callable $operation, string $successMessage): void
    {
        try {
            $this->beforeWorkflowAction();
            $operation();
        } catch (WorkflowException $e) {
            Notification::make()->danger()->title($e->getMessage())->send();

            return;
        } catch (ValidationException $e) {
            Notification::make()->danger()->title('Could not complete this action')->body(collect($e->errors())->flatten()->implode(' '))->send();

            return;
        }

        Notification::make()->success()->title($successMessage)->send();

        $record = $this->getRecord()->refresh();
        $page = static::getResource()::canEdit($record) ? 'edit' : 'view';

        $this->redirect(static::getResource()::getUrl($page, ['record' => $record]));
    }
}
