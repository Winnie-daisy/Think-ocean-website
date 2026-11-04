<?php

namespace App\Support;

use App\Models\NavigationItem;
use Illuminate\Support\Collection;

class Navigation
{
    private ?Collection $items = null;

    /** Identity of the request the cached items belong to. */
    private ?int $requestId = null;

    /**
     * Resolved, visible links for a menu location.
     *
     * @return Collection<int, array{label: string, href: string, new_tab: bool, current: bool}>
     */
    public function for(string $location): Collection
    {
        if ($this->requestId !== spl_object_id(request())) {
            $this->items = null;
            $this->requestId = spl_object_id(request());
        }

        $this->items ??= NavigationItem::query()
            ->where('is_active', true)
            ->with('page')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->groupBy('location');

        $current = rtrim(url()->current(), '/');

        return ($this->items->get($location) ?? collect())
            ->map(function (NavigationItem $item) use ($current) {
                $href = $item->href();

                if ($href === null || ! NavigationItem::isSafeUrl($href)) {
                    return null;
                }

                $absolute = rtrim(str_starts_with($href, '/') ? url($href) : $href, '/');

                return [
                    'label' => $item->label,
                    'href' => $href,
                    'new_tab' => $item->open_in_new_tab,
                    'current' => $absolute === $current || ($absolute !== rtrim(url('/'), '/') && str_starts_with($current, $absolute.'/')),
                ];
            })
            ->filter()
            ->values();
    }
}
