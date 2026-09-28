<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('installment_plans', function (Blueprint $table) {
            // Database.md has no field to express the interval for
            // installment_frequency = 'custom' - without one, custom
            // frequency has no way to compute due dates.
            $table->unsignedInteger('custom_interval_days')->nullable()->after('installment_frequency');
        });
    }

    public function down(): void
    {
        Schema::table('installment_plans', function (Blueprint $table) {
            $table->dropColumn('custom_interval_days');
        });
    }
};