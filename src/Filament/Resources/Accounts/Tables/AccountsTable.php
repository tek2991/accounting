<?php

namespace Tek2991\Accounting\Filament\Resources\Accounts\Tables;

use Filament\Tables;
use Filament\Tables\Table;
use Filament\Actions;
use Tek2991\Accounting\Enums\AccountCategory;
use Tek2991\Accounting\Enums\AccountType;
use Tek2991\Accounting\Models\Account;

class AccountsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('code')
                    ->label('Code')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->copyable(),

                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->wrap(),

                Tables\Columns\TextColumn::make('category')
                    ->badge()
                    ->sortable(),

                Tables\Columns\TextColumn::make('type')
                    ->label('Type')
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('subtype.name')
                    ->label('Subtype')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('currency_code')
                    ->label('Currency')
                    ->badge()
                    ->color('gray')
                    ->toggleable(),

                Tables\Columns\IconColumn::make('archived')
                    ->boolean()
                    ->trueIcon('heroicon-o-archive-box')
                    ->falseIcon('heroicon-o-check-circle')
                    ->trueColor('danger')
                    ->falseColor('success')
                    ->label('Archived')
                    ->toggleable(),

                Tables\Columns\IconColumn::make('default')
                    ->boolean()
                    ->label('Default')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('code')
            ->filters([
                Tables\Filters\SelectFilter::make('category')
                    ->options(AccountCategory::class)
                    ->multiple(),

                Tables\Filters\SelectFilter::make('type')
                    ->options(AccountType::class)
                    ->multiple(),

                Tables\Filters\TernaryFilter::make('archived')
                    ->label('Status')
                    ->trueLabel('Archived')
                    ->falseLabel('Active')
                    ->placeholder('All'),
            ])
            ->recordActions([
                Actions\EditAction::make(),
                Actions\Action::make('archive')
                    ->label(fn (Account $record) => $record->archived ? 'Restore' : 'Archive')
                    ->icon(fn (Account $record) => $record->archived ? 'heroicon-o-arrow-uturn-left' : 'heroicon-o-archive-box')
                    ->color(fn (Account $record) => $record->archived ? 'success' : 'warning')
                    ->requiresConfirmation()
                    ->action(fn (Account $record) => $record->update(['archived' => ! $record->archived])),
            ])
            ->groupedBulkActions([
                Actions\DeleteBulkAction::make(), // optional
                Actions\BulkAction::make('archive')
                    ->label('Archive Selected')
                    ->icon('heroicon-o-archive-box')
                    ->requiresConfirmation()
                    ->action(fn ($records) => $records->each->update(['archived' => true])),
            ])
            ->groups([
                Tables\Grouping\Group::make('category')
                    ->label('Category')
                    ->collapsible(),
            ]);
    }
}
