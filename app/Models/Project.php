<?php

namespace App\Models;

use App\Contracts\Publishable;
use App\Enums\ProjectStatus;
use App\Models\Concerns\HasPublishingWorkflow;
use Database\Factories\ProjectFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;

#[Fillable([
    'title', 'slug', 'summary', 'description', 'featured_image_id', 'gallery', 'category_id',
    'location', 'start_date', 'end_date', 'project_status', 'is_featured',
    'meta_title', 'meta_description', 'og_image_id',
])]
class Project extends Model implements Publishable
{
    /** @use HasFactory<ProjectFactory> */
    use HasFactory, HasPublishingWorkflow, SoftDeletes;

    protected $attributes = [
        'is_featured' => false,
    ];

    protected function casts(): array
    {
        return [
            'gallery' => 'array',
            'start_date' => 'date',
            'end_date' => 'date',
            'project_status' => ProjectStatus::class,
            'is_featured' => 'boolean',
        ];
    }

    public static function contentFields(): array
    {
        return [
            'title', 'slug', 'summary', 'description', 'featured_image_id', 'gallery', 'category_id',
            'location', 'start_date', 'end_date', 'project_status', 'is_featured',
            'meta_title', 'meta_description', 'og_image_id',
        ];
    }

    public static function contentTypeLabel(): string
    {
        return 'project';
    }

    public function publicUrl(): string
    {
        return route('projects.show', $this->slug);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function featuredImage(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'featured_image_id');
    }

    public function ogImage(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'og_image_id');
    }

    /** @return Collection<int, Media> Gallery images in the order the editor chose. */
    public function galleryMedia(): Collection
    {
        $ids = array_values(array_filter(array_map('intval', $this->gallery ?? [])));

        if ($ids === []) {
            return collect();
        }

        $media = Media::query()->whereIn('id', $ids)->images()->publiclyVisible()->get()->keyBy('id');

        return collect($ids)->map(fn (int $id) => $media->get($id))->filter()->values();
    }
}
