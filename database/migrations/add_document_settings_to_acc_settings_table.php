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

        Schema::table("{$prefix}settings", function (Blueprint $table) use ($prefix) {
            $table->string('company_name')->nullable();
            $table->string('company_email')->nullable();
            $table->text('company_address')->nullable();
            $table->string('company_phone')->nullable();
            $table->string('company_tax_id')->nullable();

            $table->string('invoice_prefix')->default('INV-');
            $table->string('bill_prefix')->default('BILL-');
            $table->string('payment_prefix')->default('PAY-');
            $table->string('journal_prefix')->default('JRNL-');
        });
    }

    public function down(): void
    {
        $prefix = $this->prefix();

        Schema::table("{$prefix}settings", function (Blueprint $table) use ($prefix) {
            $table->dropColumn([
                'company_name',
                'company_email',
                'company_address',
                'company_phone',
                'company_tax_id',
                'invoice_prefix',
                'bill_prefix',
                'payment_prefix',
                'journal_prefix',
            ]);
        });
    }
};
