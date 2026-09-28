<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('installment_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->string('plan_number', 100);

            $table->date('start_date');
            $table->date('end_date')->nullable();

            $table->decimal('principal_amount', 15, 2);
            $table->decimal('down_payment', 15, 2);
            $table->decimal('financed_amount', 15, 2);

            $table->enum('financial_charge_type', ['none', 'markup', 'interest', 'service_charge'])
                ->default('none');
            $table->string('interest_type', 50)->nullable();
            $table->decimal('interest_rate', 10, 4)->nullable();
            $table->decimal('interest_amount', 15, 2)->default(0);

            $table->enum('late_fee_type', ['none', 'fixed', 'percentage', 'per_day', 'percentage_per_day'])
                ->default('none');
            $table->decimal('late_fee_rate', 10, 4)->nullable();
            $table->decimal('late_fee_amount', 15, 2)->nullable();
            $table->decimal('maximum_late_fee', 15, 2)->nullable();

            $table->decimal('total_amount', 15, 2);
            $table->decimal('paid_amount', 15, 2)->default(0);
            $table->decimal('remaining_amount', 15, 2)->default(0);

            $table->enum('installment_frequency', ['daily', 'weekly', 'biweekly', 'monthly', 'quarterly', 'custom']);
            $table->unsignedInteger('number_of_installments');
            $table->decimal('installment_amount', 15, 2);

            $table->enum('status', ['draft', 'pending', 'active', 'overdue', 'defaulted', 'completed', 'cancelled', 'settled'])
                ->default('draft');

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->text('notes')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['company_id', 'plan_number']);
            $table->index(['company_id', 'customer_id']);
            $table->index(['company_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('installment_plans');
    }
};