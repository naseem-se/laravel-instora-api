<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('installment_plans', function (Blueprint $table) {
            $table->foreignId('product_id')->nullable()->after('customer_id')->constrained('products')->restrictOnDelete();
            $table->index(['company_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::table('installment_plans', function (Blueprint $table) {
            $table->dropForeign(['product_id']);
            $table->dropIndex(['company_id', 'product_id']);
            $table->dropColumn('product_id');
        });
    }
};
