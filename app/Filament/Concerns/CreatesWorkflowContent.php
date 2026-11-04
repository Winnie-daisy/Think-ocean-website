<?php

namespace App\Filament\Concerns;

use App\Services\ContentWorkflow;
use Illuminate\Database\Eloquent\Model;

/** For CreateRecord pages of publishable content. New content always starts as an unpublished draft. */
trait CreatesWorkflowContent
{
    protected function handleRecordCreation(array $data): Model
    {
        return app(ContentWorkflow::class)->create(static::getModel(), $data, auth()->user());
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('edit', ['record' => $this->getRecord()]);
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Draft created. It is not visible on the website until it is published.';
    }
}
