<?php

namespace App\Models;

use App\Contracts\Publishable;
use App\Models\Concerns\HasPublishingWorkflow;
use Database\Factories\PageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['title', 'slug', 'intro', 'sections', 'meta_title', 'meta_description', 'og_image_id'])]
class Page extends Model implements Publishable
{
    /** @use HasFactory<PageFactory> */
    use HasFactory, HasPublishingWorkflow, SoftDeletes;

    /** Slugs of pages served by dedicated routes. Their slug cannot be changed and they cannot be deleted. */
    public const SYSTEM_SLUGS = [
        'home', 'about', 'what-we-do', 'get-involved', 'contact', 'projects', 'news',
        'privacy-policy', 'cookie-policy', 'accessibility',
    ];

    /** Slugs a custom page may not take because another route already uses them. */
    /** Page whose sections appear on the Watch page (/watch) below the player. */
    public const TV_SLUG = 'think-ocean-tv';

    public const RESERVED_SLUGS = [
        'admin', 'search', 'sitemap.xml', 'robots.txt', 'storage', 'livewire', 'up', 'filament', 'media', 'preview', 'live', 'watch', 'subscribe',
    ];

    protected function casts(): array
    {
        return [
            'sections' => 'array',
            'is_system' => 'boolean',
        ];
    }

    public static function contentFields(): array
    {
        return ['title', 'slug', 'intro', 'sections', 'meta_title', 'meta_description', 'og_image_id'];
    }

    public static function contentTypeLabel(): string
    {
        return 'page';
    }

    public function publicUrl(): string
    {
        return match ($this->slug) {
            'home' => route('home'),
            'projects' => route('projects.index'),
            'news' => route('news.index'),
            'contact' => route('contact'),
            self::TV_SLUG => route('watch'),
            default => route('pages.show', $this->slug),
        };
    }

    /** Whether the page with this slug is currently published. */
    public static function slugIsLive(string $slug): bool
    {
        return static::query()->published()->where('slug', $slug)->exists();
    }

    public function ogImage(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'og_image_id');
    }
}
