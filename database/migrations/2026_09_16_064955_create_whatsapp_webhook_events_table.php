<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_webhook_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('provider_id')->constrained('whatsapp_providers')->restrictOnDelete();
            $table->string('external_event_id');
            $table->string('event_type', 100);
            $table->json('payload');
            $table->dateTime('processed_at')->nullable();
            $table->enum('status', ['received', 'processed', 'failed', 'ignored'])->default('received');
            $table->timestamps();

            $table->unique(['provider_id', 'external_event_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_webhook_events');
    }
};