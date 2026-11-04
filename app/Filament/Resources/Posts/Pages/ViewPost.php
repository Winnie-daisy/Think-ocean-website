<?php

namespace App\Filament\Resources\Posts\Pages;

use App\Filament\Concerns\ViewsWorkflowContent;
use App\Filament\Resources\Posts\PostResource;
use Filament\Resources\Pages\ViewRecord;

class ViewPost extends ViewRecord
{
    use ViewsWorkflowContent;

    protected static string $resource = PostResource::class;
}
