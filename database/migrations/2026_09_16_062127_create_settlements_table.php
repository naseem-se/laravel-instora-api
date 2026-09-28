<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settlements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('installment_plan_id')->constrained()->restrictOnDelete();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();

            $table->date('settlement_date');

            $table->decimal('outstanding_amount', 15, 2);
            $table->decimal('discount_amount', 15, 2)->default(0);
            $table->decimal('late_fee_waived', 15, 2)->default(0);
            $table->decimal('final_amount', 15, 2);

            // References `payments`, created later. FK constraint added once that table exists.
            $table->unsignedBigInteger('payment_id')->nullable();
            $table->foreignId('approved_by')->constrained('users')->restrictOnDelete();

            $table->timestamps();

            $table->index(['company_id', 'installment_plan_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settlements');
    }
};