<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;

/**
 * State of a record's working copy. Whether the record is live is tracked
 * separately (is_published), so a live page can also have a draft in review.
 */
enum ReviewStatus: string implements HasColor, HasIcon, HasLabel
{
    case Draft = 'draft';
    case InReview = 'in_review';
    case ChangesRequested = 'changes_requested';
    case Rejected = 'rejected';
    case Published = 'published';

    public function getLabel(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::InReview => 'Submitted for review',
            self::ChangesRequested => 'Changes requested',
            self::Rejected => 'Rejected',
            self::Published => 'Published',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Draft => 'gray',
            self::InReview => 'info',
            self::ChangesRequested => 'warning',
            self::Rejected => 'danger',
            self::Published => 'success',
        };
    }

    public function getIcon(): Heroicon
    {
        return match ($this) {
            self::Draft => Heroicon::OutlinedPencil,
            self::InReview => Heroicon::OutlinedClock,
            self::ChangesRequested => Heroicon::OutlinedArrowUturnLeft,
            self::Rejected => Heroicon::OutlinedXCircle,
            self::Published => Heroicon::OutlinedCheckCircle,
        };
    }

    /** Statuses from which the working copy may be submitted for review. */
    public function canBeSubmitted(): bool
    {
        return in_array($this, [self::Draft, self::ChangesRequested, self::Rejected], true);
    }
}
