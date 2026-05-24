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

        Schema::table("{$prefix}settings", function (Blueprint $table) {
            $table->unsignedBigInteger('invoice_next_number')->default(1)->after('invoice_prefix');
            $table->unsignedBigInteger('bill_next_number')->default(1)->after('bill_prefix');
        });
    }

    public function down(): void
    {
        $prefix = $this->prefix();
        Schema::table("{$prefix}settings", function (Blueprint $table) {
            $table->dropColumn(['invoice_next_number', 'bill_next_number']);
        });
    }
};
