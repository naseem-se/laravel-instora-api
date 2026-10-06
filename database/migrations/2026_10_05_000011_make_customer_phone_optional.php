<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->string('phone', 50)->nullable()->change();
        });
    }

    public function down(): void
    {
        // Existing one-time buyers may not have a phone; retain those records.
    }
};