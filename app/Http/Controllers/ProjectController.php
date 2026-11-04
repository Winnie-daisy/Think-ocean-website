<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Page;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProjectController extends Controller
{
    public function index(Request $request): View
    {
        $categories = Category::query()
            ->ofType(Category::TYPE_PROJECT)
            ->whereHas('projects', fn ($q) => $q->published())
            ->orderBy('name')
            ->get();

        $activeCategory = $categories->firstWhere('slug', (string) $request->query('category'));

        $projects = Project::query()
            ->published()
            ->with(['featuredImage', 'category'])
            ->when($activeCategory, fn ($q) => $q->where('category_id', $activeCategory->getKey()))
            ->orderByDesc('is_featured')
            ->orderByDesc('published_at')
            ->paginate(9)
            ->withQueryString();

        return view('projects.index', [
            'page' => Page::query()->published()->where('slug', 'projects')->first(),
            'projects' => $projects,
            'categories' => $categories,
            'activeCategory' => $activeCategory,
        ]);
    }

    public function show(string $slug): View
    {
        $project = Project::query()
            ->published()
            ->with(['featuredImage', 'category', 'ogImage'])
            ->where('slug', $slug)
            ->firstOrFail();

        $related = Project::query()
            ->published()
            ->with('featuredImage')
            ->whereKeyNot($project->getKey())
            ->when($project->category_id, fn ($q) => $q->where('category_id', $project->category_id))
            ->latest('published_at')
            ->limit(3)
            ->get();

        return view('projects.show', ['project' => $project, 'related' => $related]);
    }
}
