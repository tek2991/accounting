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

        Schema::table("{$prefix}transactions", function (Blueprint $table) use ($prefix) {
            $table->foreignId('bank_account_id')
                ->nullable()
                ->after('account_id')
                ->constrained("{$prefix}bank_accounts")
                ->nullOnDelete();

            $table->index('bank_account_id', "{$prefix}txn_bank_account_idx");
        });
    }

    public function down(): void
    {
        $prefix = $this->prefix();

        Schema::table("{$prefix}transactions", function (Blueprint $table) use ($prefix) {
            $table->dropForeign("{$prefix}txn_bank_account_id_foreign");
            $table->dropIndex("{$prefix}txn_bank_account_idx");
            $table->dropColumn('bank_account_id');
        });
    }
};
