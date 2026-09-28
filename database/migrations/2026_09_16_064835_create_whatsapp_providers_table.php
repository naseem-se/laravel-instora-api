<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_providers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->nullable()->constrained('companies')->restrictOnDelete();
            $table->string('name');
            $table->string('provider_type', 100);
            $table->string('base_url', 500);
            $table->string('api_version', 100)->nullable();
            $table->json('credentials')->nullable();
            $table->string('phone_number_id')->nullable();
            $table->string('business_account_id')->nullable();
            $table->string('sender_number', 50)->nullable();
            $table->string('webhook_secret', 500)->nullable();

            $table->enum('status', ['active', 'inactive', 'error'])->default('inactive');
            $table->boolean('is_default')->default(false);

            $table->dateTime('last_verified_at')->nullable();
            $table->text('last_error')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['company_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_providers');
    }
};