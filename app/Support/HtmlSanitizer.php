<?php

namespace App\Support;

use Symfony\Component\HtmlSanitizer\HtmlSanitizer as SymfonySanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

/**
 * Cleans rich-text HTML from the CMS before it is rendered publicly.
 * Editors are trusted staff, but sanitising protects visitors if an account
 * is compromised and keeps markup consistent.
 */
class HtmlSanitizer
{
    private ?SymfonySanitizer $sanitizer = null;

    public function clean(?string $html): string
    {
        if ($html === null || trim($html) === '') {
            return '';
        }

        return $this->sanitizer()->sanitize($html);
    }

    private function sanitizer(): SymfonySanitizer
    {
        return $this->sanitizer ??= new SymfonySanitizer(
            (new HtmlSanitizerConfig)
                ->allowSafeElements()
                ->blockElement('h1')
                ->allowLinkSchemes(['https', 'http', 'mailto', 'tel'])
                ->allowRelativeLinks()
                ->allowMediaSchemes(['https'])
                ->allowRelativeMedias()
                ->forceAttribute('a', 'rel', 'noopener noreferrer')
                ->dropAttribute('style', '*')
                ->dropAttribute('class', '*')
                ->withMaxInputLength(500_000)
        );
    }
}
