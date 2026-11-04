<?php

namespace App\Models;

use App\Contracts\Publishable;
use App\Models\Concerns\HasPublishingWorkflow;
use Database\Factories\PostFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['title', 'slug', 'excerpt', 'body', 'featured_image_id', 'meta_title', 'meta_description', 'og_image_id'])]
class Post extends Model implements Publishable
{
    /** @use HasFactory<PostFactory> */
    use HasFactory, HasPublishingWorkflow, SoftDeletes;

    public static function contentFields(): array
    {
        return [
            'title', 'slug', 'excerpt', 'body', 'featured_image_id', 'published_at',
            'meta_title', 'meta_description', 'og_image_id', 'category_ids', 'tag_ids',
        ];
    }

    public static function relationContentFields(): array
    {
        return ['category_ids', 'tag_ids'];
    }

    public static function contentTypeLabel(): string
    {
        return 'news article';
    }

    public function publicUrl(): string
    {
        return route('news.show', $this->slug);
    }

    protected function relationContentValue(string $field): mixed
    {
        if (! $this->exists) {
            return [];
        }

        $ids = match ($field) {
            'category_ids' => $this->categories()->pluck('categories.id'),
            'tag_ids' => $this->tags()->pluck('tags.id'),
        };

        return $ids->map(fn ($id) => (int) $id)->sort()->values()->all();
    }

    public function syncContentRelations(array $data): void
    {
        if (array_key_exists('category_ids', $data)) {
            $this->categories()->sync(
                Category::query()->ofType(Category::TYPE_POST)->whereIn('id', (array) $data['category_ids'])->pluck('id')
            );
        }

        if (array_key_exists('tag_ids', $data)) {
            $this->tags()->sync(Tag::query()->whereIn('id', (array) $data['tag_ids'])->pluck('id'));
        }
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class);
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class);
    }

    public function featuredImage(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'featured_image_id');
    }

    public function ogImage(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'og_image_id');
    }
}
