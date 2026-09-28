<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('category_id')->nullable()
                ->constrained('product_categories')->nullOnDelete();
            $table->string('sku', 100)->nullable();
            $table->string('name');
            $table->text('description')->nullable();
            $table->decimal('cost_price', 15, 2);
            $table->decimal('cash_price', 15, 2);
            $table->decimal('installment_price', 15, 2)->nullable();
            $table->decimal('stock_quantity', 15, 3)->nullable();
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['company_id', 'sku']);
            $table->index(['company_id', 'category_id']);
            $table->index(['company_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};