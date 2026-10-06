<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('warehouse_id')->nullable()->constrained('inventory_warehouses')->restrictOnDelete();
            $table->enum('type', ['in', 'out', 'adjustment', 'transfer']);
            $table->enum('direction', ['in', 'out']);
            $table->decimal('quantity', 15, 3);
            $table->decimal('unit_cost', 15, 2)->nullable();
            $table->uuid('transfer_id')->nullable()->index();
            $table->string('serial_number')->nullable();
            $table->string('variant_label')->nullable();
            $table->string('note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['company_id', 'product_id', 'warehouse_id', 'created_at'], 'inventory_movement_lookup');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_movements');
    }
};