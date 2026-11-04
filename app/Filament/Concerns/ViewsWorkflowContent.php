<?php

namespace App\Filament\Concerns;

use Filament\Actions\EditAction;

/** For read-only ViewRecord pages of publishable content (e.g. for reviewers). */
trait ViewsWorkflowContent
{
    use HasWorkflowActions;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        return $this->getRecord()->workingContent();
    }

    protected function getHeaderActions(): array
    {
        return [EditAction::make(), ...$this->workflowActions()];
    }
}
