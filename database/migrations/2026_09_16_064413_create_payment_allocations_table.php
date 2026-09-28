<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_allocations', function (Blueprint $table) {
            $table->id();
            // restrictOnDelete: a completed payment must never be removable while allocations exist.
            $table->foreignId('payment_id')->constrained()->restrictOnDelete();
            $table->foreignId('installment_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('invoice_id')->nullable()->constrained()->nullOnDelete();

            $table->decimal('principal_amount', 15, 2)->default(0);
            $table->decimal('interest_amount', 15, 2)->default(0);
            $table->decimal('late_fee_amount', 15, 2)->default(0);
            $table->decimal('allocated_amount', 15, 2);

            $table->timestamps();

            $table->index('payment_id');
            $table->index('installment_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_allocations');
    }
};