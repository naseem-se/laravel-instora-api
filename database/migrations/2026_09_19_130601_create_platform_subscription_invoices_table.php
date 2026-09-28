<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_subscription_invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subscription_id')->constrained('platform_subscriptions')->cascadeOnDelete();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('invoice_number', 100)->unique();
            $table->date('period_start');
            $table->date('period_end');
            $table->decimal('amount', 15, 2);
            $table->string('currency', 10);
            $table->date('due_date');
            $table->enum('status', ['pending', 'paid', 'overdue', 'cancelled'])->default('pending');
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'status']);
            $table->index(['subscription_id', 'period_start'], 'sub_invoices_sub_period_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_subscription_invoices');
    }
};