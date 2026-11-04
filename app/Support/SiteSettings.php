<?php

namespace App\Support;

use App\Models\Media;
use App\Models\Setting;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;

/**
 * Cached access to site-wide settings managed in the CMS.
 *
 * Contact details and social links default to empty: nothing is shown on the
 * public site until staff enter verified information.
 */
class SiteSettings
{
    private const CACHE_KEY = 'site-settings';

    public const DEFAULTS = [
        'organisation_name' => 'Think Ocean CIC',
        'tagline' => 'Healthy Oceans. Thriving Futures.',
        'logo_media_id' => null,
        'favicon_media_id' => null,
        'contact_email' => null,
        'contact_phone' => null,
        'postal_address' => null,
        'company_registration' => null,
        'social_links' => [],
        'footer_about' => null,
        'default_meta_title' => 'Think Ocean CIC | Healthy Oceans. Thriving Futures.',
        'default_meta_description' => null,
        'default_og_image_id' => null,
        'contact_form_enabled' => true,
        'contact_notification_email' => null,
        'live_enabled' => false,
        'live_title' => 'Think Ocean TV',
        'live_description' => null,
        'live_stream_url' => null,
        'live_schedule' => null,
        'watch_videos' => [],
        'watch_browse_url' => null,
    ];

    private ?array $values = null;

    /** Identity of the request the memoised values belong to. */
    private ?int $requestId = null;

    public function all(): array
    {
        if ($this->requestId !== spl_object_id(request())) {
            $this->values = null;
            $this->requestId = spl_object_id(request());
        }

        return $this->values ??= array_merge(
            self::DEFAULTS,
            Cache::rememberForever(self::CACHE_KEY, fn () => Setting::query()->pluck('value', 'key')->all()),
        );
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return Arr::get($this->all(), $key) ?? $default;
    }

    public function set(array $values): void
    {
        foreach (array_intersect_key($values, self::DEFAULTS) as $key => $value) {
            Setting::query()->updateOrCreate(['key' => $key], ['value' => $value]);
        }

        $this->flush();
    }

    public function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
        $this->values = null;
    }

    public function media(string $key): ?Media
    {
        $id = $this->get($key);

        return $id ? Media::query()->publiclyVisible()->find($id) : null;
    }

    /** @return array<int, array{platform: string, url: string}> */
    public function socialLinks(): array
    {
        return array_values(array_filter(
            (array) $this->get('social_links', []),
            fn ($link) => is_array($link) && filled($link['url'] ?? null) && filled($link['platform'] ?? null),
        ));
    }
}
