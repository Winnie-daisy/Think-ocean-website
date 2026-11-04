<?php

namespace App\Filament\Resources\Posts;

use App\Enums\Permission;
use App\Filament\RelationManagers\RevisionsRelationManager;
use App\Filament\Resources\Posts\Pages\CreatePost;
use App\Filament\Resources\Posts\Pages\EditPost;
use App\Filament\Resources\Posts\Pages\ListPosts;
use App\Filament\Resources\Posts\Pages\ViewPost;
use App\Filament\Support\ContentFormComponents;
use App\Filament\Support\MediaPicker;
use App\Filament\Support\PageBlocks;
use App\Filament\Support\WorkflowTable;
use App\Models\Category;
use App\Models\Post;
use App\Models\Tag;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Str;
use UnitEnum;

class PostResource extends Resource
{
    protected static ?string $model = Post::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedNewspaper;

    protected static string|UnitEnum|null $navigationGroup = 'Content';

    protected static ?int $navigationSort = 3;

    protected static ?string $navigationLabel = 'News and insights';

    protected static ?string $modelLabel = 'article';

    protected static ?string $recordTitleAttribute = 'title';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            ContentFormComponents::workflowNotice(),
            Section::make('Article')
                ->schema([
                    ContentFormComponents::title(),
                    ContentFormComponents::slug('posts', url('/news').'/'),
                    Textarea::make('excerpt')
                        ->rows(3)
                        ->maxLength(500)
                        ->required()
                        ->helperText('A short summary for news listings and search results.'),
                    PageBlocks::richEditor('body', 'Article')->required(),
                ])
                ->columnSpan(2),
            Section::make('Publishing')
                ->schema([
                    DateTimePicker::make('published_at')
                        ->label('Publication date')
                        ->native(false)
                        ->seconds(false)
                        ->displayFormat('j M Y, H:i')
                        ->helperText('Leave blank to use the moment it is published. A future date schedules it.'),
                    Select::make('category_ids')
                        ->label('Categories')
                        ->multiple()
                        ->options(fn () => Category::query()->ofType(Category::TYPE_POST)->orderBy('name')->pluck('name', 'id'))
                        ->searchable(),
                    Select::make('tag_ids')
                        ->label('Tags')
                        ->multiple()
                        ->options(fn () => Tag::query()->orderBy('name')->pluck('name', 'id'))
                        ->searchable()
                        ->createOptionForm([
                            TextInput::make('name')->required()->maxLength(60)->unique('tags', 'name'),
                        ])
                        ->createOptionUsing(function (array $data): int {
                            abort_unless(auth()->user()->hasPermission(Permission::ManageTaxonomy), 403);

                            return Tag::query()->firstOrCreate(
                                ['slug' => Str::slug($data['name'])],
                                ['name' => $data['name']],
                            )->getKey();
                        })
                        ->createOptionAction(fn ($action) => $action->visible(fn () => auth()->user()->hasPermission(Permission::ManageTaxonomy))),
                    MediaPicker::image('featured_image_id', 'Featured image'),
                ])
                ->columnSpan(1),
            ContentFormComponents::seo()->columnSpanFull(),
        ])->columns(3);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                WorkflowTable::titleColumn(),
                TextColumn::make('published_at')->label('Publication date')->date('j M Y')->sortable()->placeholder('—'),
                ...WorkflowTable::statusColumns(),
            ])
            ->defaultSort('updated_at', 'desc')
            ->filters(WorkflowTable::filters())
            ->recordActions([ViewAction::make(), EditAction::make(), WorkflowTable::restoreAction()]);
    }

    public static function getRelations(): array
    {
        return [RevisionsRelationManager::class];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()->withoutGlobalScopes([SoftDeletingScope::class]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPosts::route('/'),
            'create' => CreatePost::route('/create'),
            'view' => ViewPost::route('/{record}'),
            'edit' => EditPost::route('/{record}/edit'),
        ];
    }
}
