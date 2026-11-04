<?php

namespace Tests\Feature\Admin;

use App\Filament\Resources\Media\Pages\CreateMedia;
use App\Models\Media;
use App\Services\MediaLibrary;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class MediaUploadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Storage::fake('public');
    }

    public function test_a_valid_image_is_stored_publicly_with_a_safe_name_and_variants(): void
    {
        $this->actingAs($this->contributor());

        Livewire::test(CreateMedia::class)
            ->fillForm([
                'path' => UploadedFile::fake()->image('My Beach Photo!.jpg', 1200, 800),
                'alt_text' => 'Waves breaking on a sandy beach',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $media = Media::query()->sole();
        $this->assertSame('public', $media->disk);
        $this->assertSame('image/jpeg', $media->mime_type);
        $this->assertSame('My Beach Photo!.jpg', $media->original_name);
        $this->assertMatchesRegularExpression('#^media/\d{4}/\d{2}/[0-9a-z]{26}\.jpe?g$#', $media->path);
        $this->assertSame(1200, $media->width);
        $this->assertNotEmpty($media->variants);
        Storage::disk('public')->assertExists($media->path);
        Storage::disk('local')->assertMissing($media->path);
        foreach ($media->variants as $variant) {
            Storage::disk('public')->assertExists($variant);
        }
    }

    public function test_disallowed_file_types_are_rejected(): void
    {
        $this->actingAs($this->editor());

        foreach ([
            UploadedFile::fake()->create('shell.php', 10, 'application/x-php'),
            UploadedFile::fake()->create('vector.svg', 10, 'image/svg+xml'),
            UploadedFile::fake()->create('script.html', 10, 'text/html'),
        ] as $file) {
            Livewire::test(CreateMedia::class)
                ->fillForm(['path' => $file])
                ->call('create')
                ->assertHasFormErrors(['path']);
        }

        $this->assertSame(0, Media::query()->count());
    }

    public function test_oversized_files_are_rejected(): void
    {
        $this->actingAs($this->editor());

        Livewire::test(CreateMedia::class)
            ->fillForm(['path' => UploadedFile::fake()->create('huge.pdf', Media::MAX_UPLOAD_KB + 1, 'application/pdf')])
            ->call('create')
            ->assertHasFormErrors(['path']);

        $this->assertSame(0, Media::query()->count());
    }

    public function test_a_file_whose_content_does_not_match_an_allowed_type_is_rejected_even_with_an_allowed_extension(): void
    {
        $user = $this->editor();
        Storage::disk('local')->put('media/2026/10/fake.jpg', '<?php echo "not an image";');

        $this->expectException(ValidationException::class);

        try {
            app(MediaLibrary::class)->register('local', 'media/2026/10/fake.jpg', 'fake.jpg', [], $user);
        } finally {
            Storage::disk('local')->assertMissing('media/2026/10/fake.jpg');
            $this->assertSame(0, Media::query()->count());
        }
    }

    public function test_private_documents_stay_on_the_private_disk_and_need_sign_in_to_download(): void
    {
        $editor = $this->editor();
        Storage::disk('local')->put('media/2026/10/board-minutes.pdf', "%PDF-1.4\n%âãÏÓ\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF");

        $media = app(MediaLibrary::class)->register('local', 'media/2026/10/board-minutes.pdf', 'Board minutes.pdf', ['visibility' => Media::VISIBILITY_PRIVATE], $editor);

        $this->assertSame('local', $media->disk);
        $this->assertNull($media->url());
        Storage::disk('public')->assertMissing($media->path);

        $this->get(route('admin.media.download', $media))->assertRedirect('/admin/login');
        $this->actingAs($editor)->get(route('admin.media.download', $media))->assertOk()->assertDownload('Board minutes.pdf');
    }

    public function test_reviewers_cannot_upload(): void
    {
        $this->actingAs($this->reviewer());

        $this->get('/admin/media/create')->assertForbidden();
    }
}
