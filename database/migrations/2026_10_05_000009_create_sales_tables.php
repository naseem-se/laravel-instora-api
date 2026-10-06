<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->foreignId('installment_plan_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('invoice_id')->nullable()->constrained()->nullOnDelete();
            $table->string('sale_number', 100);
            $table->enum('payment_type', ['cash', 'installment']);
            $table->enum('status', ['completed', 'pending', 'cancelled', 'returned'])->default('pending');
            $table->decimal('subtotal', 15, 2);
            $table->decimal('discount', 15, 2)->default(0);
            $table->decimal('additional_charges', 15, 2)->default(0);
            $table->decimal('tax', 15, 2)->default(0);
            $table->decimal('total_amount', 15, 2);
            $table->decimal('down_payment', 15, 2)->default(0);
            $table->string('payment_method', 50)->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('sold_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['company_id', 'sale_number']);
            $table->index(['company_id', 'status', 'created_at']);
            $table->index(['company_id', 'customer_id']);
        });

        Schema::create('sale_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')->constrained()->restrictOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('warehouse_id')->nullable()->constrained('inventory_warehouses')->nullOnDelete();
            $table->string('product_name');
            $table->string('sku')->nullable();
            $table->unsignedInteger('quantity');
            $table->decimal('unit_price', 15, 2);
            $table->decimal('returned_quantity', 15, 3)->default(0);
            $table->decimal('line_total', 15, 2);
            $table->timestamps();
            $table->index('sale_id');
        });

        Schema::create('sale_returns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('sale_id')->constrained()->restrictOnDelete();
            $table->string('return_number', 100);
            $table->enum('type', ['return', 'cancellation']);
            $table->decimal('amount', 15, 2)->default(0);
            $table->string('reason', 500)->nullable();
            $table->foreignId('processed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'return_number']);
        });

        Schema::create('sale_return_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_return_id')->constrained('sale_returns')->restrictOnDelete();
            $table->foreignId('sale_item_id')->constrained('sale_items')->restrictOnDelete();
            $table->unsignedInteger('quantity');
            $table->decimal('unit_price', 15, 2);
            $table->decimal('amount', 15, 2);
            $table->timestamps();
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->foreignId('sale_id')->nullable()->after('installment_plan_id')->constrained()->nullOnDelete();
            $table->decimal('additional_charges', 15, 2)->default(0)->after('discount');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->foreignId('sale_id')->nullable()->after('installment_plan_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('payments', fn (Blueprint $table) => $table->dropConstrainedForeignId('sale_id'));
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropConstrainedForeignId('sale_id');
            $table->dropColumn('additional_charges');
        });
        Schema::dropIfExists('sale_return_items');
        Schema::dropIfExists('sale_returns');
        Schema::dropIfExists('sale_items');
        Schema::dropIfExists('sales');
    }
};