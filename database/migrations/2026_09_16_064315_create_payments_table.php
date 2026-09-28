<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->string('payment_number', 100);

            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->foreignId('installment_plan_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('invoice_id')->nullable()->constrained()->nullOnDelete();

            $table->dateTime('payment_date');
            $table->decimal('amount', 15, 2);

            $table->enum('payment_method', ['cash', 'bank_transfer', 'cheque', 'card', 'easypaisa', 'jazzcash', 'other']);
            $table->foreignId('cash_account_id')->nullable()->constrained()->nullOnDelete();

            $table->string('reference_number')->nullable();
            $table->text('notes')->nullable();

            $table->foreignId('received_by')->constrained('users')->restrictOnDelete();
            $table->enum('status', ['completed', 'reversed'])->default('completed');

            $table->timestamps();

            $table->unique(['company_id', 'payment_number']);
            $table->index(['company_id', 'customer_id']);
            $table->index(['company_id', 'payment_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};