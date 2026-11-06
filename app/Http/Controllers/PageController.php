<?php

namespace App\Http\Controllers;

use App\Models\Page;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class PageController extends Controller
{
    public function home(): View
    {
        $page = Page::query()->published()->where('slug', 'home')->first();

        return view('pages.home', ['page' => $page]);
    }

    public function show(string $slug): View|RedirectResponse
    {
        // Core pages with their own URL are not duplicated at /{slug}.
        $canonical = match ($slug) {
            'home' => route('home'),
            'projects' => route('projects.index'),
            'news' => route('news.index'),
            'contact' => route('contact'),
            Page::TV_SLUG => route('watch'),
            default => null,
        };

        if ($canonical) {
            return redirect()->to($canonical, 301);
        }

        abort_if(in_array($slug, Page::RESERVED_SLUGS, true), 404);

        $page = Page::query()->published()->where('slug', $slug)->firstOrFail();

        return view('pages.show', ['page' => $page]);
    }
}
