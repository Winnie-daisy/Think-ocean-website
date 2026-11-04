<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Page;
use App\Models\Post;
use App\Models\Tag;
use Illuminate\Http\Request;
use Illuminate\View\View;

class NewsController extends Controller
{
    public function index(Request $request): View
    {
        $categories = Category::query()
            ->ofType(Category::TYPE_POST)
            ->whereHas('posts', fn ($q) => $q->published())
            ->orderBy('name')
            ->get();

        $activeCategory = $categories->firstWhere('slug', (string) $request->query('category'));
        $activeTag = $request->filled('tag') ? Tag::query()->where('slug', (string) $request->query('tag'))->first() : null;

        $posts = Post::query()
            ->published()
            ->with('featuredImage')
            ->when($activeCategory, fn ($q) => $q->whereHas('categories', fn ($c) => $c->whereKey($activeCategory->getKey())))
            ->when($activeTag, fn ($q) => $q->whereHas('tags', fn ($t) => $t->whereKey($activeTag->getKey())))
            ->latest('published_at')
            ->paginate(9)
            ->withQueryString();

        return view('news.index', [
            'page' => Page::query()->published()->where('slug', 'news')->first(),
            'posts' => $posts,
            'categories' => $categories,
            'activeCategory' => $activeCategory,
            'activeTag' => $activeTag,
        ]);
    }

    public function show(string $slug): View
    {
        $post = Post::query()
            ->published()
            ->with(['featuredImage', 'ogImage', 'categories', 'tags', 'author'])
            ->where('slug', $slug)
            ->firstOrFail();

        $related = Post::query()
            ->published()
            ->with('featuredImage')
            ->whereKeyNot($post->getKey())
            ->latest('published_at')
            ->limit(3)
            ->get();

        return view('news.show', ['post' => $post, 'related' => $related]);
    }
}
