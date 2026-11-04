<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['type', 'name', 'slug', 'description'])]
class Category extends Model
{
    use HasFactory;

    public const TYPE_PROJECT = 'project';

    public const TYPE_POST = 'post';

    public const TYPES = [
        self::TYPE_PROJECT => 'Projects',
        self::TYPE_POST => 'News and insights',
    ];

    #[Scope]
    protected function ofType(Builder $query, string $type): void
    {
        $query->where('type', $type);
    }

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    public function posts(): BelongsToMany
    {
        return $this->belongsToMany(Post::class);
    }
}
