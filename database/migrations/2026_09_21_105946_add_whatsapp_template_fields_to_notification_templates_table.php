<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notification_templates', function (Blueprint $table) {
            $table->string('whatsapp_template_name')->nullable()->after('body');
            $table->string('whatsapp_template_language')->nullable()->after('whatsapp_template_name');
        });
    }

    public function down(): void
    {
        Schema::table('notification_templates', function (Blueprint $table) {
            $table->dropColumn(['whatsapp_template_name', 'whatsapp_template_language']);
        });
    }
};
