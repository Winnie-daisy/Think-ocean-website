<?php

namespace App\Filament\Resources\Media\Pages;

use App\Filament\Resources\Media\MediaResource;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditMedia extends EditRecord
{
    protected static string $resource = MediaResource::class;

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        // Only descriptive fields can change; the stored file is fixed.
        $record->fill(array_intersect_key($data, array_flip(['title', 'alt_text', 'caption', 'credit'])))->save();

        return $record;
    }
}
