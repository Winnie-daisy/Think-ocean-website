<?php

namespace App\Rules;

use App\Models\NavigationItem;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Accepts site-relative paths ("/about"), http(s), mailto: and tel: links.
 * Rejects javascript:, data: and protocol-relative URLs.
 */
class SafeUrl implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! NavigationItem::isSafeUrl($value)) {
            $fail('Enter a link starting with "/", "https://", "mailto:" or "tel:".');

            return;
        }

        if (preg_match('#^https?://#i', $value) && filter_var($value, FILTER_VALIDATE_URL) === false) {
            $fail('Enter a valid web address.');
        }
    }
}
