<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected function prefix(): string
    {
        return config('accounting.table_prefix', 'acc_');
    }

    public function up(): void
    {
        $prefix = $this->prefix();

        // ──────────────────────────────────────────────────────────────
        // Bank Accounts (a subset of Asset/Liability chart accounts
        // that represent actual cash/card/investment accounts)
        // ──────────────────────────────────────────────────────────────
        Schema::create("{$prefix}bank_accounts", function (Blueprint $table) use ($prefix) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('account_id')
                ->unique()  // one-to-one: each chart account can only be one bank account
                ->constrained("{$prefix}accounts")
                ->cascadeOnDelete();
            $table->string('type', 20);         // BankAccountType enum
            $table->string('number', 30)->nullable();  // masked account number
            $table->boolean('enabled')->default(false); // default/primary account flag
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('company_id', "{$prefix}bank_accounts_company_idx");
            $table->index('type', "{$prefix}bank_accounts_type_idx");
            $table->index('enabled', "{$prefix}bank_accounts_enabled_idx");
        });
    }

    public function down(): void
    {
        $prefix = $this->prefix();

        Schema::dropIfExists("{$prefix}bank_accounts");
    }
};
