<?php

namespace App\Filament\Resources\NavigationItems;

use App\Filament\Resources\NavigationItems\Pages\ManageNavigationItems;
use App\Filament\Support\ContentFormComponents;
use App\Models\NavigationItem;
use App\Models\Page;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

class NavigationItemResource extends Resource
{
    protected static ?string $model = NavigationItem::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBars3;

    protected static string|UnitEnum|null $navigationGroup = 'Site';

    protected static ?string $navigationLabel = 'Navigation';

    protected static ?string $modelLabel = 'menu link';

    protected static ?string $recordTitleAttribute = 'label';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('location')->label('Menu')->options(NavigationItem::LOCATIONS)->required()->default('header'),
            TextInput::make('label')->required()->maxLength(60),
            Radio::make('link_type')
                ->label('Link to')
                ->options(['page' => 'A page on this site', 'url' => 'A web address'])
                ->default('page')
                ->inline()
                ->live(),
            Select::make('page_id')
                ->label('Page')
                ->options(fn () => Page::query()->orderBy('title')->pluck('title', 'id'))
                ->searchable()
                ->required(fn (Get $get) => $get('link_type') === 'page')
                ->visible(fn (Get $get) => $get('link_type') === 'page')
                ->helperText('The link is hidden automatically while the page is unpublished.'),
            ContentFormComponents::link('url', 'Web address')
                ->required(fn (Get $get) => $get('link_type') === 'url')
                ->visible(fn (Get $get) => $get('link_type') === 'url')
                ->helperText('Use a path such as /projects for pages on this site.'),
            TextInput::make('sort_order')->numeric()->minValue(0)->default(0),
            Toggle::make('open_in_new_tab')->label('Open in a new tab'),
            Toggle::make('is_active')->label('Show in menu')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('label')->searchable(),
                TextColumn::make('location')->label('Menu')->formatStateUsing(fn (string $state) => NavigationItem::LOCATIONS[$state] ?? $state)->badge()->color('gray'),
                TextColumn::make('target')
                    ->label('Links to')
                    ->state(fn (NavigationItem $record) => $record->link_type === 'page' ? 'Page: '.($record->page?->title ?? 'missing') : $record->url),
                ToggleColumn::make('is_active')->label('Shown'),
            ])
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->defaultGroup('location')
            ->filters([SelectFilter::make('location')->label('Menu')->options(NavigationItem::LOCATIONS)])
            ->recordActions([EditAction::make(), DeleteAction::make()])
            ->paginated(false);
    }

    public static function getPages(): array
    {
        return ['index' => ManageNavigationItems::route('/')];
    }
}
