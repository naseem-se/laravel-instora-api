<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('serial_number')->index();
            $table->string('status')->default('in_stock'); // in_stock, sold, returned, defective
            $table->foreignId('warehouse_id')->nullable()->constrained('inventory_warehouses')->nullOnDelete();
            $table->timestamps();

            $table->unique(['company_id', 'product_id', 'serial_number'], 'product_items_serial_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_items');
    }
};
