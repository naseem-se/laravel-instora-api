<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_documents', function (Blueprint $table) {
            // Existing records were stored on the public disk before disk selection was configurable.
            $table->string('storage_disk', 50)->default('public')->after('file_path');
        });
    }

    public function down(): void
    {
        Schema::table('customer_documents', function (Blueprint $table) {
            $table->dropColumn('storage_disk');
        });
    }
};
