<?php

namespace Tests\Feature\Admin;

use App\Enums\ReviewStatus;
use App\Filament\Resources\Pages\Pages\CreatePage;
use App\Filament\Resources\Pages\Pages\EditPage;
use App\Filament\Resources\Pages\Pages\ViewPage;
use App\Filament\Resources\Projects\Pages\ListProjects;
use App\Models\Page;
use App\Models\Project;
use App\Services\ContentWorkflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Exercises the real Filament pages, so the admin UI is proven to go through
 * the publishing workflow rather than saving models directly.
 */
class ContentAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_contributor_can_create_a_draft_through_the_admin(): void
    {
        $contributor = $this->contributor();
        $this->actingAs($contributor);

        Livewire::test(CreatePage::class)
            ->fillForm(['title' => 'Beach Cleans', 'slug' => 'beach-cleans', 'intro' => 'Draft intro'])
            ->call('create')
            ->assertHasNoFormErrors();

        $page = Page::query()->where('slug', 'beach-cleans')->firstOrFail();
        $this->assertSame($contributor->id, $page->author_id);
        $this->assertFalse($page->is_published);
        $this->get('/beach-cleans')->assertNotFound();
    }

    public function test_saving_a_live_page_in_the_admin_stores_a_draft_only(): void
    {
        $contributor = $this->contributor();
        $page = app(ContentWorkflow::class)->create(Page::class, ['title' => 'Seagrass', 'slug' => 'seagrass', 'intro' => 'Live intro'], $contributor);
        app(ContentWorkflow::class)->publish($page, $this->editor());

        $this->actingAs($contributor);

        Livewire::test(EditPage::class, ['record' => $page->getRouteKey()])
            ->assertFormSet(['intro' => 'Live intro'])
            ->fillForm(['intro' => 'Proposed intro'])
            ->call('save')
            ->assertHasNoFormErrors();

        $page->refresh();
        $this->assertSame('Live intro', $page->intro);
        $this->assertSame('Proposed intro', $page->draft_content['intro']);
        $this->get('/seagrass')->assertSee('Live intro')->assertDontSee('Proposed intro');

        // Re-opening the editor shows the working copy, not the live version.
        Livewire::test(EditPage::class, ['record' => $page->getRouteKey()])->assertFormSet(['intro' => 'Proposed intro']);
    }

    public function test_contributors_do_not_get_publish_actions_and_cannot_call_them(): void
    {
        $contributor = $this->contributor();
        $page = app(ContentWorkflow::class)->create(Page::class, ['title' => 'Kelp', 'slug' => 'kelp'], $contributor);
        $this->actingAs($contributor);

        Livewire::test(EditPage::class, ['record' => $page->getRouteKey()])
            ->assertActionVisible('submitForReview')
            ->assertActionHidden('publish')
            ->assertActionHidden('unpublish');

        $this->assertFalse($page->fresh()->is_published);
    }

    public function test_contributors_can_submit_and_editors_can_approve_in_the_admin(): void
    {
        $contributor = $this->contributor();
        $editor = $this->editor();
        $page = app(ContentWorkflow::class)->create(Page::class, ['title' => 'Plastics', 'slug' => 'plastics', 'intro' => 'Intro'], $contributor);

        $this->actingAs($contributor);
        Livewire::test(EditPage::class, ['record' => $page->getRouteKey()])
            ->callAction('submitForReview', ['note' => 'Please review']);
        $this->assertSame(ReviewStatus::InReview, $page->fresh()->review_status);

        $this->actingAs($editor);
        Livewire::test(EditPage::class, ['record' => $page->getRouteKey()])
            ->assertActionVisible('publish')
            ->callAction('publish', ['comment' => 'Approved']);

        $this->assertTrue($page->fresh()->is_published);
        $this->get('/plastics')->assertOk();
    }

    public function test_contributors_cannot_open_other_users_content_for_editing(): void
    {
        $page = Page::factory()->create();
        $this->actingAs($this->contributor());

        $this->get("/admin/pages/{$page->id}/edit")->assertForbidden();
        $this->get("/admin/pages/{$page->id}")->assertOk();
    }

    public function test_reviewers_see_content_read_only_without_workflow_actions(): void
    {
        $page = Page::factory()->create();
        $this->actingAs($this->reviewer());

        Livewire::test(ViewPage::class, ['record' => $page->getRouteKey()])
            ->assertActionHidden('edit')
            ->assertActionHidden('publish')
            ->assertActionHidden('submitForReview');
    }

    public function test_the_project_list_shows_working_titles_and_status(): void
    {
        $this->actingAs($this->editor());
        $project = Project::factory()->published()->create(['title' => 'Reef Survey']);

        Livewire::test(ListProjects::class)
            ->assertCanSeeTableRecords([$project])
            ->assertSee('Live');
    }
}
