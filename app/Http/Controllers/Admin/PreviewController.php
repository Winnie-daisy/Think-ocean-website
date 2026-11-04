<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Page;
use App\Models\Post;
use App\Models\Project;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

/**
 * Renders the working copy (draft) of content with the public templates,
 * for signed-in staff only. The preview model is an unsaved in-memory copy,
 * so nothing here can change live content.
 */
class PreviewController extends Controller
{
    private const TYPES = [
        'page' => Page::class,
        'project' => Project::class,
        'post' => Post::class,
    ];

    public function __invoke(string $type, int $id): Response
    {
        $class = self::TYPES[$type] ?? abort(404);
        $record = $class::query()->withTrashed()->findOrFail($id);

        Gate::authorize('view', $record);

        $preview = (clone $record)->fillContent($record->workingContent());

        $view = match ($type) {
            'page' => $preview->slug === 'home' ? view('pages.home', ['page' => $preview]) : view('pages.show', ['page' => $preview]),
            'project' => view('projects.show', ['project' => $preview, 'related' => collect()]),
            'post' => view('news.show', ['post' => $preview->setRelation('author', $record->author), 'related' => collect()]),
        };

        view()->share('isPreview', true);

        return response($view->render())
            ->header('X-Robots-Tag', 'noindex, nofollow')
            ->header('Cache-Control', 'no-store, private');
    }
}
