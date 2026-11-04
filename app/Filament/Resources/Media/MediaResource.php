<?php

namespace App\Filament\Resources\Media;

use App\Filament\Resources\Media\Pages\CreateMedia;
use App\Filament\Resources\Media\Pages\EditMedia;
use App\Filament\Resources\Media\Pages\ListMedia;
use App\Models\Media;
use App\Services\MediaLibrary;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use UnitEnum;

class MediaResource extends Resource
{
    protected static ?string $model = Media::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPhoto;

    protected static string|UnitEnum|null $navigationGroup = 'Library';

    protected static ?string $navigationLabel = 'Media';

    protected static ?string $modelLabel = 'media file';

    protected static ?string $pluralModelLabel = 'media';

    protected static ?string $recordTitleAttribute = 'title';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('File')
                ->schema([
                    FileUpload::make('path')
                        ->label('File')
                        ->required()
                        // Uploads land on the private disk first; MediaLibrary moves
                        // public files after checking their real content type.
                        ->disk('local')
                        ->directory(fn () => 'media/'.now()->format('Y/m'))
                        ->visibility('private')
                        ->acceptedFileTypes(Media::allowedMimeTypes())
                        ->maxSize(Media::MAX_UPLOAD_KB)
                        ->storeFileNamesIn('original_name')
                        ->getUploadedFileNameForStorageUsing(
                            fn (TemporaryUploadedFile $file): string => Str::lower((string) Str::ulid()).'.'.($file->guessExtension() ?: 'bin')
                        )
                        ->helperText('Images: JPG, PNG, WebP or GIF. Documents: PDF, Word, Excel, PowerPoint, text or CSV. Maximum 10 MB. SVG files are not accepted for security reasons.')
                        ->visibleOn('create'),
                    Radio::make('visibility')
                        ->label('Who can access documents?')
                        ->options([
                            Media::VISIBILITY_PUBLIC => 'Public — can be linked from the website',
                            Media::VISIBILITY_PRIVATE => 'Private — staff only, never linked publicly',
                        ])
                        ->default(Media::VISIBILITY_PUBLIC)
                        ->helperText('Images are always public.')
                        ->visibleOn('create'),
                    Text::make(fn (?Media $record) => new HtmlString(static::previewHtml($record)))
                        ->visibleOn('edit'),
                ]),
            Section::make('Description')
                ->schema([
                    TextInput::make('title')->maxLength(200)->helperText('For finding the file in the library.'),
                    TextInput::make('alt_text')
                        ->label('Alternative text')
                        ->maxLength(250)
                        ->helperText('Describe the image for people who cannot see it, e.g. "Volunteers collecting litter on a sandy beach". Leave blank only for purely decorative images.'),
                    Textarea::make('caption')->rows(2)->maxLength(500),
                    TextInput::make('credit')->label('Photo credit / licence')->maxLength(200)->helperText('Record who owns the image and how it may be used.'),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('preview')
                    ->state(fn (Media $record) => $record->isImage() ? $record->urlForWidth(480) : null)
                    ->defaultImageUrl(null)
                    ->imageHeight(48)
                    ->extraImgAttributes(['alt' => '', 'loading' => 'lazy']),
                TextColumn::make('title')
                    ->state(fn (Media $record) => $record->displayName())
                    ->description(fn (Media $record) => $record->original_name)
                    ->searchable(['title', 'original_name', 'alt_text'])
                    ->wrap(),
                TextColumn::make('alt_text')
                    ->label('Alt text')
                    ->placeholder(fn (Media $record) => $record->isImage() ? 'Missing' : '—')
                    ->limit(40)
                    ->toggleable(),
                TextColumn::make('visibility')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => Str::headline($state))
                    ->icon(fn (string $state) => $state === Media::VISIBILITY_PRIVATE ? 'heroicon-o-lock-closed' : 'heroicon-o-globe-alt')
                    ->color(fn (string $state) => $state === Media::VISIBILITY_PRIVATE ? 'warning' : 'gray'),
                TextColumn::make('size')->state(fn (Media $record) => $record->humanSize())->toggleable(),
                TextColumn::make('uploader.name')->label('Uploaded by')->toggleable(),
                TextColumn::make('created_at')->label('Uploaded')->since()->dateTimeTooltip()->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('kind')
                    ->options(['image' => 'Images', 'document' => 'Documents'])
                    ->query(fn (Builder $query, array $data) => match ($data['value'] ?? null) {
                        'image' => $query->whereIn('mime_type', Media::IMAGE_MIME_TYPES),
                        'document' => $query->whereNotIn('mime_type', Media::IMAGE_MIME_TYPES),
                        default => $query,
                    }),
                SelectFilter::make('visibility')->options([
                    Media::VISIBILITY_PUBLIC => 'Public',
                    Media::VISIBILITY_PRIVATE => 'Private',
                ]),
            ])
            ->recordActions([
                Action::make('open')
                    ->label(fn (Media $record) => $record->isPublic() ? 'Open' : 'Download')
                    ->icon(Heroicon::OutlinedArrowDownTray)
                    ->color('gray')
                    ->url(fn (Media $record) => $record->url() ?? route('admin.media.download', $record))
                    ->openUrlInNewTab(),
                EditAction::make(),
                Action::make('deleteMedia')
                    ->label('Delete')
                    ->icon(Heroicon::OutlinedTrash)
                    ->color('danger')
                    ->visible(fn (Media $record) => Gate::allows('delete', $record))
                    ->requiresConfirmation()
                    ->modalDescription('The file is permanently deleted. Any page, project or article using it will no longer show it.')
                    ->action(function (Media $record, MediaLibrary $library): void {
                        $library->delete($record, auth()->user());
                        Notification::make()->success()->title('File deleted.')->send();
                    }),
            ]);
    }

    private static function previewHtml(?Media $media): string
    {
        if (! $media) {
            return '';
        }

        $details = e($media->original_name).' · '.e($media->mime_type).' · '.e($media->humanSize())
            .($media->width ? ' · '.$media->width.' × '.$media->height.' px' : '');

        if ($media->isImage() && $media->isPublic()) {
            return '<img src="'.e($media->urlForWidth(960)).'" alt="" style="max-height:18rem;border-radius:.5rem;margin-bottom:.5rem">'
                .'<p style="font-size:.875rem;opacity:.75">'.$details.'</p>';
        }

        return '<p style="font-size:.875rem">'.$details.'</p>';
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMedia::route('/'),
            'create' => CreateMedia::route('/create'),
            'edit' => EditMedia::route('/{record}/edit'),
        ];
    }
}
