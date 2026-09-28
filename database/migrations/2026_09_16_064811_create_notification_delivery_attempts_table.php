<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_delivery_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('notification_id')->constrained('notification_logs')->restrictOnDelete();
            $table->unsignedInteger('attempt_number');
            $table->dateTime('started_at');
            $table->dateTime('completed_at')->nullable();
            $table->enum('status', ['success', 'failed']);
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->string('error_code', 100)->nullable();
            $table->text('error_message')->nullable();
            $table->json('response_metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index('notification_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_delivery_attempts');
    }
};