<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->string('customer_number', 100);
            $table->string('name');
            $table->string('father_name')->nullable();
            $table->string('cnic', 50)->nullable();
            $table->string('phone', 50);
            $table->string('alternate_phone', 50)->nullable();
            $table->string('email')->nullable();
            $table->date('date_of_birth')->nullable();
            $table->string('gender', 30)->nullable();
            $table->text('address')->nullable();
            $table->string('city', 100)->nullable();
            $table->string('province', 100)->nullable();
            $table->string('postal_code', 20)->nullable();
            $table->string('occupation')->nullable();
            $table->decimal('monthly_income', 15, 2)->nullable();
            $table->enum('status', ['active', 'inactive', 'blocked'])->default('active');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['company_id', 'customer_number']);
            $table->index(['company_id', 'phone']);
            $table->index(['company_id', 'status']);
            // CNIC is intentionally not unique - the same identifier may recur across tenants.
            $table->index(['company_id', 'cnic']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};