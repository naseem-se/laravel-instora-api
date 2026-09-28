<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('installments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('installment_plan_id')->constrained()->restrictOnDelete();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();

            $table->unsignedInteger('installment_number');
            $table->date('due_date');

            $table->decimal('principal_amount', 15, 2);
            $table->decimal('interest_amount', 15, 2)->default(0);
            $table->decimal('late_fee_amount', 15, 2)->default(0);
            $table->decimal('scheduled_amount', 15, 2);

            $table->decimal('paid_amount', 15, 2)->default(0);
            $table->decimal('remaining_amount', 15, 2)->default(0);

            $table->enum('status', ['pending', 'partial', 'paid', 'overdue', 'waived', 'cancelled'])
                ->default('pending');

            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->unique(['installment_plan_id', 'installment_number']);
            $table->index(['company_id', 'due_date', 'status']);
            $table->index(['company_id', 'customer_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('installments');
    }
};