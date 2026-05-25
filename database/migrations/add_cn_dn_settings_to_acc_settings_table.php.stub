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
            $table->string('credit_note_prefix', 10)->default('CN-');
            $table->unsignedInteger('credit_note_next_number')->default(1);
            $table->string('debit_note_prefix', 10)->default('DN-');
            $table->unsignedInteger('debit_note_next_number')->default(1);
        });
    }

    public function down(): void
    {
        $prefix = $this->prefix();
        Schema::table("{$prefix}settings", function (Blueprint $table) {
            $table->dropColumn([
                'credit_note_prefix',
                'credit_note_next_number',
                'debit_note_prefix',
                'debit_note_next_number',
            ]);
        });
    }
};
