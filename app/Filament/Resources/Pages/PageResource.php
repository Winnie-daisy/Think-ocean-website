<?php

namespace App\Filament\Resources\Pages;

use App\Filament\RelationManagers\RevisionsRelationManager;
use App\Filament\Resources\Pages\Pages\CreatePage;
use App\Filament\Resources\Pages\Pages\EditPage;
use App\Filament\Resources\Pages\Pages\ListPages;
use App\Filament\Resources\Pages\Pages\ViewPage;
use App\Filament\Support\ContentFormComponents;
use App\Filament\Support\PageBlocks;
use App\Filament\Support\WorkflowTable;
use App\Models\Page;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;

class PageResource extends Resource
{
    protected static ?string $model = Page::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static string|UnitEnum|null $navigationGroup = 'Content';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'title';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            ContentFormComponents::workflowNotice(),
            Section::make('Page')
                ->schema([
                    ContentFormComponents::title(),
                    ContentFormComponents::slug('pages', url('/').'/')
                        ->disabled(fn (?Page $record) => (bool) $record?->is_system)
                        ->notIn(Page::RESERVED_SLUGS)
                        ->helperText(fn (?Page $record) => $record?->is_system
                            ? 'This is a core page with a fixed address.'
                            : 'Part of the web address. Changing it on live content breaks existing links.'),
                    Textarea::make('intro')
                        ->label('Introduction')
                        ->rows(3)
                        ->maxLength(600)
                        ->helperText('Short lead paragraph shown at the top of the page.'),
                ])
                ->columns(1)
                ->columnSpanFull(),
            Section::make('Content')
                ->description('Build the page from sections. Drag to reorder.')
                ->schema([PageBlocks::builder()])
                ->columnSpanFull(),
            ContentFormComponents::seo()->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                WorkflowTable::titleColumn(),
                IconColumn::make('is_system')->label('Core page')->boolean()->trueIcon('heroicon-o-lock-closed')->falseIcon('')->toggleable(),
                ...WorkflowTable::statusColumns(),
            ])
            ->defaultSort('title')
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
            'index' => ListPages::route('/'),
            'create' => CreatePage::route('/create'),
            'view' => ViewPage::route('/{record}'),
            'edit' => EditPage::route('/{record}/edit'),
        ];
    }
}
