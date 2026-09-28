<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_subscription_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained('platform_subscription_invoices')->restrictOnDelete();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->decimal('amount', 15, 2);
            $table->string('currency', 10);
            $table->date('payment_date');
            $table->string('payment_method', 50);
            $table->string('reference_number')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index('company_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_subscription_payments');
    }
};