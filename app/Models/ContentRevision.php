<?php

namespace App\Models;

use App\Enums\RevisionAction;
use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Immutable history entry for a piece of publishable content.
 * Written only by App\Services\ContentWorkflow.
 */
#[WithoutTimestamps]
class ContentRevision extends Model
{
    protected $guarded = ['*'];

    protected function casts(): array
    {
        return [
            'action' => RevisionAction::class,
            'previous_content' => 'array',
            'new_content' => 'array',
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn () => false);
    }

    public function revisionable(): MorphTo
    {
        return $this->morphTo()->withTrashed();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Fields whose value differs between the previous and new snapshots.
     *
     * @return array<string, array{old: mixed, new: mixed}>
     */
    public function changes(): array
    {
        $old = $this->previous_content ?? [];
        $new = $this->new_content ?? [];
        $changes = [];

        foreach (array_unique([...array_keys($old), ...array_keys($new)]) as $key) {
            if (($old[$key] ?? null) != ($new[$key] ?? null)) {
                $changes[$key] = ['old' => $old[$key] ?? null, 'new' => $new[$key] ?? null];
            }
        }

        return $changes;
    }
}
