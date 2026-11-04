<?php

namespace App\Filament\Resources\Donations;

use App\Filament\Resources\Donations\Pages\ListDonations;
use App\Models\Donation;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

class DonationResource extends Resource
{
    protected static ?string $model = Donation::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedHeart;

    protected static string|UnitEnum|null $navigationGroup = 'Enquiries';

    protected static ?string $navigationLabel = 'Donations';

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => Donation::STATUSES[$state] ?? $state)
                    ->color(fn (string $state) => match ($state) {
                        'paid' => 'success',
                        'failed' => 'danger',
                        'pending' => 'warning',
                        default => 'gray',
                    }),
                TextColumn::make('amount')->formatStateUsing(fn (Donation $record) => $record->formattedAmount())->sortable(),
                TextColumn::make('frequency')->formatStateUsing(fn (string $state) => Donation::FREQUENCIES[$state] ?? $state),
                TextColumn::make('name')->placeholder('Anonymous')->searchable(),
                TextColumn::make('email')->searchable()->copyable()->toggleable(),
                TextColumn::make('created_at')->label('Started')->since()->dateTimeTooltip()->sortable(),
                TextColumn::make('paid_at')->label('Paid')->dateTime('j M Y, H:i')->placeholder('—')->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')->options(Donation::STATUSES),
                SelectFilter::make('frequency')->options(Donation::FREQUENCIES),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDonations::route('/'),
        ];
    }
}
