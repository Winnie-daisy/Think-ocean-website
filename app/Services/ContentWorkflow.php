<?php

namespace App\Services;

use App\Contracts\Publishable;
use App\Enums\ReviewStatus;
use App\Enums\RevisionAction;
use App\Models\ContentRevision;
use App\Models\Page;
use App\Models\User;
use BackedEnum;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * The only place that changes the state of publishable content.
 *
 * Rules:
 *  - While a record is published, edits are stored in draft_content and the
 *    live columns are untouched.
 *  - While a record is not published, edits are written to the columns (they
 *    are not public, so there is nothing to protect).
 *  - Only publish() makes content public, and it requires the "publish" ability.
 *
 * Every method authorises the acting user itself, so callers (Filament actions,
 * tests, console commands) cannot bypass the policies.
 */
class ContentWorkflow
{
    public const PLACEHOLDER_MARKER = '[PLACEHOLDER';

    public function __construct(private readonly ActivityLogger $activity) {}

    /**
     * @template T of Model&Publishable
     *
     * @param  class-string<T>  $class
     * @return T
     */
    public function create(string $class, array $data, User $user): Model
    {
        Gate::forUser($user)->authorize('create', $class);

        return DB::transaction(function () use ($class, $data, $user) {
            /** @var Model&Publishable $model */
            $model = new $class;
            $data = $this->normalise($model, $data);

            $model->fillContent($data);
            $model->forceFill([
                'author_id' => $user->getKey(),
                'review_status' => ReviewStatus::Draft,
                'is_published' => false,
            ]);
            $model->save();
            $model->syncContentRelations($data);

            // Load database defaults (e.g. boolean flags) so later snapshots are complete.
            $model->refresh();

            $this->recordRevision($model, RevisionAction::Created, $user, null, $model->workingContent());
            $this->logActivity('content.created', 'Created', $model, $user);

            return $model;
        });
    }

    public function saveDraft(Model&Publishable $model, array $data, User $user): Model
    {
        Gate::forUser($user)->authorize('update', $model);

        $data = $this->normalise($model, $data);

        if ($model instanceof Page && $model->is_system) {
            unset($data['slug']);
        }

        return DB::transaction(function () use ($model, $data, $user) {
            $before = $model->workingContent();

            if ($model->is_published) {
                $draft = $this->diff($model->liveContent(), array_merge($before, $data));
                $model->forceFill(['draft_content' => $draft ?: null]);

                if ($draft && $model->review_status === ReviewStatus::Published) {
                    $model->forceFill(['review_status' => ReviewStatus::Draft]);
                } elseif (! $draft && $model->review_status === ReviewStatus::Draft) {
                    $model->forceFill(['review_status' => ReviewStatus::Published]);
                }

                $model->save();
            } else {
                $merged = array_merge($before, $data);
                $model->fillContent($merged);
                $model->forceFill(['draft_content' => null]);
                $model->save();
                $model->syncContentRelations($merged);
            }

            $after = $model->workingContent();

            if ($this->diff($before, $after) !== []) {
                $this->recordRevision($model, RevisionAction::DraftSaved, $user, $before, $after);
            }

            return $model;
        });
    }

    public function submit(Model&Publishable $model, User $user, ?string $note = null): Model
    {
        Gate::forUser($user)->authorize('submit', $model);

        if (! $model->review_status->canBeSubmitted()) {
            throw new WorkflowException('This '.$model::contentTypeLabel().' is not in a state that can be submitted.');
        }

        if ($model->is_published && ! $model->hasPendingDraft()) {
            throw new WorkflowException('There are no unpublished changes to submit.');
        }

        return DB::transaction(function () use ($model, $user, $note) {
            $model->forceFill([
                'review_status' => ReviewStatus::InReview,
                'submitted_by' => $user->getKey(),
                'submitted_at' => now(),
                'review_comment' => null,
            ])->save();

            $this->recordRevision($model, RevisionAction::Submitted, $user, null, $model->workingContent(), $note);
            $this->logActivity('content.submitted', 'Submitted for review', $model, $user);

            return $model;
        });
    }

