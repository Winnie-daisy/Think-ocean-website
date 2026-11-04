<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['location', 'label', 'link_type', 'page_id', 'url', 'open_in_new_tab', 'is_active', 'sort_order'])]
class NavigationItem extends Model
{
    public const LOCATIONS = [
        'header' => 'Main navigation (header)',
        'footer' => 'Footer navigation',
        'legal' => 'Footer legal links',
    ];

    protected function casts(): array
    {
        return [
            'open_in_new_tab' => 'boolean',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class);
    }

    /**
     * Resolved link. Page links only resolve while the page is live so menus
     * never point at unpublished content.
     */
    public function href(): ?string
    {
        if ($this->link_type === 'page') {
            return $this->page?->isLive() ? $this->page->publicUrl() : null;
        }

        return $this->url ?: null;
    }

    /** Allow relative paths, http(s) and mailto/tel links only (blocks javascript: URLs). */
    public static function isSafeUrl(?string $url): bool
    {
        if ($url === null || $url === '') {
            return true;
        }

        return (bool) preg_match('#^(/(?!/)|https?://|mailto:|tel:|\#)#i', $url);
    }
}
