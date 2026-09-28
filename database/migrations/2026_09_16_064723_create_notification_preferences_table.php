<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();

            $table->boolean('email_enabled')->default(true);
            $table->boolean('whatsapp_enabled')->default(true);
            $table->boolean('sms_enabled')->default(true);

            $table->boolean('due_reminders_enabled')->default(true);
            $table->boolean('overdue_reminders_enabled')->default(true);
            $table->boolean('payment_receipts_enabled')->default(true);

            $table->time('quiet_hours_start')->nullable();
            $table->time('quiet_hours_end')->nullable();

            $table->timestamps();

            // One preference row per customer.
            $table->unique('customer_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_preferences');
    }
};