    public function publish(Model&Publishable $model, User $user, ?string $comment = null): Model
    {
        Gate::forUser($user)->authorize('publish', $model);

        if ($model->is_published && ! $model->hasPendingDraft() && $model->review_status === ReviewStatus::Published) {
            throw new WorkflowException('There are no unpublished changes to publish.');
        }

        return DB::transaction(function () use ($model, $user, $comment) {
            $previous = $model->is_published ? $model->liveContent() : null;
            $content = $model->workingContent();

            $this->ensureSlugIsAvailable($model, (string) $content['slug']);
            $this->ensureNoPlaceholders($model, $content);

            $model->withLiveChangesAllowed(function (Model&Publishable $model) use ($content, $user, $comment) {
                $model->fillContent($content);
                $model->forceFill([
                    'draft_content' => null,
                    'is_published' => true,
                    'published_at' => $model->published_at ?? now(),
                    'review_status' => ReviewStatus::Published,
                    'reviewer_id' => $user->getKey(),
                    'reviewed_at' => now(),
                    'published_by' => $user->getKey(),
                    'review_comment' => $comment,
                ]);
                $model->save();
                $model->syncContentRelations($content);
            });

            $this->recordRevision($model, RevisionAction::Published, $user, $previous, $model->liveContent(), $comment);
            $this->logActivity('content.published', 'Approved and published', $model, $user);

            return $model;
        });
    }

    public function requestChanges(Model&Publishable $model, User $user, string $comment): Model
    {
        return $this->closeReview($model, $user, $comment, ReviewStatus::ChangesRequested, RevisionAction::ChangesRequested);
    }

    public function reject(Model&Publishable $model, User $user, string $reason): Model
    {
        return $this->closeReview($model, $user, $reason, ReviewStatus::Rejected, RevisionAction::Rejected);
    }

    public function unpublish(Model&Publishable $model, User $user, ?string $reason = null): Model
    {
        Gate::forUser($user)->authorize('unpublish', $model);

        if (! $model->is_published) {
            throw new WorkflowException('This '.$model::contentTypeLabel().' is not published.');
        }

        return DB::transaction(function () use ($model, $user, $reason) {
            $previous = $model->liveContent();

            $model->withLiveChangesAllowed(function (Model $model) {
                $model->forceFill(['is_published' => false]);

                if ($model->review_status === ReviewStatus::Published) {
                    $model->forceFill(['review_status' => ReviewStatus::Draft]);
                }

                $model->save();
            });

            $this->recordRevision($model, RevisionAction::Unpublished, $user, $previous, null, $reason);
            $this->logActivity('content.unpublished', 'Unpublished', $model, $user);

            return $model;
        });
    }

    public function discardDraft(Model&Publishable $model, User $user): Model
    {
        Gate::forUser($user)->authorize('update', $model);

        if (! $model->hasPendingDraft()) {
            throw new WorkflowException('There is no draft to discard.');
        }

        return DB::transaction(function () use ($model, $user) {
            $before = $model->workingContent();

            $model->forceFill([
                'draft_content' => null,
                'review_status' => $model->is_published ? ReviewStatus::Published : ReviewStatus::Draft,
            ])->save();

            $this->recordRevision($model, RevisionAction::DraftDiscarded, $user, $before, $model->workingContent());

            return $model;
        });
    }

    public function delete(Model&Publishable $model, User $user): void
    {
        Gate::forUser($user)->authorize('delete', $model);

        DB::transaction(function () use ($model, $user) {
            $this->recordRevision($model, RevisionAction::Deleted, $user, $model->workingContent(), null);
            $this->logActivity('content.deleted', 'Deleted', $model, $user);

            $model->withLiveChangesAllowed(function (Model $model) {
                $model->forceFill(['is_published' => false])->save();
                $model->delete();
            });
        });
    }

    public function restore(Model&Publishable $model, User $user): void
    {
        Gate::forUser($user)->authorize('restore', $model);

        DB::transaction(function () use ($model, $user) {
            $model->restore();
            $model->forceFill(['review_status' => ReviewStatus::Draft])->save();

            $this->recordRevision($model, RevisionAction::Restored, $user, null, $model->workingContent());
            $this->logActivity('content.restored', 'Restored (unpublished)', $model, $user);
        });
    }

