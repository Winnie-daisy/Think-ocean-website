<?php

namespace App\Http\Controllers;

use App\Models\Page;
use App\Models\Post;
use App\Models\Project;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

/** Site search across published content only. */
class SearchController extends Controller
{
    private const PER_TYPE = 20;

    public function __invoke(Request $request): View
    {
        $query = Str::limit(trim((string) $request->query('q', '')), 100, '');
        $results = collect();

        if (mb_strlen($query) >= 2) {
            $term = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $query).'%';

            $pages = Page::query()->published()
                ->whereNotIn('slug', ['home'])
                ->where(fn (Builder $q) => $q->where('title', 'like', $term)->orWhere('intro', 'like', $term)->orWhere('sections', 'like', $term))
                ->limit(self::PER_TYPE)->get()
                ->map(fn (Page $page) => [
                    'type' => 'Page',
                    'title' => $page->title,
                    'url' => $page->publicUrl(),
                    'summary' => $page->meta_description ?: $page->intro,
                    'date' => null,
                ]);

            $projects = Project::query()->published()
                ->where(fn (Builder $q) => $q->where('title', 'like', $term)->orWhere('summary', 'like', $term)->orWhere('description', 'like', $term))
                ->latest('published_at')->limit(self::PER_TYPE)->get()
                ->map(fn (Project $project) => [
                    'type' => 'Project',
                    'title' => $project->title,
                    'url' => $project->publicUrl(),
                    'summary' => $project->summary,
                    'date' => null,
                ]);

            $posts = Post::query()->published()
                ->where(fn (Builder $q) => $q->where('title', 'like', $term)->orWhere('excerpt', 'like', $term)->orWhere('body', 'like', $term))
                ->latest('published_at')->limit(self::PER_TYPE)->get()
                ->map(fn (Post $post) => [
                    'type' => 'News',
                    'title' => $post->title,
                    'url' => $post->publicUrl(),
                    'summary' => $post->excerpt,
                    'date' => $post->published_at,
                ]);

            $results = $pages->concat($projects)->concat($posts);
        }

        return view('search', ['query' => $query, 'results' => $results]);
    }
}
