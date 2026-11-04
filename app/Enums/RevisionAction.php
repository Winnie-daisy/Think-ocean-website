<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum RevisionAction: string implements HasLabel
{
    case Created = 'created';
    case DraftSaved = 'draft_saved';
    case Submitted = 'submitted';
    case Published = 'published';
    case ChangesRequested = 'changes_requested';
    case Rejected = 'rejected';
    case Unpublished = 'unpublished';
    case DraftDiscarded = 'draft_discarded';
    case Deleted = 'deleted';
    case Restored = 'restored';

    public function getLabel(): string
    {
        return match ($this) {
            self::Created => 'Created',
            self::DraftSaved => 'Draft saved',
            self::Submitted => 'Submitted for review',
            self::Published => 'Approved and published',
            self::ChangesRequested => 'Returned for changes',
            self::Rejected => 'Rejected',
            self::Unpublished => 'Unpublished',
            self::DraftDiscarded => 'Draft discarded',
            self::Deleted => 'Deleted',
            self::Restored => 'Restored',
        };
    }
}
