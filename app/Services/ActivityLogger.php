<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ActivityLogger
{
    public function log(string $event, string $description, ?Model $subject = null, array $properties = [], ?User $user = null): ActivityLog
    {
        $request = app()->runningInConsole() ? null : request();

        $log = new ActivityLog;
        $log->forceFill([
            'user_id' => ($user ?? auth()->user())?->getKey(),
            'event' => $event,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'description' => Str::limit($description, 250),
            'properties' => $properties ?: null,
            'ip_address' => $request?->ip(),
            'user_agent' => $request ? Str::limit((string) $request->userAgent(), 500, '') : null,
            'created_at' => now(),
        ])->save();

        return $log;
    }
}
