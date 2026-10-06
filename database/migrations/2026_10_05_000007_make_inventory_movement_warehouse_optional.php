<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_movements', function (Blueprint $table) {
            $table->foreignId('warehouse_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        // Keep company-wide stock movements intact when rolling back.
    }
};