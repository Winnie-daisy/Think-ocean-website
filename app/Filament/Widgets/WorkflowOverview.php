<?php

namespace App\Filament\Widgets;

use App\Enums\Permission;
use App\Enums\ReviewStatus;
use App\Filament\Resources\ContactSubmissions\ContactSubmissionResource;
use App\Models\ContactSubmission;
use App\Models\Page;
use App\Models\Post;
use App\Models\Project;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/** Dashboard summary of work waiting in the publishing workflow. */
class WorkflowOverview extends StatsOverviewWidget
{
    protected static ?int $sort = 2;

    protected function getStats(): array
    {
        $user = auth()->user();
        $models = [Page::class, Project::class, Post::class];

        $inReview = collect($models)->sum(fn ($m) => $m::query()->where('review_status', ReviewStatus::InReview)->count());
        $returned = collect($models)->sum(fn ($m) => $m::query()
            ->whereIn('review_status', [ReviewStatus::ChangesRequested, ReviewStatus::Rejected])
            ->where('author_id', $user->getKey())
            ->count());
        $pending = collect($models)->sum(fn ($m) => $m::query()->whereNotNull('draft_content')->count());

        $stats = [
            Stat::make('Awaiting review', $inReview)
                ->description($user->hasPermission(Permission::PublishContent) ? 'Submissions for you to approve' : 'Submitted and waiting for an editor')
                ->icon('heroicon-o-clock'),
            Stat::make('Returned to you', $returned)
                ->description('Changes requested or rejected')
                ->icon('heroicon-o-arrow-uturn-left'),
            Stat::make('Live content with unpublished changes', $pending)
                ->icon('heroicon-o-pencil-square'),
        ];

        if ($user->hasPermission(Permission::ViewSubmissions)) {
            $stats[] = Stat::make('Unread contact messages', ContactSubmission::query()->where('status', 'unread')->count())
                ->icon('heroicon-o-inbox')
                ->url(ContactSubmissionResource::getUrl('index'));
        }

        return $stats;
    }
}
