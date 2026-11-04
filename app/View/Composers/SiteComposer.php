<?php

namespace App\View\Composers;

use App\Support\Navigation;
use App\Support\SiteSettings;
use Illuminate\View\View;

/** Shares site settings and menus with the public layout. */
class SiteComposer
{
    public function __construct(
        private readonly SiteSettings $settings,
        private readonly Navigation $navigation,
    ) {}

    public function compose(View $view): void
    {
        $view->with([
            'site' => $this->settings,
            'navigation' => $this->navigation,
        ]);
    }
}
