<?php

namespace App\Filament\Support;

use Filament\Forms\Components\Builder;
use Filament\Forms\Components\Builder\Block;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Support\Icons\Heroicon;

/**
 * Structured sections staff can add to any page. Each block type has a
 * matching Blade partial in resources/views/sections/.
 */
class PageBlocks
{
    /** Icons available for feature items, mapped to Heroicon names used by the public site. */
    public const ICONS = [
        'globe' => 'Globe',
        'beaker' => 'Research / science',
        'academic-cap' => 'Education',
        'users' => 'Community',
        'megaphone' => 'Campaigning',
        'heart' => 'Wellbeing',
        'book-open' => 'Learning resources',
        'sparkles' => 'Innovation',
        'hand-raised' => 'Volunteering',
        'chart-bar' => 'Evidence / data',
        'lifebuoy' => 'Support',
        'map' => 'Places',
        'sun' => 'Climate',
        'camera' => 'Media',
        'eye' => 'Vision',
        'light-bulb' => 'Ideas',
        'shield-check' => 'Integrity',
        'trophy' => 'Impact',
    ];

    public static function builder(): Builder
    {
        return Builder::make('sections')
            ->label('Page sections')
            ->blocks([
                static::hero(),
                static::richText(),
                static::featureGrid(),
                static::imageText(),
                static::facts(),
                static::callToAction(),
                static::featuredProjects(),
                static::latestNews(),
                static::partners(),
                static::quote(),
                static::team(),
                static::image(),
                static::enquiryForm(),
                static::donationForm(),
            ])
            ->collapsible()
            ->cloneable()
            ->blockNumbers(false)
            ->addActionLabel('Add a section')
            ->columnSpanFull();
    }

    public static function richEditor(string $name, string $label = 'Text'): RichEditor
    {
        return RichEditor::make($name)
            ->label($label)
            ->toolbarButtons([
                ['bold', 'italic', 'link'],
                ['h2', 'h3'],
                ['blockquote', 'bulletList', 'orderedList'],
                ['undo', 'redo'],
            ])
            ->fileAttachments(false);
    }

    private static function hero(): Block
    {
        return Block::make('hero')
            ->label('Hero banner')
            ->icon(Heroicon::OutlinedPhoto)
            ->schema([
                TextInput::make('heading')->required()->maxLength(120),
                Textarea::make('text')->label('Introductory text')->rows(3)->maxLength(400),
                MediaPicker::image('image_id', 'Background image')
                    ->helperText('A wide, high-quality landscape photograph (at least 1920 px wide). A dark overlay is added for readability. Leave blank for the default ocean background.'),
                Grid::make(2)->schema([
                    TextInput::make('primary_label')->label('Primary button label')->maxLength(40),
                    ContentFormComponents::link('primary_url', 'Primary button link'),
                    TextInput::make('secondary_label')->label('Secondary button label')->maxLength(40),
                    ContentFormComponents::link('secondary_url', 'Secondary button link'),
                ]),
            ]);
    }

    private static function richText(): Block
    {
        return Block::make('rich_text')
            ->label('Text')
            ->icon(Heroicon::OutlinedDocumentText)
            ->schema([
                TextInput::make('eyebrow')->label('Small label above heading (optional)')->maxLength(60),
                TextInput::make('heading')->maxLength(150),
                static::richEditor('body')->required(),
            ]);
    }

    private static function featureGrid(): Block
    {
        return Block::make('feature_grid')
            ->label('Feature list (e.g. What we do)')
            ->icon(Heroicon::OutlinedSquares2x2)
            ->schema([
                TextInput::make('eyebrow')->label('Small label above heading (optional)')->maxLength(60),
                TextInput::make('heading')->maxLength(150),
                Textarea::make('intro')->rows(2)->maxLength(500),
                Select::make('layout')
                    ->options(['tiles' => 'Separate tiles', 'panel' => 'One panel with dividers (e.g. Mission / Vision / Values)'])
                    ->default('tiles')
                    ->selectablePlaceholder(false),
                Repeater::make('items')
                    ->schema([
                        TextInput::make('title')->required()->maxLength(100),
                        Textarea::make('text')->rows(3)->maxLength(500),
                        Grid::make(2)->schema([
                            Select::make('icon')->options(self::ICONS)->placeholder('No icon'),
                            MediaPicker::image('image_id', 'Image (optional, replaces icon)'),
                        ]),
                        Grid::make(2)->schema([
                            TextInput::make('link_label')->maxLength(40),
                            ContentFormComponents::link('link_url', 'Link'),
                        ]),
                    ])
                    ->itemLabel(fn (array $state): ?string => $state['title'] ?? null)
                    ->collapsible()
                    ->minItems(1)
                    ->maxItems(12)
                    ->addActionLabel('Add item'),
            ]);
    }

