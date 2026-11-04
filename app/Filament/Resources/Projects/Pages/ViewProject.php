<?php

namespace App\Filament\Resources\Projects\Pages;

use App\Filament\Concerns\ViewsWorkflowContent;
use App\Filament\Resources\Projects\ProjectResource;
use Filament\Resources\Pages\ViewRecord;

class ViewProject extends ViewRecord
{
    use ViewsWorkflowContent;

    protected static string $resource = ProjectResource::class;
}
