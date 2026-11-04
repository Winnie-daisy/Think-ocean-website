<?php

namespace App\Filament\Resources\ContactSubmissions\Pages;

use App\Filament\Resources\ContactSubmissions\ContactSubmissionResource;
use App\Models\ContactSubmission;
use App\Services\ActivityLogger;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Gate;

class ViewContactSubmission extends ViewRecord
{
    protected static string $resource = ContactSubmissionResource::class;

    public function mount(int|string $record): void
    {
        parent::mount($record);

        /** @var ContactSubmission $submission */
        $submission = $this->getRecord();

        if ($submission->status === 'unread' && Gate::allows('update', $submission)) {
            $submission->forceFill(['status' => 'read'])->save();
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('markReviewed')
                ->label('Mark as reviewed')
                ->icon(Heroicon::OutlinedCheckCircle)
                ->color('success')
                ->authorize('update', $this->getRecord())
                ->visible(fn () => $this->getRecord()->status !== 'reviewed')
                ->schema([
                    Textarea::make('internal_notes')
                        ->label('Internal notes (optional)')
                        ->default(fn () => $this->getRecord()->internal_notes)
                        ->maxLength(2000),
                ])
                ->action(function (array $data, ActivityLogger $activity): void {
                    $this->getRecord()->forceFill([
                        'status' => 'reviewed',
                        'reviewed_at' => now(),
                        'reviewed_by' => auth()->id(),
                        'internal_notes' => $data['internal_notes'] ?? null,
                    ])->save();

                    $activity->log('submission.reviewed', 'Marked contact message #'.$this->getRecord()->getKey().' as reviewed', $this->getRecord());
                    Notification::make()->success()->title('Marked as reviewed.')->send();
                }),
            Action::make('markSpam')
                ->label('Mark as spam')
                ->icon(Heroicon::OutlinedNoSymbol)
                ->color('gray')
                ->authorize('update', $this->getRecord())
                ->visible(fn () => $this->getRecord()->status !== 'spam')
                ->requiresConfirmation()
                ->action(function (): void {
                    $this->getRecord()->forceFill(['status' => 'spam'])->save();
                    Notification::make()->success()->title('Marked as spam.')->send();
                }),
            DeleteAction::make(),
        ];
    }
}
