<?php

namespace App\Filament\Resources\Pages\Pages;

use App\Filament\Concerns\EditsWorkflowContent;
use App\Filament\Resources\Pages\PageResource;
use Filament\Resources\Pages\EditRecord;

class EditPage extends EditRecord
{
    use EditsWorkflowContent;

    protected static string $resource = PageResource::class;
}
