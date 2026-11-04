<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/** Runs against the in-memory test database only. */
class MigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_migrations_create_the_expected_tables_and_can_be_rolled_back_and_re_run(): void
    {
        foreach (['users', 'pages', 'projects', 'posts', 'media', 'categories', 'tags', 'category_post', 'post_tag',
            'content_revisions', 'settings', 'navigation_items', 'contact_submissions', 'activity_logs'] as $table) {
            $this->assertTrue(Schema::hasTable($table), "Missing table {$table}");
        }

        $this->artisan('migrate:rollback', ['--force' => true])->assertSuccessful();
        $this->assertFalse(Schema::hasTable('pages'));

        $this->artisan('migrate', ['--force' => true])->assertSuccessful();
        $this->assertTrue(Schema::hasTable('pages'));
        $this->assertTrue(Schema::hasColumns('pages', ['draft_content', 'is_published', 'review_status']));
    }

    public function test_the_seeder_is_idempotent(): void
    {
        $this->seed();
        $this->seed();

        $this->assertDatabaseCount('pages', 10);
        $this->assertDatabaseCount('navigation_items', 14);
    }
}
