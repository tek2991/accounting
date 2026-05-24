<?php

namespace Tek2991\Accounting\Filament\Resources\Settings\FiscalPeriods;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\DatePicker;
use Filament\Schemas\Schema;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Tek2991\Accounting\Models\FiscalPeriod;
use Tek2991\Accounting\Filament\Resources\Settings\FiscalPeriods\Pages;
use Tek2991\Accounting\Services\FiscalPeriodService;

class FiscalPeriodResource extends Resource
{
    protected static ?string $model = FiscalPeriod::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-calendar-days';
    protected static \UnitEnum|string|null $navigationGroup = 'Settings';
    protected static ?int $navigationSort = 100;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->maxLength(100),
                DatePicker::make('start_date')
                    ->required(),
                DatePicker::make('end_date')
                    ->required()
                    ->afterOrEqual('start_date'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('start_date')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('end_date')
                    ->date()
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_locked')
                    ->label('Locked')
                    ->boolean()
                    ->trueIcon('heroicon-o-lock-closed')
                    ->falseIcon('heroicon-o-lock-open')
                    ->trueColor('danger')
                    ->falseColor('success'),
                Tables\Columns\TextColumn::make('lockedBy.name')
                    ->label('Locked By')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('locked_at')
                    ->dateTime()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->actions([
                \Filament\Actions\EditAction::make()
                    ->visible(fn (FiscalPeriod $record) => !$record->is_locked),
                \Filament\Actions\Action::make('lock')
                    ->label('Lock')
                    ->icon('heroicon-o-lock-closed')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalDescription('This will prevent any further postings to this period. This cannot be undone without admin access.')
                    ->visible(fn (FiscalPeriod $record) => !$record->is_locked)
                    ->action(function (FiscalPeriod $record) {
                        app(FiscalPeriodService::class)->lock($record, auth()->user());
                        \Filament\Notifications\Notification::make()->title('Period Locked')->success()->send();
                    }),
                \Filament\Actions\Action::make('unlock')
                    ->label('Unlock')
                    ->icon('heroicon-o-lock-open')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalDescription('WARNING: Unlocking this period will allow new postings to an already closed period.')
                    ->visible(fn (FiscalPeriod $record) => $record->is_locked)
                    ->action(function (FiscalPeriod $record) {
                        app(FiscalPeriodService::class)->unlock($record, auth()->user());
                        \Filament\Notifications\Notification::make()->title('Period Unlocked')->success()->send();
                    }),
            ])
            ->bulkActions([
                \Filament\Actions\BulkActionGroup::make([
                    \Filament\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageFiscalPeriods::route('/'),
        ];
    }
}
