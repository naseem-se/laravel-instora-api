<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_reversals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('payment_id')->unique()->constrained()->restrictOnDelete();

            $table->text('reason');
            $table->foreignId('reversed_by')->constrained('users')->restrictOnDelete();
            $table->dateTime('reversed_at');
            $table->timestamp('created_at')->useCurrent();

            $table->index('company_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_reversals');
    }
};