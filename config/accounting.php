<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Company Model
    |--------------------------------------------------------------------------
    |
    | The fully qualified class name of the Company (tenant) model used by
    | the host application. The plugin uses this to resolve the current
    | company context for all accounting data.
    |
    */

    'company_model' => env('ACCOUNTING_COMPANY_MODEL', 'App\\Models\\Company'),

    /*
    |--------------------------------------------------------------------------
    | Table Prefix
    |--------------------------------------------------------------------------
    |
    | Prefix applied to all database tables created by this plugin to avoid
    | collisions with the host application's tables.
    |
    */

    'table_prefix' => env('ACCOUNTING_TABLE_PREFIX', 'acc_'),

    /*
    |--------------------------------------------------------------------------
    | Company Foreign Key
    |--------------------------------------------------------------------------
    |
    | The foreign key column name used to associate accounting records with
    | a company. Must match the primary key column of the company model.
    |
    */

    'company_foreign_key' => 'company_id',

    /*
    |--------------------------------------------------------------------------
    | User Model
    |--------------------------------------------------------------------------
    |
    | The user model class used for audit trails (created_by / updated_by).
    |
    */

    'user_model' => env('ACCOUNTING_USER_MODEL', 'App\\Models\\User'),

    /*
    |--------------------------------------------------------------------------
    | Default Currency
    |--------------------------------------------------------------------------
    |
    | The default ISO 4217 currency code used when creating new companies
    | or when no currency is explicitly specified.
    |
    */

    'default_currency' => env('ACCOUNTING_DEFAULT_CURRENCY', 'USD'),

    /*
    |--------------------------------------------------------------------------
    | Fiscal Year Start
    |--------------------------------------------------------------------------
    |
    | Default fiscal year start month (1 = January, 4 = April, etc.).
    |
    */

    'fiscal_year_start_month' => env('ACCOUNTING_FISCAL_YEAR_START', 1),

    /*
    |--------------------------------------------------------------------------
    | Account Code Settings
    |--------------------------------------------------------------------------
    */

    'account_code' => [
        'digits' => 4,
        'ranges' => [
            'asset'     => [1000, 1999],
            'liability' => [2000, 2999],
            'equity'    => [3000, 3999],
            'revenue'   => [4000, 4999],
            'expense'   => [5000, 5999],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | PDF Export
    |--------------------------------------------------------------------------
    |
    | Configuration for PDF generation via spatie/laravel-pdf.
    |
    */

    'pdf' => [
        'paper_size' => 'A4',
        'orientation' => 'portrait',
    ],

];
