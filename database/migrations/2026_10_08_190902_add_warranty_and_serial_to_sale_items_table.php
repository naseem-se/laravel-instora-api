<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sale_items', function (Blueprint $table) {
            $table->foreignId('product_item_id')->nullable()->after('product_id')->constrained('product_items')->nullOnDelete();
            $table->date('warranty_ends_at')->nullable()->after('warehouse_id');
            $table->date('guarantee_ends_at')->nullable()->after('warranty_ends_at');
        });
    }

    public function down(): void
    {
        Schema::table('sale_items', function (Blueprint $table) {
            $table->dropForeign(['product_item_id']);
            $table->dropColumn(['product_item_id', 'warranty_ends_at', 'guarantee_ends_at']);
        });
    }
};
