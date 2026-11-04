<?php

namespace App\Filament\Resources\Projects\Pages;

use App\Filament\Concerns\EditsWorkflowContent;
use App\Filament\Resources\Projects\ProjectResource;
use Filament\Resources\Pages\EditRecord;

class EditProject extends EditRecord
{
    use EditsWorkflowContent;

    protected static string $resource = ProjectResource::class;
}
