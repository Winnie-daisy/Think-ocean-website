<?php

namespace Tests\Feature\Workflow;

use App\Enums\ReviewStatus;
use App\Enums\RevisionAction;
use App\Exceptions\LiveContentProtectedException;
use App\Models\ActivityLog;
use App\Models\Category;
use App\Models\Page;
use App\Models\Post;
use App\Models\Project;
use App\Services\ContentWorkflow;
use App\Services\WorkflowException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PublishingWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private ContentWorkflow $workflow;

    protected function setUp(): void
    {
        parent::setUp();

        $this->workflow = app(ContentWorkflow::class);
    }

    private function draftPage(array $attributes = [], $author = null): Page
    {
        return $this->workflow->create(Page::class, array_merge([
            'title' => 'Ocean Literacy',
            'slug' => 'ocean-literacy',
            'intro' => 'Original introduction.',
        ], $attributes), $author ?? $this->contributor());
    }

    public function test_new_content_starts_as_an_unpublished_draft(): void
    {
        $page = $this->draftPage();

        $this->assertFalse($page->is_published);
        $this->assertSame(ReviewStatus::Draft, $page->review_status);
        $this->get('/ocean-literacy')->assertNotFound();
    }

    public function test_contributors_cannot_publish_content(): void
    {
        $contributor = $this->contributor();
        $page = $this->draftPage(author: $contributor);

        $this->expectException(AuthorizationException::class);

        try {
            $this->workflow->publish($page, $contributor);
        } finally {
            $this->assertFalse($page->fresh()->is_published);
        }
    }

    public function test_reviewers_cannot_modify_or_publish_content(): void
    {
        $reviewer = $this->reviewer();
        $page = $this->draftPage();

        foreach ([
            fn () => $this->workflow->saveDraft($page, ['title' => 'Changed'], $reviewer),
            fn () => $this->workflow->publish($page, $reviewer),
            fn () => $this->workflow->create(Page::class, ['title' => 'X', 'slug' => 'x'], $reviewer),
        ] as $attempt) {
            try {
                $attempt();
                $this->fail('Reviewer action should have been refused.');
            } catch (AuthorizationException) {
                // expected
            }
        }

        $this->assertSame('Ocean Literacy', $page->fresh()->title);
    }

    public function test_contributors_cannot_edit_other_users_content(): void
    {
        $page = $this->draftPage(author: $this->contributor());
        $otherContributor = $this->contributor();

        $this->expectException(AuthorizationException::class);
        $this->workflow->saveDraft($page, ['title' => 'Hijacked'], $otherContributor);
    }

    public function test_contributors_cannot_edit_while_content_is_in_review(): void
    {
        $contributor = $this->contributor();
        $page = $this->draftPage(author: $contributor);
        $this->workflow->submit($page, $contributor);

        $this->assertFalse($contributor->can('update', $page->fresh()));

        $this->expectException(AuthorizationException::class);
        $this->workflow->saveDraft($page->fresh(), ['title' => 'Sneaky change'], $contributor);
    }

    public function test_editing_published_content_does_not_change_the_live_version(): void
    {
        $contributor = $this->contributor();
        $editor = $this->editor();
        $page = $this->draftPage(author: $contributor);
        $this->workflow->publish($page, $editor);

        $this->get('/ocean-literacy')->assertOk()->assertSee('Original introduction.');

        $this->workflow->saveDraft($page->fresh(), ['intro' => 'Unapproved new introduction.'], $contributor);

        $page->refresh();
        $this->assertSame('Original introduction.', $page->intro);
        $this->assertSame('Unapproved new introduction.', $page->draft_content['intro']);
        $this->assertSame('Unapproved new introduction.', $page->workingContent()['intro']);
        $this->assertTrue($page->is_published);

        $this->get('/ocean-literacy')
            ->assertOk()
            ->assertSee('Original introduction.')
            ->assertDontSee('Unapproved new introduction.');
    }

    public function test_only_approved_changes_become_public(): void
    {
        $contributor = $this->contributor();
        $editor = $this->editor();
        $page = $this->draftPage(author: $contributor);
        $this->workflow->publish($page, $editor);

        $this->workflow->saveDraft($page->fresh(), ['intro' => 'Revised introduction.'], $contributor);
        $this->workflow->submit($page->fresh(), $contributor, 'Ready for review');
        $this->get('/ocean-literacy')->assertDontSee('Revised introduction.');

        $this->workflow->publish($page->fresh(), $editor, 'Looks good');

        $page->refresh();
        $this->assertSame('Revised introduction.', $page->intro);
        $this->assertNull($page->draft_content);
        $this->assertSame(ReviewStatus::Published, $page->review_status);
        $this->assertSame($editor->id, $page->reviewer_id);
        $this->get('/ocean-literacy')->assertSee('Revised introduction.');
    }

    public function test_returning_and_rejecting_require_a_comment_and_keep_the_draft(): void
    {
        $contributor = $this->contributor();
        $editor = $this->editor();
        $page = $this->draftPage(author: $contributor);
        $this->workflow->submit($page, $contributor);

        try {
            $this->workflow->requestChanges($page->fresh(), $editor, '   ');
            $this->fail('A blank comment should be refused.');
        } catch (ValidationException) {
            // expected
        }

        $this->workflow->requestChanges($page->fresh(), $editor, 'Please add a source for the figures.');
        $page->refresh();
        $this->assertSame(ReviewStatus::ChangesRequested, $page->review_status);
        $this->assertSame('Please add a source for the figures.', $page->review_comment);
        $this->assertTrue($contributor->can('update', $page));

        $this->workflow->submit($page, $contributor);
        $this->workflow->reject($page->fresh(), $editor, 'Out of scope for the website.');
        $page->refresh();
        $this->assertSame(ReviewStatus::Rejected, $page->review_status);
        $this->assertFalse($page->is_published);
    }

    public function test_only_submitted_content_can_be_returned_or_rejected(): void
    {
        $page = $this->draftPage();

        $this->expectException(AuthorizationException::class);
        $this->workflow->reject($page, $this->editor(), 'No');
    }

    public function test_revision_history_records_who_did_what(): void
    {
        $contributor = $this->contributor();
        $editor = $this->editor();
        $page = $this->draftPage(author: $contributor);
        $this->workflow->publish($page, $editor);
        $this->workflow->saveDraft($page->fresh(), ['title' => 'Ocean Literacy Programme'], $contributor);
        $this->workflow->submit($page->fresh(), $contributor, 'Renamed');
        $this->workflow->publish($page->fresh(), $editor, 'Approved');

        $revisions = $page->revisions()->get()->sortBy('id')->values();

        $this->assertSame(
            [RevisionAction::Created, RevisionAction::Published, RevisionAction::DraftSaved, RevisionAction::Submitted, RevisionAction::Published],
            $revisions->pluck('action')->all(),
        );

        $draftSaved = $revisions[2];
        $this->assertSame($contributor->id, $draftSaved->user_id);
        $this->assertSame('Ocean Literacy', $draftSaved->previous_content['title']);
        $this->assertSame('Ocean Literacy Programme', $draftSaved->new_content['title']);
        $this->assertArrayHasKey('title', $draftSaved->changes());

        $finalPublish = $revisions[4];
        $this->assertSame($editor->id, $finalPublish->user_id);
        $this->assertSame('Approved', $finalPublish->comment);
        $this->assertSame('Ocean Literacy', $finalPublish->previous_content['title']);
        $this->assertSame('Ocean Literacy Programme', $finalPublish->new_content['title']);

        $this->assertTrue(ActivityLog::query()->where('event', 'content.published')->where('user_id', $editor->id)->exists());
    }

    public function test_live_content_cannot_be_changed_outside_the_workflow(): void
    {
        $page = Page::factory()->published()->create(['intro' => 'Live text']);

        $this->expectException(LiveContentProtectedException::class);
        $page->fresh()->forceFill(['intro' => 'Bypassed the workflow'])->save();
    }

    public function test_content_cannot_be_published_by_setting_the_flag_directly(): void
    {
        $page = Page::factory()->create();

        $this->expectException(LiveContentProtectedException::class);
        $page->forceFill(['is_published' => true, 'published_at' => now()])->save();
    }

    public function test_content_containing_placeholders_cannot_be_published(): void
    {
        $page = $this->draftPage(['intro' => '[PLACEHOLDER: introduction]']);

        try {
            $this->workflow->publish($page, $this->editor());
            $this->fail('Placeholder content should not be publishable.');
        } catch (WorkflowException $e) {
            $this->assertStringContainsString('placeholder', $e->getMessage());
        }

        $this->assertFalse($page->fresh()->is_published);
    }

    public function test_system_page_slugs_cannot_be_changed(): void
    {
        $page = Page::factory()->create(['slug' => 'about']);
        $page->forceFill(['is_system' => true])->save();

        $this->workflow->saveDraft($page, ['slug' => 'something-else', 'title' => 'About us'], $this->editor());

        $this->assertSame('about', $page->fresh()->slug);
        $this->assertSame('About us', $page->fresh()->title);
    }

    public function test_unpublishing_removes_content_from_the_site(): void
    {
        $editor = $this->editor();
        $page = $this->draftPage();
        $this->workflow->publish($page, $editor);
        $this->get('/ocean-literacy')->assertOk();

        $this->workflow->unpublish($page->fresh(), $editor, 'Out of date');

        $this->get('/ocean-literacy')->assertNotFound();
    }

    public function test_discarding_a_draft_restores_the_live_version_in_the_editor(): void
    {
        $editor = $this->editor();
        $page = $this->draftPage();
        $this->workflow->publish($page, $editor);
        $this->workflow->saveDraft($page->fresh(), ['intro' => 'Temporary'], $editor);

        $this->workflow->discardDraft($page->fresh(), $editor);

        $page->refresh();
        $this->assertNull($page->draft_content);
        $this->assertSame('Original introduction.', $page->workingContent()['intro']);
        $this->assertSame(ReviewStatus::Published, $page->review_status);
    }

    public function test_post_taxonomy_changes_are_held_in_the_draft_until_published(): void
    {
        $editor = $this->editor();
        $original = Category::factory()->create();
        $replacement = Category::factory()->create();

        $post = $this->workflow->create(Post::class, [
            'title' => 'Seagrass Update', 'slug' => 'seagrass-update', 'excerpt' => 'Summary', 'body' => '<p>Body</p>',
            'category_ids' => [$original->id],
        ], $editor);
        $this->workflow->publish($post, $editor);

        $this->workflow->saveDraft($post->fresh(), ['category_ids' => [$replacement->id]], $editor);
        $this->assertSame([$original->id], $post->fresh()->categories->pluck('id')->all());

        $this->workflow->publish($post->fresh(), $editor);
        $this->assertSame([$replacement->id], $post->fresh()->categories->pluck('id')->all());
    }

    public function test_publishing_fails_when_the_slug_is_already_taken(): void
    {
        $editor = $this->editor();
        Project::factory()->create(['slug' => 'reef-survey']);
        $project = $this->workflow->create(Project::class, ['title' => 'Reef', 'slug' => 'reef-survey-2', 'summary' => 'S'], $editor);
        $this->workflow->publish($project, $editor);

        // Force a clash in the draft, as could happen if two people edit at once.
        $project->refresh()->forceFill(['draft_content' => ['slug' => 'reef-survey']])->save();

        $this->expectException(ValidationException::class);
        $this->workflow->publish($project->fresh(), $editor);
    }
}
