<?php

namespace App\Contracts;

use App\Models\Concerns\HasPublishingWorkflow;
use Illuminate\Database\Eloquent\Model;

/**
 * Content that goes through the draft / review / publish workflow.
 *
 * @mixin Model
 * @mixin HasPublishingWorkflow
 */
interface Publishable
{
    /** @return array<int, string> */
    public static function contentFields(): array;

    /** Human label for the content type, e.g. "page". */
    public static function contentTypeLabel(): string;

    /** Public URL of the live version. */
    public function publicUrl(): string;
}
