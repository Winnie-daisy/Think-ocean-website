<?php

namespace App\Models\Concerns;

use App\Enums\ReviewStatus;
use App\Exceptions\LiveContentProtectedException;
use App\Models\ContentRevision;
use App\Models\User;
use BackedEnum;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Str;

/**
 * The model's columns hold the live (approved) content once it is published.
 * While a record is live, edits are stored in `draft_content` and only an
 * authorised publish action (App\Services\ContentWorkflow) copies them onto
 * the columns. A saving guard enforces this so no other code path can leak
 * unapproved content onto the public site.
 */
trait HasPublishingWorkflow
{
    private bool $liveChangesAllowed = false;

    public static function bootHasPublishingWorkflow(): void
    {
        static::updating(function (self $model): void {
            if ($model->liveChangesAllowed) {
                return;
            }

            if ($model->isDirty('is_published') && $model->is_published) {
                throw new LiveContentProtectedException('Content can only be published through the publishing workflow.');
            }

            if ($model->getOriginal('is_published') && $model->isDirty(static::contentColumns())) {
                throw new LiveContentProtectedException('Live content can only be changed through the publishing workflow.');
            }
        });

        static::creating(function (self $model): void {
            if ($model->is_published && ! $model->liveChangesAllowed) {
                throw new LiveContentProtectedException('Content can only be published through the publishing workflow.');
            }
        });
    }

    public function initializeHasPublishingWorkflow(): void
    {
        $this->mergeCasts([
            'review_status' => ReviewStatus::class,
            'draft_content' => 'array',
            'is_published' => 'boolean',
            'published_at' => 'datetime',
            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
        ]);
    }

    /** Short key used in admin preview URLs, e.g. "page". */
    public static function contentTypeKey(): string
    {
        return Str::snake(class_basename(static::class));
    }

    /** Content fields stored as columns on this table. */
    public static function contentColumns(): array
    {
        return array_values(array_diff(static::contentFields(), static::relationContentFields()));
    }

    /** Content fields backed by relationships (e.g. tag ids) rather than columns. */
    public static function relationContentFields(): array
    {
        return [];
    }

    /** Only live content whose publication date has arrived. */
    #[Scope]
    protected function published(Builder $query): void
    {
        $query->where($this->qualifyColumn('is_published'), true)
            ->whereNotNull($this->qualifyColumn('published_at'))
            ->where($this->qualifyColumn('published_at'), '<=', now());
    }

    public function isLive(): bool
    {
        return $this->is_published && $this->published_at !== null && $this->published_at->lte(now());
    }

    public function isScheduled(): bool
    {
        return $this->is_published && $this->published_at?->isFuture();
    }

    public function hasPendingDraft(): bool
    {
        return ! empty($this->draft_content);
    }

    /** Snapshot of the content as stored in the columns (the live version once published). */
    public function liveContent(): array
    {
        $content = [];

        foreach (static::contentColumns() as $field) {
            $content[$field] = $this->normaliseContentValue($field, $this->getAttribute($field));
        }

        foreach (static::relationContentFields() as $field) {
            $content[$field] = $this->relationContentValue($field);
        }

        return $content;
    }

    /** The version staff are editing: live content overlaid with any pending draft. */
    public function workingContent(): array
    {
        return array_merge($this->liveContent(), array_intersect_key(
            $this->draft_content ?? [],
            array_flip(static::contentFields()),
        ));
    }

    /**
     * Write content to the columns. Only ContentWorkflow should call this.
     */
    public function fillContent(array $data): static
    {
        $this->forceFill(array_intersect_key($data, array_flip(static::contentColumns())));

        return $this;
    }

    /** Persist relationship-backed content after the record is saved. */
    public function syncContentRelations(array $data): void {}

    protected function relationContentValue(string $field): mixed
    {
        return null;
    }

    /**
     * Run a callback with the live-content guard lifted for this instance.
     */
    public function withLiveChangesAllowed(callable $callback): mixed
    {
        $this->liveChangesAllowed = true;

        try {
            return $callback($this);
        } finally {
            $this->liveChangesAllowed = false;
        }
    }

    protected function normaliseContentValue(string $field, mixed $value): mixed
    {
        if ($value instanceof BackedEnum) {
            return $value->value;
        }

        if ($value instanceof CarbonInterface) {
            return $this->hasCast($field, ['date', 'immutable_date'])
                ? $value->toDateString()
                : $value->toDateTimeString();
        }

        return $value;
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }

    public function publisher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by');
    }

    public function revisions(): MorphMany
    {
        return $this->morphMany(ContentRevision::class, 'revisionable')->latest('id');
    }
}
