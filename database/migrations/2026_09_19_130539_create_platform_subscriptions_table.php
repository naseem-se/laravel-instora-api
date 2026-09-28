<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->unique()->constrained()->cascadeOnDelete();
            $table->decimal('monthly_fee', 15, 2);
            $table->string('currency', 10);
            $table->unsignedTinyInteger('billing_anchor_day'); // 1-28 only, enforced at validation
            $table->unsignedInteger('grace_period_days')->default(7);
            $table->enum('status', ['active', 'grace', 'suspended', 'cancelled'])->default('active');
            $table->date('current_period_start');
            $table->date('current_period_end');
            $table->timestamp('suspended_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_subscriptions');
    }
};