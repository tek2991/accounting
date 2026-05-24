<?php

namespace Tek2991\Accounting\Filament\Resources\Contacts\Schemas;

use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Tek2991\Accounting\Enums\ContactType;

class ContactForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Basic Information')
                    ->columns(2)
                    ->components([
                        Forms\Components\Select::make('type')
                            ->label('Contact Type')
                            ->options(ContactType::class)
                            ->default(ContactType::Customer)
                            ->required(),
                            
                        Forms\Components\TextInput::make('name')
                            ->label('Name / Company')
                            ->required()
                            ->maxLength(255),
                            
                        Forms\Components\TextInput::make('email')
                            ->label('Email Address')
                            ->email()
                            ->maxLength(255),
                            
                        Forms\Components\TextInput::make('phone')
                            ->label('Phone Number')
                            ->maxLength(255),
                            
                        Forms\Components\TextInput::make('tax_id')
                            ->label('Tax ID / GSTIN')
                            ->maxLength(255),
                    ]),
                    
                Section::make('Addresses')
                    ->columns(2)
                    ->components([
                        Forms\Components\Textarea::make('billing_address')
                            ->label('Billing Address')
                            ->rows(3),
                            
                        Forms\Components\Textarea::make('shipping_address')
                            ->label('Shipping Address')
                            ->rows(3),
                    ]),
            ]);
    }
}
