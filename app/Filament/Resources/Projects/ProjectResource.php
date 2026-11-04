<?php

namespace App\Filament\Resources\Projects;

use App\Enums\ProjectStatus;
use App\Filament\RelationManagers\RevisionsRelationManager;
use App\Filament\Resources\Projects\Pages\CreateProject;
use App\Filament\Resources\Projects\Pages\EditProject;
use App\Filament\Resources\Projects\Pages\ListProjects;
use App\Filament\Resources\Projects\Pages\ViewProject;
use App\Filament\Support\ContentFormComponents;
use App\Filament\Support\MediaPicker;
use App\Filament\Support\PageBlocks;
use App\Filament\Support\WorkflowTable;
use App\Models\Category;
use App\Models\Project;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;

class ProjectResource extends Resource
{
    protected static ?string $model = Project::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedGlobeEuropeAfrica;

    protected static string|UnitEnum|null $navigationGroup = 'Content';

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'title';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            ContentFormComponents::workflowNotice(),
            Section::make('Project')
                ->schema([
                    ContentFormComponents::title(),
                    ContentFormComponents::slug('projects', url('/projects').'/'),
                    Textarea::make('summary')
                        ->rows(3)
                        ->maxLength(500)
                        ->required()
                        ->helperText('One or two sentences shown on project cards and in search results.'),
                    PageBlocks::richEditor('description', 'Full description'),
                ])
                ->columnSpan(2),
            Section::make('Details')
                ->schema([
                    Select::make('project_status')->label('Project status')->options(ProjectStatus::class),
                    Select::make('category_id')
                        ->label('Category')
                        ->options(fn () => Category::query()->ofType(Category::TYPE_PROJECT)->orderBy('name')->pluck('name', 'id'))
                        ->searchable(),
                    TextInput::make('location')
                        ->maxLength(150)
                        ->helperText('Only enter a location that has been confirmed.'),
                    Grid::make(2)->schema([
                        DatePicker::make('start_date')->native(false)->displayFormat('j M Y'),
                        DatePicker::make('end_date')->native(false)->displayFormat('j M Y')->afterOrEqual('start_date'),
                    ]),
                    Toggle::make('is_featured')
                        ->label('Feature on the homepage')
                        ->helperText('Featured projects appear on the homepage once published.'),
                ])
                ->columnSpan(1),
            Section::make('Images')
                ->schema([
                    MediaPicker::image('featured_image_id', 'Featured image'),
                    MediaPicker::gallery('gallery', 'Gallery')->helperText('Images are shown in the order selected.'),
                ])
                ->columnSpanFull(),
            ContentFormComponents::seo()->columnSpanFull(),
        ])->columns(3);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                WorkflowTable::titleColumn(),
                IconColumn::make('is_featured')->label('Featured')->boolean()->trueIcon('heroicon-o-star')->falseIcon('')->toggleable(),
                ...WorkflowTable::statusColumns(),
            ])
            ->defaultSort('updated_at', 'desc')
            ->filters([
                ...WorkflowTable::filters(),
                SelectFilter::make('category_id')->label('Category')->relationship('category', 'name', fn (Builder $query) => $query->where('type', Category::TYPE_PROJECT)),
            ])
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
            'index' => ListProjects::route('/'),
            'create' => CreateProject::route('/create'),
            'view' => ViewProject::route('/{record}'),
            'edit' => EditProject::route('/{record}/edit'),
        ];
    }
}
