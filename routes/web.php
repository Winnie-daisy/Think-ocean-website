<?php

use App\Http\Controllers\Admin\MediaDownloadController;
use App\Http\Controllers\Admin\PreviewController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\DonationController;
use App\Http\Controllers\LiveController;
use App\Http\Controllers\NewsController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\SitemapController;
use App\Http\Controllers\SubscriberController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public website
|--------------------------------------------------------------------------
| Every public controller reads only published content (the `published`
| scope). Drafts are only reachable through the authenticated preview route.
*/

Route::get('/', [PageController::class, 'home'])->name('home');

foreach ([
    'about' => 'about',
    'what-we-do' => 'what-we-do',
    'get-involved' => 'get-involved',
    'privacy-policy' => 'privacy-policy',
    'cookie-policy' => 'cookie-policy',
    'accessibility' => 'accessibility',
] as $slug => $name) {
    Route::get($slug, [PageController::class, 'show'])->defaults('slug', $slug)->name($name);
}

Route::get('projects', [ProjectController::class, 'index'])->name('projects.index');
Route::get('projects/{slug}', [ProjectController::class, 'show'])->name('projects.show');

Route::get('news', [NewsController::class, 'index'])->name('news.index');
Route::get('news/{slug}', [NewsController::class, 'show'])->name('news.show');

Route::get('contact', [ContactController::class, 'show'])->name('contact');
Route::post('contact', [ContactController::class, 'store'])->middleware('throttle:contact')->name('contact.store');

Route::get('watch', LiveController::class)->name('watch');
Route::permanentRedirect('live', '/watch');

Route::post('donate', [DonationController::class, 'store'])->middleware('throttle:contact')->name('donate.store');
Route::get('donate/thank-you', [DonationController::class, 'thanks'])->name('donate.thanks');
Route::get('donate/cancelled/{reference}', [DonationController::class, 'cancelled'])->whereUuid('reference')->name('donate.cancelled');
Route::post('stripe/webhook', [DonationController::class, 'webhook'])->name('stripe.webhook');

Route::post('subscribe', [SubscriberController::class, 'store'])->middleware('throttle:contact')->name('subscribe');

Route::get('search', SearchController::class)->middleware('throttle:search')->name('search');
Route::get('sitemap.xml', SitemapController::class)->name('sitemap');

/*
|--------------------------------------------------------------------------
| Staff-only helpers outside the Filament panel
|--------------------------------------------------------------------------
*/

Route::middleware('auth')->prefix('admin')->name('admin.')->group(function () {
    Route::get('preview/{type}/{id}', PreviewController::class)
        ->whereIn('type', ['page', 'project', 'post'])
        ->whereNumber('id')
        ->name('preview');

    Route::get('files/{media}/download', MediaDownloadController::class)->name('media.download');
});

// Custom CMS pages, e.g. /volunteer-guidelines. Keep this route last.
Route::get('{slug}', [PageController::class, 'show'])
    ->where('slug', '(?!(?:admin|livewire|filament|storage|up|search|live|watch|subscribe)$)[a-z0-9]+(?:-[a-z0-9]+)*')
    ->name('pages.show');
