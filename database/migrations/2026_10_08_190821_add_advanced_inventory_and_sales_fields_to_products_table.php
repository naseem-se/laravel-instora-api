<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->boolean('has_serial_numbers')->default(false)->after('stock_quantity');
            $table->integer('warranty_days')->nullable()->after('has_serial_numbers');
            $table->integer('guarantee_days')->nullable()->after('warranty_days');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['has_serial_numbers', 'warranty_days', 'guarantee_days']);
        });
    }
};
