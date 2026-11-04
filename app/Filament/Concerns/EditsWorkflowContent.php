<?php

namespace App\Filament\Concerns;

use App\Services\ContentWorkflow;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

/**
 * For EditRecord pages of publishable content: the form shows the working
 * copy and saving goes through ContentWorkflow, so live content is never
 * overwritten directly.
 */
trait EditsWorkflowContent
{
    use HasWorkflowActions;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        return $this->getRecord()->workingContent();
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        return app(ContentWorkflow::class)->saveDraft($record, $data, auth()->user());
    }

    protected function beforeWorkflowAction(): void
    {
        if (Gate::allows('update', $this->getRecord())) {
            $this->save(shouldRedirect: false, shouldSendSavedNotification: false);
        }
    }

    protected function getSavedNotificationTitle(): ?string
    {
        return $this->getRecord()->is_published
            ? 'Draft saved. The live version is unchanged until these changes are published.'
            : 'Draft saved.';
    }

    protected function getHeaderActions(): array
    {
        return $this->workflowActions();
    }
}
