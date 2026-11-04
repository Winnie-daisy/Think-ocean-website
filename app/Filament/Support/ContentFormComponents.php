<?php

namespace App\Filament\Support;

use App\Enums\ReviewStatus;
use App\Rules\SafeUrl;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Set;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Form pieces shared by the page, project and news resources.
 */
class ContentFormComponents
{
    /** Banner explaining the state of the record and any reviewer feedback. */
    public static function workflowNotice(): Callout
    {
        return Callout::make(fn (?Model $record): ?string => match (true) {
            $record === null => null,
            $record->review_status === ReviewStatus::ChangesRequested => 'Changes requested by the reviewer',
            $record->review_status === ReviewStatus::Rejected => 'This submission was rejected',
            $record->review_status === ReviewStatus::InReview => 'Submitted for review',
            $record->is_published && $record->hasPendingDraft() => 'You are editing a draft of live content',
            $record->is_published => 'This content is live',
            default => 'Draft — not visible on the website',
        })
            ->description(fn (?Model $record): ?string => match (true) {
                $record === null => null,
                filled($record->review_comment) && in_array($record->review_status, [ReviewStatus::ChangesRequested, ReviewStatus::Rejected], true) => 'Reviewer comment: '.$record->review_comment,
                $record->review_status === ReviewStatus::InReview => 'An editor will review these changes. Contributors cannot edit while a review is in progress.',
                $record->is_published && $record->hasPendingDraft() => 'The website still shows the previously published version. Your changes go live only after an editor approves them.',
                $record->is_published => 'Saving changes creates a draft. The live version stays unchanged until the draft is approved and published.',
                default => 'Save your work, then submit it for review when it is ready.',
            })
            ->status(fn (?Model $record): string => match (true) {
                $record === null => 'info',
                $record->review_status === ReviewStatus::Rejected => 'danger',
                $record->review_status === ReviewStatus::ChangesRequested => 'warning',
                $record->is_published && ! $record->hasPendingDraft() => 'success',
                default => 'info',
            })
            ->visible(fn (?Model $record): bool => $record !== null)
            ->columnSpanFull();
    }

    public static function title(): TextInput
    {
        return TextInput::make('title')
            ->required()
            ->maxLength(200)
            ->live(onBlur: true)
            ->afterStateUpdated(function (?string $state, Set $set, ?string $operation): void {
                if ($operation === 'create') {
                    $set('slug', Str::slug((string) $state));
                }
            });
    }

    public static function slug(string $table, ?string $prefix = null): TextInput
    {
        return TextInput::make('slug')
            ->label('URL slug')
            ->required()
            ->maxLength(200)
            ->alphaDash()
            ->rule('regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/')
            ->validationMessages(['regex' => 'Use lowercase letters, numbers and single hyphens only.'])
            ->unique($table, 'slug', ignoreRecord: true)
            ->prefix($prefix)
            ->helperText('Part of the web address. Changing it on live content breaks existing links.');
    }

    public static function seo(): Section
    {
        return Section::make('Search engines and social sharing')
            ->description('Leave blank to use the title and summary.')
            ->collapsible()
            ->collapsed()
            ->schema([
                TextInput::make('meta_title')
                    ->label('Page title for search engines')
                    ->maxLength(70)
                    ->helperText('Shown in browser tabs and search results. Around 50–60 characters works best.'),
                Textarea::make('meta_description')
                    ->label('Meta description')
                    ->rows(3)
                    ->maxLength(320)
                    ->helperText('A one or two sentence summary shown in search results. Around 150–160 characters.'),
                MediaPicker::image('og_image_id', 'Social sharing image')
                    ->helperText('Shown when the link is shared on social media. Ideally 1200 × 630 pixels.'),
            ]);
    }

    public static function link(string $name, string $label): TextInput
    {
        return TextInput::make($name)
            ->label($label)
            ->maxLength(500)
            ->rule(new SafeUrl)
            ->placeholder('/get-involved');
    }
}