    private static function imageText(): Block
    {
        return Block::make('image_text')
            ->label('Image with text')
            ->icon(Heroicon::OutlinedNewspaper)
            ->schema([
                TextInput::make('eyebrow')->label('Small label above heading (optional)')->maxLength(60),
                TextInput::make('heading')->maxLength(150),
                static::richEditor('body'),
                Grid::make(2)->schema([
                    MediaPicker::image('image_id', 'Image'),
                    Select::make('image_position')->options(['left' => 'Image on the left', 'right' => 'Image on the right'])->default('right')->selectablePlaceholder(false),
                ]),
                Grid::make(2)->schema([
                    TextInput::make('link_label')->label('Button label')->maxLength(40),
                    ContentFormComponents::link('link_url', 'Button link'),
                ]),
            ]);
    }

    private static function facts(): Block
    {
        return Block::make('facts')
            ->label('Key facts')
            ->icon(Heroicon::OutlinedChartBar)
            ->schema([
                TextInput::make('heading')->maxLength(150),
                Repeater::make('items')
                    ->schema([
                        TextInput::make('value')->label('Figure')->required()->maxLength(20),
                        TextInput::make('label')->required()->maxLength(120),
                        TextInput::make('source')->label('Source (required for credibility)')->required()->maxLength(200),
                    ])
                    ->columns(3)
                    ->minItems(1)
                    ->maxItems(6),
            ])
            ->columns(1);
    }

    private static function callToAction(): Block
    {
        return Block::make('call_to_action')
            ->label('Call to action')
            ->icon(Heroicon::OutlinedMegaphone)
            ->schema([
                TextInput::make('heading')->required()->maxLength(150),
                Textarea::make('text')->rows(3)->maxLength(500),
                MediaPicker::image('image_id', 'Background image (optional)'),
                Grid::make(2)->schema([
                    TextInput::make('primary_label')->label('Primary button label')->maxLength(40),
                    ContentFormComponents::link('primary_url', 'Primary button link'),
                    TextInput::make('secondary_label')->label('Secondary button label')->maxLength(40),
                    ContentFormComponents::link('secondary_url', 'Secondary button link'),
                ]),
            ]);
    }

    private static function featuredProjects(): Block
    {
        return Block::make('featured_projects')
            ->label('Featured projects (automatic)')
            ->icon(Heroicon::OutlinedGlobeEuropeAfrica)
            ->schema([
                TextInput::make('eyebrow')->label('Small label above heading (optional)')->maxLength(60),
                TextInput::make('heading')->default('Featured projects')->maxLength(150),
                Textarea::make('intro')->rows(2)->maxLength(500),
                Select::make('style')
                    ->options(['cards' => 'Cards with text', 'gallery' => 'Photo strip with a “View All Projects” button'])
                    ->default('cards')
                    ->selectablePlaceholder(false),
                Select::make('limit')->options([3 => '3', 6 => '6'])->default(3)->selectablePlaceholder(false),
            ]);
    }

    private static function partners(): Block
    {
        return Block::make('partners')
            ->label('Partners')
            ->icon(Heroicon::OutlinedUserGroup)
            ->schema([
                TextInput::make('heading')->default('Our Partners')->maxLength(150),
                Textarea::make('intro')->rows(2)->maxLength(500),
                Repeater::make('items')
                    ->label('Partners')
                    ->schema([
                        TextInput::make('name')->required()->maxLength(120),
                        ContentFormComponents::link('url', 'Website'),
                        MediaPicker::image('image_id', 'Logo (optional)'),
                    ])
                    ->columns(3)
                    ->itemLabel(fn (array $state): ?string => $state['name'] ?? null)
                    ->collapsible()
                    ->minItems(1)
                    ->maxItems(30)
                    ->addActionLabel('Add partner'),
                Grid::make(2)->schema([
                    TextInput::make('link_label')->label('Link label (optional)')->maxLength(40),
                    ContentFormComponents::link('link_url', 'Link'),
                ]),
            ]);
    }

