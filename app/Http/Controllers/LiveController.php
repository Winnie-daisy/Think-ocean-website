<?php

namespace App\Http\Controllers;

use App\Models\Media;
use App\Models\Page;
use App\Support\LiveStream;
use App\Support\SiteSettings;
use Illuminate\View\View;

/** Think Ocean TV: plays the stream configured in Site settings while it is switched on. */
class LiveController extends Controller
{
    public function __invoke(SiteSettings $settings): View
    {
        $stream = $settings->get('live_enabled') ? LiveStream::fromUrl($settings->get('live_stream_url')) : null;

        $videos = collect((array) $settings->get('watch_videos'))
            ->filter(fn ($video) => is_array($video) && filled($video['title'] ?? null) && filled($video['url'] ?? null));
        $thumbnails = Media::query()->publiclyVisible()->whereIn('id', $videos->pluck('image_id')->filter()->all())->get()->keyBy('id');

        return view('live', [
            // The "Think Ocean TV" page in the CMS holds the editable content shown below the player.
            'page' => Page::query()->published()->where('slug', Page::TV_SLUG)->first(),
            'stream' => $stream,
            'title' => $settings->get('live_title') ?: 'Think Ocean TV',
            'description' => $settings->get('live_description'),
            'schedule' => $settings->get('live_schedule'),
            'videos' => $videos->map(fn (array $video) => [...$video, 'image' => $thumbnails->get((int) ($video['image_id'] ?? 0))])->values(),
            'browseUrl' => $settings->get('watch_browse_url'),
        ]);
    }
}