    private function closeReview(Model&Publishable $model, User $user, string $comment, ReviewStatus $status, RevisionAction $action): Model
    {
        Gate::forUser($user)->authorize('review', $model);

        if ($model->review_status !== ReviewStatus::InReview) {
            throw new WorkflowException('Only content that has been submitted for review can be returned or rejected.');
        }

        if (trim($comment) === '') {
            throw ValidationException::withMessages(['comment' => 'Please give a reason so the author knows what to change.']);
        }

        return DB::transaction(function () use ($model, $user, $comment, $status, $action) {
            $model->forceFill([
                'review_status' => $status,
                'reviewer_id' => $user->getKey(),
                'reviewed_at' => now(),
                'review_comment' => $comment,
            ])->save();

            $this->recordRevision($model, $action, $user, null, $model->workingContent(), $comment);
            $this->logActivity('content.'.$action->value, $action->getLabel(), $model, $user);

            return $model;
        });
    }

    private function ensureSlugIsAvailable(Model&Publishable $model, string $slug): void
    {
        $taken = $model->newQuery()
            ->withoutGlobalScopes()
            ->where('slug', $slug)
            ->whereKeyNot($model->getKey())
            ->exists();

        $reserved = $model instanceof Page && in_array($slug, Page::RESERVED_SLUGS, true);

        if ($slug === '' || $taken || $reserved) {
            throw ValidationException::withMessages(['slug' => 'The URL slug "'.$slug.'" is already in use. Change it before publishing.']);
        }
    }

    /**
     * Seeded templates mark unverified text with "[PLACEHOLDER". Refuse to
     * publish until every placeholder has been replaced with approved copy.
     */
    private function ensureNoPlaceholders(Model&Publishable $model, array $content): void
    {
        if (str_contains((string) json_encode($content, JSON_UNESCAPED_UNICODE), self::PLACEHOLDER_MARKER)) {
            throw new WorkflowException('This '.$model::contentTypeLabel().' still contains placeholder text marked "[PLACEHOLDER: …]". Replace it with approved content before publishing.');
        }
    }

    /** Keep only content fields and coerce values to the same shape liveContent() produces. */
    private function normalise(Model&Publishable $model, array $data): array
    {
        $data = array_intersect_key($data, array_flip($model::contentFields()));

        foreach ($data as $field => $value) {
            if (in_array($field, $model::relationContentFields(), true)) {
                $data[$field] = collect((array) $value)->filter()->map(fn ($id) => (int) $id)->unique()->sort()->values()->all();

                continue;
            }

            if ($value instanceof BackedEnum) {
                $value = $value->value;
            }

            if (is_string($value)) {
                $value = trim($value) === '' ? null : $value;
            }

            if ($value !== null && $model->hasCast($field, ['date', 'immutable_date'])) {
                $value = Carbon::parse($value)->toDateString();
            } elseif ($value !== null && $model->hasCast($field, ['datetime', 'immutable_datetime'])) {
                $value = Carbon::parse($value)->toDateTimeString();
            }

            $data[$field] = $value;
        }

        return $data;
    }

    /** Fields in $new that differ from $base. Loose comparison so "5" and 5 are equal. */
    private function diff(array $base, array $new): array
    {
        $changes = [];

        foreach ($new as $field => $value) {
            if (! array_key_exists($field, $base) || $base[$field] != $value) {
                $changes[$field] = $value;
            }
        }

        return $changes;
    }

    private function recordRevision(Model $model, RevisionAction $action, User $user, ?array $previous, ?array $new, ?string $comment = null): void
    {
        $revision = new ContentRevision;
        $revision->forceFill([
            'revisionable_type' => $model->getMorphClass(),
            'revisionable_id' => $model->getKey(),
            'user_id' => $user->getKey(),
            'action' => $action,
            'previous_content' => $previous,
            'new_content' => $new,
            'comment' => $comment,
            'created_at' => now(),
        ])->save();
    }

    private function logActivity(string $event, string $verb, Model&Publishable $model, User $user): void
    {
        $this->activity->log(
            $event,
            $verb.' '.$model::contentTypeLabel().' "'.$model->title.'"',
            $model,
            ['review_status' => $model->review_status?->value],
            $user,
        );
    }
}
