<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('company_id')->nullable()->after('id')
                ->constrained('companies')->nullOnDelete();
            $table->string('phone', 50)->nullable()->after('email');
            $table->enum('status', ['active', 'inactive', 'suspended'])
                ->default('active')->after('password');
            $table->timestamp('last_login_at')->nullable()->after('status');

            $table->index('company_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('company_id');
            $table->dropColumn(['phone', 'status', 'last_login_at']);
        });
    }
};