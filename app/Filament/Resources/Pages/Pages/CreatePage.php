<?php

namespace App\Filament\Resources\Pages\Pages;

use App\Filament\Concerns\CreatesWorkflowContent;
use App\Filament\Resources\Pages\PageResource;
use Filament\Resources\Pages\CreateRecord;

class CreatePage extends CreateRecord
{
    use CreatesWorkflowContent;

    protected static string $resource = PageResource::class;
}
