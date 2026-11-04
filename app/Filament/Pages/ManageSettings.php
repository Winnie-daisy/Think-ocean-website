<?php

namespace App\Filament\Pages;

use App\Enums\Permission;
use App\Filament\Support\MediaPicker;
use App\Rules\SafeUrl;
use App\Services\ActivityLogger;
use App\Support\SiteSettings;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/**
 * @property-read Schema $form
 */
class ManageSettings extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static string|UnitEnum|null $navigationGroup = 'Site';

    protected static ?string $navigationLabel = 'Site settings';

    protected static ?string $title = 'Site settings';

    protected static ?string $slug = 'settings';

    /** @var array<string, mixed> */
    public ?array $data = [];

    public const SOCIAL_PLATFORMS = [
        'instagram' => 'Instagram',
        'facebook' => 'Facebook',
        'linkedin' => 'LinkedIn',
        'x' => 'X (Twitter)',
        'bluesky' => 'Bluesky',
        'youtube' => 'YouTube',
        'tiktok' => 'TikTok',
    ];

    public static function canAccess(): bool
    {
        return auth()->user()?->hasPermission(Permission::ManageSettings) ?? false;
    }

    public function mount(SiteSettings $settings): void
    {
        $this->form->fill($settings->all());
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make('Organisation')
                    ->schema([
                        TextInput::make('organisation_name')->required()->maxLength(120),
                        TextInput::make('tagline')->maxLength(160),
                        TextInput::make('company_registration')
                            ->label('Company / CIC registration details')
                            ->maxLength(200)
                            ->helperText('Shown in the footer, e.g. "Community Interest Company registered in England and Wales, no. …". Only enter verified details.'),
                        Textarea::make('footer_about')->label('Footer description')->rows(3)->maxLength(400),
                        MediaPicker::image('logo_media_id', 'Logo')->helperText('Optional. Without a logo the site shows the organisation name as text.'),
                        MediaPicker::image('favicon_media_id', 'Favicon')->helperText('A square image, at least 512 × 512 pixels.'),
                    ])
                    ->columns(2),
                Section::make('Contact details')
                    ->description('Only enter confirmed contact details. Anything left blank is not shown on the website.')
                    ->schema([
                        TextInput::make('contact_email')->label('Public email address')->email()->maxLength(190),
                        TextInput::make('contact_phone')->label('Public phone number')->tel()->maxLength(40),
                        Textarea::make('postal_address')->rows(3)->maxLength(400)->columnSpanFull(),
                    ])
                    ->columns(2),
                Section::make('Contact form')
                    ->schema([
                        Toggle::make('contact_form_enabled')->label('Accept messages through the contact form'),
                        TextInput::make('contact_notification_email')
                            ->label('Send a notification of new messages to')
                            ->email()
                            ->maxLength(190)
                            ->helperText('Optional. Messages are always stored in the CMS. Requires outgoing email to be configured on the server.'),
                    ])
                    ->columns(2),
                Section::make('Social media')
                    ->description('Only add verified accounts that belong to the organisation.')
                    ->schema([
                        Repeater::make('social_links')
                            ->hiddenLabel()
                            ->schema([
                                Select::make('platform')->options(self::SOCIAL_PLATFORMS)->required(),
                                TextInput::make('url')->label('Profile URL')->url()->required()->maxLength(300)->rule(new SafeUrl)->startsWith(['https://']),
                            ])
                            ->columns(2)
                            ->maxItems(10)
                            ->defaultItems(0)
                            ->addActionLabel('Add social link'),
                    ]),
                Section::make('Live TV')
                    ->description('The /live page. While it is switched on, a red “on air” dot appears next to Live TV in the menu.')
                    ->schema([
                        Toggle::make('live_enabled')->label('On air')->columnSpanFull(),
                        TextInput::make('live_title')->label('Channel name')->required()->maxLength(120),
                        TextInput::make('live_stream_url')
                            ->label('Stream link')
                            ->url()
                            ->startsWith(['https://'])
                            ->rule(new SafeUrl)
                            ->maxLength(500)
                            ->requiredIf('live_enabled', true)
                            ->validationMessages(['required_if' => 'Add a stream link before switching Live TV on.'])
                            ->helperText('A YouTube or Vimeo link, an HLS stream (.m3u8) or a video file (.mp4).'),
                        Textarea::make('live_description')->label('About the channel')->rows(3)->maxLength(1000),
                        Textarea::make('live_schedule')->label('Broadcast schedule')->rows(3)->maxLength(2000),
                        TextInput::make('watch_browse_url')
                            ->label('“Browse Videos” link')
                            ->url()
                            ->startsWith(['https://'])
                            ->rule(new SafeUrl)
                            ->maxLength(300)
                            ->helperText('For example your YouTube channel. Leave blank to hide the button.')
                            ->columnSpanFull(),
                        Repeater::make('watch_videos')
                            ->label('Videos under “A closer look at the ocean”')
                            ->schema([
                                TextInput::make('title')->required()->maxLength(120),
                                TextInput::make('url')->label('Video link')->url()->required()->startsWith(['https://'])->rule(new SafeUrl)->maxLength(300),
                                MediaPicker::image('image_id', 'Thumbnail'),
                            ])
                            ->columns(3)
                            ->defaultItems(0)
                            ->maxItems(9)
                            ->addActionLabel('Add video')
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
                Section::make('Search engines and sharing defaults')
                    ->schema([
                        TextInput::make('default_meta_title')->label('Default page title')->maxLength(70),
                        Textarea::make('default_meta_description')->label('Default meta description')->rows(3)->maxLength(320),
                        MediaPicker::image('default_og_image_id', 'Default social sharing image')->helperText('Used when a page has no image of its own. Ideally 1200 × 630 pixels.'),
                    ]),
            ]);
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Form::make([EmbeddedSchema::make('form')])
                ->id('form')
                ->livewireSubmitHandler('save')
                ->footer([
                    Actions::make([
                        Action::make('save')->label('Save settings')->submit('save'),
                    ]),
                ]),
        ]);
    }

    public function save(SiteSettings $settings, ActivityLogger $activity): void
    {
        abort_unless(static::canAccess(), 403);

        $data = $this->form->getState();
        $before = $settings->all();

        $settings->set($data);

        $changed = array_keys(array_filter(
            array_intersect_key($data, SiteSettings::DEFAULTS),
            fn ($value, $key) => ($before[$key] ?? null) != $value,
            ARRAY_FILTER_USE_BOTH,
        ));

        $activity->log('settings.updated', 'Updated site settings', null, ['changed' => $changed]);

        Notification::make()->success()->title('Settings saved.')->send();
    }
}
