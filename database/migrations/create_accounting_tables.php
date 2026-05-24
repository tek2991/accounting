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

        // ──────────────────────────────────────────────────────────
        // Account Subtypes
        // ──────────────────────────────────────────────────────────
        Schema::create("{$prefix}account_subtypes", function (Blueprint $table) use ($prefix) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('category', 20);  // AccountCategory enum
            $table->string('type', 30);      // AccountType enum
            $table->string('name');
            $table->string('description')->nullable();

            $table->unique(['company_id', 'type', 'name'], "{$prefix}subtypes_unique");
            $table->index('company_id', "{$prefix}subtypes_company_idx");
        });

        // ──────────────────────────────────────────────────────────
        // Chart of Accounts
        // ──────────────────────────────────────────────────────────
        Schema::create("{$prefix}accounts", function (Blueprint $table) use ($prefix) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subtype_id')
                ->nullable()
                ->constrained("{$prefix}account_subtypes")
                ->nullOnDelete();
            $table->foreignId('parent_id')
                ->nullable()
                ->constrained("{$prefix}accounts")
                ->nullOnDelete();
            $table->string('category', 20);   // AccountCategory enum
            $table->string('type', 30);       // AccountType enum
            $table->string('code', 10);
            $table->string('name');
            $table->string('currency_code', 3)->default('USD');
            $table->text('description')->nullable();
            $table->boolean('archived')->default(false);
            $table->boolean('default')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['company_id', 'code'], "{$prefix}accounts_code_unique");
            $table->index('company_id', "{$prefix}accounts_company_idx");
            $table->index('category', "{$prefix}accounts_category_idx");
            $table->index('type', "{$prefix}accounts_type_idx");
            $table->index('archived', "{$prefix}accounts_archived_idx");
        });

        // ──────────────────────────────────────────────────────────
        // Transactions
        // ──────────────────────────────────────────────────────────
        Schema::create("{$prefix}transactions", function (Blueprint $table) use ($prefix) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('account_id')
                ->nullable()
                ->constrained("{$prefix}accounts")
                ->nullOnDelete();
            $table->string('type', 20);       // TransactionType enum
            $table->string('description');
            $table->text('notes')->nullable();
            $table->string('reference')->nullable();
            $table->bigInteger('amount')->default(0); // stored in minor units (cents)
            $table->boolean('pending')->default(false);
            $table->boolean('reviewed')->default(false);
            $table->boolean('allow_reversal')->default(true);
            $table->date('posted_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('company_id', "{$prefix}txn_company_idx");
            $table->index('account_id', "{$prefix}txn_account_idx");
            $table->index('type', "{$prefix}txn_type_idx");
            $table->index('posted_at', "{$prefix}txn_posted_idx");
            $table->index(['company_id', 'posted_at'], "{$prefix}txn_company_posted_idx");
        });

        // ──────────────────────────────────────────────────────────
        // Journal Entries (double-entry lines)
        // ──────────────────────────────────────────────────────────
        Schema::create("{$prefix}journal_entries", function (Blueprint $table) use ($prefix) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('transaction_id')
                ->constrained("{$prefix}transactions")
                ->cascadeOnDelete();
            $table->foreignId('account_id')
                ->constrained("{$prefix}accounts")
                ->restrictOnDelete();
            $table->string('type', 10);       // JournalEntryType enum: 'debit' or 'credit'
            $table->bigInteger('amount');      // stored in minor units (cents)
            $table->string('description')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('company_id', "{$prefix}je_company_idx");
            $table->index('transaction_id', "{$prefix}je_txn_idx");
            $table->index('account_id', "{$prefix}je_account_idx");
            $table->index('type', "{$prefix}je_type_idx");
            $table->index(['account_id', 'type'], "{$prefix}je_account_type_idx");
        });
    }

    public function down(): void
    {
        $prefix = $this->prefix();

        Schema::dropIfExists("{$prefix}journal_entries");
        Schema::dropIfExists("{$prefix}transactions");
        Schema::dropIfExists("{$prefix}accounts");
        Schema::dropIfExists("{$prefix}account_subtypes");
    }
};