    private static function quote(): Block
    {
        return Block::make('quote')
            ->label('Quote')
            ->icon(Heroicon::OutlinedChatBubbleBottomCenterText)
            ->schema([
                Textarea::make('text')->label('Quote')->required()->rows(2)->maxLength(300),
                TextInput::make('attribution')->label('Who said it')->maxLength(120),
                MediaPicker::image('image_id', 'Background image (optional)'),
            ]);
    }

    private static function team(): Block
    {
        return Block::make('team')
            ->label('Team')
            ->icon(Heroicon::OutlinedUsers)
            ->schema([
                TextInput::make('heading')->default('Meet the people behind Think Ocean')->maxLength(150),
                Textarea::make('intro')->rows(2)->maxLength(300),
                Repeater::make('members')
                    ->helperText('The section stays hidden until at least one person is added.')
                    ->schema([
                        TextInput::make('name')->required()->maxLength(120),
                        TextInput::make('role')->maxLength(120),
                        MediaPicker::image('image_id', 'Photo'),
                    ])
                    ->columns(3)
                    ->itemLabel(fn (array $state): ?string => $state['name'] ?? null)
                    ->collapsible()
                    ->defaultItems(0)
                    ->maxItems(24)
                    ->addActionLabel('Add person'),
                Grid::make(2)->schema([
                    TextInput::make('link_label')->label('Button label (optional)')->maxLength(40),
                    ContentFormComponents::link('link_url', 'Button link'),
                ]),
            ]);
    }

    private static function image(): Block
    {
        return Block::make('image')
            ->label('Wide photograph')
            ->icon(Heroicon::OutlinedPhoto)
            ->schema([
                MediaPicker::image('image_id', 'Photograph')->required(),
            ]);
    }

    private static function enquiryForm(): Block
    {
        return Block::make('enquiry_form')
            ->label('Enquiry form (“Tell us how you’d like to help”)')
            ->icon(Heroicon::OutlinedHandRaised)
            ->schema([
                TextInput::make('heading')->default('Tell us how you’d like to help')->maxLength(150),
                Textarea::make('intro')->rows(2)->maxLength(300),
                Textarea::make('options')
                    ->label('“I’m interested in” choices, one per line')
                    ->rows(4)
                    ->default("Volunteering\nPartnering with Think Ocean\nSupporting our work\nSomething else"),
                MediaPicker::image('image_id', 'Background image (optional)'),
            ])
            ->maxItems(1);
    }

    private static function donationForm(): Block
    {
        return Block::make('donation_form')
            ->label('Donation form (Stripe)')
            ->icon(Heroicon::OutlinedHeart)
            ->schema([
                TextInput::make('heading')->default('Make a donation')->maxLength(150),
                Textarea::make('intro')->rows(2)->maxLength(400),
                TextInput::make('amounts')
                    ->label('Suggested amounts in £, separated by commas')
                    ->default('10, 25, 50, 100')
                    ->regex('/^\s*\d+(\s*,\s*\d+)*\s*$/')
                    ->maxLength(60),
                Toggle::make('allow_monthly')->label('Offer monthly giving')->default(true),
                TextInput::make('items_heading')->label('Side panel heading')->default('Your support can enable')->maxLength(100),
                Repeater::make('items')
                    ->label('Side panel list')
                    ->schema([
                        TextInput::make('title')->required()->maxLength(80),
                        Select::make('icon')->options(self::ICONS)->placeholder('No icon'),
                    ])
                    ->columns(2)
                    ->defaultItems(0)
                    ->maxItems(8),
            ])
            ->maxItems(1);
    }

    private static function latestNews(): Block
    {
        return Block::make('latest_news')
            ->label('Latest news (automatic)')
            ->icon(Heroicon::OutlinedRss)
            ->schema([
                TextInput::make('eyebrow')->label('Small label above heading (optional)')->maxLength(60),
                TextInput::make('heading')->default('News and insights')->maxLength(150),
                Textarea::make('intro')->rows(2)->maxLength(500),
                Select::make('limit')->options([3 => '3', 6 => '6'])->default(3)->selectablePlaceholder(false),
            ]);
    }
}
