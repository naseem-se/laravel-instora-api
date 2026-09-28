<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('installment_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('invoice_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('payment_id')->nullable()->constrained()->nullOnDelete();

            $table->string('type', 100);
            $table->enum('channel', ['email', 'whatsapp', 'sms']);

            $table->string('recipient');

            $table->foreignId('template_id')->nullable()
                ->constrained('notification_templates')->nullOnDelete();
            // No FK: provider table depends on channel (email/SMS/WhatsApp), resolved in application code.
            $table->unsignedBigInteger('provider_id')->nullable();

            $table->enum('status', [
                'pending', 'queued', 'sending', 'accepted', 'sent',
                'delivered', 'read', 'failed', 'skipped', 'cancelled',
            ])->default('pending');

            $table->string('skip_reason')->nullable();
            $table->string('provider_message_id')->nullable();
            $table->string('provider_event_id')->nullable();
            $table->json('provider_response')->nullable();
            $table->string('error_code', 100)->nullable();
            $table->text('error_message')->nullable();

            $table->dateTime('scheduled_for')->nullable();
            $table->dateTime('sent_at')->nullable();
            $table->dateTime('delivered_at')->nullable();
            $table->dateTime('read_at')->nullable();

            $table->string('idempotency_key')->unique();

            $table->timestamps();

            $table->index(['company_id', 'status']);
            $table->index(['company_id', 'customer_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_logs');
    }
};