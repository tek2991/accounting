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
            $table->string('voucherable_type', 100)->nullable()->after('company_id');
            $table->unsignedBigInteger('voucherable_id')->nullable()->after('voucherable_type');

            $table->index(['voucherable_type', 'voucherable_id'], "{$prefix}txn_voucherable_idx");
        });
    }

    public function down(): void
    {
        $prefix = $this->prefix();

        Schema::table("{$prefix}transactions", function (Blueprint $table) use ($prefix) {
            $table->dropIndex("{$prefix}txn_voucherable_idx");
            $table->dropColumn(['voucherable_type', 'voucherable_id']);
        });
    }
};
