<?php

namespace Database\Factories\Concerns;

use App\Enums\ReviewStatus;
use Illuminate\Database\Eloquent\Model;

/**
 * Factory states for publishable content. Published records are created
 * through the model's workflow guard, mirroring what ContentWorkflow does.
 */
trait PublishableFactoryStates
{
    public function published(?\DateTimeInterface $at = null): static
    {
        return $this->afterCreating(function (Model $model) use ($at) {
            $model->withLiveChangesAllowed(fn (Model $model) => $model->forceFill([
                'is_published' => true,
                'published_at' => $at ?? now()->subMinute(),
                'review_status' => ReviewStatus::Published,
            ])->save());
        });
    }

    public function inReview(): static
    {
        return $this->state(fn () => ['review_status' => ReviewStatus::InReview]);
    }
}
