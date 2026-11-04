<?php

namespace App\Filament\Resources\Pages\Pages;

use App\Filament\Concerns\ViewsWorkflowContent;
use App\Filament\Resources\Pages\PageResource;
use Filament\Resources\Pages\ViewRecord;

class ViewPage extends ViewRecord
{
    use ViewsWorkflowContent;

    protected static string $resource = PageResource::class;
}
