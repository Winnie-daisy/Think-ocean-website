<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['name', 'email', 'organisation', 'subject', 'message'])]
#[Hidden(['ip_address', 'user_agent'])]
class ContactSubmission extends Model
{
    use HasFactory;

    public const STATUSES = [
        'unread' => 'Unread',
        'read' => 'Read',
        'reviewed' => 'Reviewed',
        'spam' => 'Spam',
    ];

    protected $attributes = [
        'status' => 'unread',
    ];

    protected function casts(): array
    {
        return [
            'reviewed_at' => 'datetime',
        ];
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
