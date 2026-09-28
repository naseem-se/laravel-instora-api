<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('notification_id')->constrained('notification_logs')->restrictOnDelete();
            $table->foreignId('provider_id')->constrained('whatsapp_providers')->restrictOnDelete();

            $table->string('recipient', 50);
            $table->string('message_type', 50);
            $table->string('template_name')->nullable();
            $table->string('provider_message_id')->nullable();

            $table->string('status', 50);
            $table->json('request_metadata')->nullable();
            $table->json('response_metadata')->nullable();

            $table->timestamps();

            $table->index(['company_id', 'notification_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_messages');
    }
};