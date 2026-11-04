<?php

namespace App\Http\Controllers;

use App\Models\Page;
use App\Models\Post;
use App\Models\Project;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

class SitemapController extends Controller
{
    public function __invoke(): Response
    {
        // Short cache: publication changes appear within ten minutes.
        $xml = Cache::remember('sitemap.xml', now()->addMinutes(10), function () {
            $urls = collect([['loc' => route('home'), 'lastmod' => null]]);

            Page::query()->published()->whereNot('slug', 'home')->get(['slug', 'updated_at'])
                ->each(fn (Page $page) => $urls->push(['loc' => $page->publicUrl(), 'lastmod' => $page->updated_at]));

            if (! $urls->contains('loc', route('projects.index'))) {
                $urls->push(['loc' => route('projects.index'), 'lastmod' => null]);
            }

            if (! $urls->contains('loc', route('news.index'))) {
                $urls->push(['loc' => route('news.index'), 'lastmod' => null]);
            }

            if (! $urls->contains('loc', route('contact'))) {
                $urls->push(['loc' => route('contact'), 'lastmod' => null]);
            }

            Project::query()->published()->get(['slug', 'updated_at'])
                ->each(fn (Project $project) => $urls->push(['loc' => $project->publicUrl(), 'lastmod' => $project->updated_at]));

            Post::query()->published()->get(['slug', 'updated_at'])
                ->each(fn (Post $post) => $urls->push(['loc' => $post->publicUrl(), 'lastmod' => $post->updated_at]));

            return view('sitemap', ['urls' => $urls])->render();
        });

        return response($xml, 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }
}
