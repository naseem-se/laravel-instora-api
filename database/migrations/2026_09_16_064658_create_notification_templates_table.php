<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('type', 100);
            $table->enum('channel', ['email', 'whatsapp', 'sms']);
            $table->unsignedInteger('version')->default(1);
            $table->string('subject', 500)->nullable();
            $table->text('body');
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['company_id', 'type', 'channel']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_templates');
    }
};