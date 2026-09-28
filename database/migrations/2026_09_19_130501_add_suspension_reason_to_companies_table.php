<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            // 'non_payment' | 'manual' | null - distinguishes an
            // auto-suspension the billing system can safely reverse from a
            // manual one it must never touch. See SubscriptionService::recordPayment().
            $table->string('suspension_reason', 50)->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn('suspension_reason');
        });
    }
};