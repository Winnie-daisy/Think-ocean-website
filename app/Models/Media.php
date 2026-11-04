<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * An uploaded file. Public files live on the "public" disk; private documents
 * live on the non-web-accessible "local" disk and are only downloadable by
 * signed-in staff through an authorised route.
 */
#[Fillable(['title', 'alt_text', 'caption', 'credit'])]
class Media extends Model
{
    public const VISIBILITY_PUBLIC = 'public';

    public const VISIBILITY_PRIVATE = 'private';

    /** Raster image types accepted for upload. SVG is excluded because it can carry scripts. */
    public const IMAGE_MIME_TYPES = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];

    public const DOCUMENT_MIME_TYPES = [
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/vnd.ms-powerpoint',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'text/plain',
        'text/csv',
    ];

    /** Maximum upload size in kilobytes. */
    public const MAX_UPLOAD_KB = 10240;

    protected $table = 'media';

    protected function casts(): array
    {
        return [
            'variants' => 'array',
            'size' => 'integer',
            'width' => 'integer',
            'height' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::deleted(function (Media $media): void {
            $disk = Storage::disk($media->disk);
            $disk->delete(array_values(array_filter([$media->path, ...array_values($media->variants ?? [])])));
        });
    }

    public static function allowedMimeTypes(): array
    {
        return [...self::IMAGE_MIME_TYPES, ...self::DOCUMENT_MIME_TYPES];
    }

    #[Scope]
    protected function images(Builder $query): void
    {
        $query->whereIn('mime_type', self::IMAGE_MIME_TYPES);
    }

    #[Scope]
    protected function publiclyVisible(Builder $query): void
    {
        $query->where('visibility', self::VISIBILITY_PUBLIC);
    }

    public function isImage(): bool
    {
        return in_array($this->mime_type, self::IMAGE_MIME_TYPES, true);
    }

    public function isPublic(): bool
    {
        return $this->visibility === self::VISIBILITY_PUBLIC && $this->disk === 'public';
    }

    /** Public URL, or null for private files (which must never be linked publicly). */
    public function url(): ?string
    {
        return $this->isPublic() ? Storage::disk('public')->url($this->path) : null;
    }

    /** srcset string built from the generated WebP variants. */
    public function srcset(): ?string
    {
        if (! $this->isPublic() || empty($this->variants)) {
            return null;
        }

        $disk = Storage::disk('public');

        return collect($this->variants)
            ->sortKeys()
            ->map(fn (string $path, int|string $width) => $disk->url($path).' '.$width.'w')
            ->implode(', ');
    }

    /** URL of the smallest variant at least $width pixels wide, falling back to the original. */
    public function urlForWidth(int $width): ?string
    {
        if (! $this->isPublic()) {
            return null;
        }

        $variants = collect($this->variants ?? [])->sortKeys();
        $variant = $variants->first(fn (string $path, int|string $w) => (int) $w >= $width) ?? $variants->last();

        return $variant ? Storage::disk('public')->url($variant) : $this->url();
    }

    public function displayName(): string
    {
        return $this->title ?: $this->original_name;
    }

    public function humanSize(): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $size = (float) $this->size;
        $i = 0;

        while ($size >= 1024 && $i < count($units) - 1) {
            $size /= 1024;
            $i++;
        }

        return round($size, $i === 0 ? 0 : 1).' '.$units[$i];
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
