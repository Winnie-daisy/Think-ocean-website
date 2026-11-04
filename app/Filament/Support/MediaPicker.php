<?php

namespace App\Filament\Support;

use App\Models\Media;
use Filament\Forms\Components\Select;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Select field for choosing public images from the media library.
 */
class MediaPicker
{
    public static function image(string $name, string $label): Select
    {
        return static::base($name, $label)
            ->getOptionLabelUsing(fn ($value): ?string => ($media = static::query()->find($value)) ? static::label($media) : null);
    }

    public static function gallery(string $name, string $label): Select
    {
        return static::base($name, $label)
            ->multiple()
            ->getOptionLabelsUsing(fn (array $values): array => static::options(static::query()->whereIn('id', $values)->get()));
    }

    private static function base(string $name, string $label): Select
    {
        return Select::make($name)
            ->label($label)
            ->searchable()
            ->allowHtml()
            ->options(fn (): array => static::options(static::query()->latest('id')->limit(30)->get()))
            ->getSearchResultsUsing(fn (string $search): array => static::options(
                static::query()
                    ->where(fn (Builder $q) => $q
                        ->where('title', 'like', '%'.static::escapeLike($search).'%')
                        ->orWhere('original_name', 'like', '%'.static::escapeLike($search).'%')
                        ->orWhere('alt_text', 'like', '%'.static::escapeLike($search).'%'))
                    ->latest('id')
                    ->limit(30)
                    ->get()
            ))
            ->helperText('Choose an image from the media library. Upload new images under Library → Media.');
    }

    private static function query(): Builder
    {
        return Media::query()->images()->publiclyVisible();
    }

    /** @param Collection<int, Media> $media */
    private static function options(Collection $media): array
    {
        return $media->mapWithKeys(fn (Media $m) => [$m->getKey() => static::label($m)])->all();
    }

    private static function label(Media $media): string
    {
        $thumb = e($media->urlForWidth(480) ?? '');
        $name = e($media->displayName());
        $warning = blank($media->alt_text) ? ' <span style="color:#b45309">(no alt text)</span>' : '';

        return '<span style="display:flex;align-items:center;gap:.5rem">'
            .'<img src="'.$thumb.'" alt="" style="width:3rem;height:2rem;object-fit:cover;border-radius:.25rem">'
            .'<span>'.$name.$warning.'</span></span>';
    }

    private static function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }
}
