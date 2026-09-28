<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('installment_reschedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('installment_id')->constrained()->restrictOnDelete();
            $table->foreignId('installment_plan_id')->constrained()->restrictOnDelete();
            $table->date('old_due_date');
            $table->date('new_due_date');
            $table->text('reason');
            $table->foreignId('approved_by')->constrained('users')->restrictOnDelete();
            // History rows are never edited - created_at only, no updated_at.
            $table->timestamp('created_at')->useCurrent();

            $table->index(['company_id', 'installment_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('installment_reschedules');
    }
};