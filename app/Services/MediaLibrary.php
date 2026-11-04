<?php

namespace App\Services;

use App\Models\Media;
use App\Models\User;
use GdImage;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Registers uploaded files in the media library.
 *
 * Filament has already stored the upload under a random file name; this
 * service re-checks the file's real content type (not its extension), records
 * its metadata and generates resized WebP copies of images. The WebP copies
 * are re-encoded, which also strips EXIF metadata such as GPS location.
 */
class MediaLibrary
{
    /** Responsive widths generated for images, in pixels. */
    public const VARIANT_WIDTHS = [480, 960, 1440, 1920, 2560];

    public function __construct(private readonly ActivityLogger $activity) {}

    public function register(string $disk, string $path, string $originalName, array $attributes, User $user): Media
    {
        Gate::forUser($user)->authorize('create', Media::class);

        $storage = Storage::disk($disk);

        if (! $storage->exists($path)) {
            throw ValidationException::withMessages(['path' => 'The uploaded file could not be found. Please try again.']);
        }

        $mime = $this->detectMimeType($storage->path($path));

        if (! in_array($mime, Media::allowedMimeTypes(), true)) {
            $storage->delete($path);

            throw ValidationException::withMessages(['path' => 'This file type is not allowed.']);
        }

        $isImage = in_array($mime, Media::IMAGE_MIME_TYPES, true);
        $visibility = $isImage ? Media::VISIBILITY_PUBLIC : ($attributes['visibility'] ?? Media::VISIBILITY_PUBLIC);

        // Images are always public. Documents marked private must live on the
        // non-public disk, so move them if necessary.
        $targetDisk = $visibility === Media::VISIBILITY_PRIVATE ? 'local' : 'public';

        if ($targetDisk !== $disk) {
            Storage::disk($targetDisk)->writeStream($path, $storage->readStream($path));
            $storage->delete($path);
            $storage = Storage::disk($targetDisk);
        }

        $dimensions = $isImage ? @getimagesize($storage->path($path)) : false;

        $media = new Media;
        $media->fill(array_intersect_key($attributes, array_flip(['title', 'alt_text', 'caption', 'credit'])));
        $media->forceFill([
            'disk' => $targetDisk,
            'visibility' => $visibility,
            'path' => $path,
            'original_name' => Str::limit(basename($originalName), 250, ''),
            'mime_type' => $mime,
            'extension' => strtolower(pathinfo($path, PATHINFO_EXTENSION)),
            'size' => $storage->size($path),
            'width' => $dimensions[0] ?? null,
            'height' => $dimensions[1] ?? null,
            'uploaded_by' => $user->getKey(),
        ]);

        if ($isImage && $targetDisk === 'public') {
            $media->variants = $this->generateVariants($media);
        }

        $media->save();

        $this->activity->log('media.uploaded', 'Uploaded "'.$media->original_name.'"', $media, ['visibility' => $visibility], $user);

        return $media;
    }

    public function delete(Media $media, User $user): void
    {
        Gate::forUser($user)->authorize('delete', $media);

        $this->activity->log('media.deleted', 'Deleted "'.$media->original_name.'"', $media, [], $user);
        $media->delete();
    }

    public function detectMimeType(string $absolutePath): ?string
    {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = $finfo ? finfo_file($finfo, $absolutePath) : false;

        return $mime ?: null;
    }

    /** @return array<int, string> width => path */
    public function generateVariants(Media $media): array
    {
        if (! function_exists('imagewebp')) {
            return [];
        }

        $disk = Storage::disk($media->disk);
        $variants = [];

        try {
            $source = $this->loadImage($disk->path($media->path), $media->mime_type);

            if (! $source) {
                return [];
            }

            $width = imagesx($source);
            $base = pathinfo($media->path, PATHINFO_DIRNAME).'/variants/'.pathinfo($media->path, PATHINFO_FILENAME);

            // Always keep at least one variant (the original width) so a
            // metadata-free WebP copy exists for every image.
            $widths = array_filter(self::VARIANT_WIDTHS, fn (int $w) => $w < $width);
            $widths[] = min($width, max(self::VARIANT_WIDTHS));

            foreach (array_unique($widths) as $targetWidth) {
                $resized = $targetWidth < $width ? imagescale($source, $targetWidth, -1, IMG_BICUBIC) : $source;

                if (! $resized) {
                    continue;
                }

                ob_start();
                imagewebp($resized, null, 80);
                $binary = ob_get_clean();

                if ($resized !== $source) {
                    imagedestroy($resized);
                }

                $path = $base.'-'.$targetWidth.'.webp';
                $disk->put($path, $binary);
                $variants[$targetWidth] = $path;
            }

            imagedestroy($source);
        } catch (Throwable $e) {
            Log::warning('Could not generate image variants', ['media' => $media->path, 'error' => $e->getMessage()]);
        }

        ksort($variants);

        return $variants;
    }

    private function loadImage(string $path, ?string $mime): GdImage|false
    {
        $image = match ($mime) {
            'image/jpeg' => @imagecreatefromjpeg($path),
            'image/png' => @imagecreatefrompng($path),
            'image/webp' => @imagecreatefromwebp($path),
            'image/gif' => @imagecreatefromgif($path),
            default => false,
        };

        if ($image && $mime === 'image/jpeg' && function_exists('exif_read_data')) {
            $orientation = (@exif_read_data($path) ?: [])['Orientation'] ?? 1;
            $image = match ((int) $orientation) {
                3 => imagerotate($image, 180, 0),
                6 => imagerotate($image, -90, 0),
                8 => imagerotate($image, 90, 0),
                default => $image,
            };
        }

        if ($image && ! imageistruecolor($image)) {
            imagepalettetotruecolor($image);
        }

        if ($image) {
            imagealphablending($image, false);
            imagesavealpha($image, true);
        }

        return $image;
    }
}
