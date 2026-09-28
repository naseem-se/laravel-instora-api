<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notification_logs', function (Blueprint $table) {
            // Without these, editing a template later makes it impossible to
            // see what was actually sent to a customer historically - this is
            // Architecture.md's "message audit trail" feature.
            $table->string('rendered_subject', 500)->nullable()->after('template_id');
            $table->text('rendered_body')->nullable()->after('rendered_subject');
        });
    }

    public function down(): void
    {
        Schema::table('notification_logs', function (Blueprint $table) {
            $table->dropColumn(['rendered_subject', 'rendered_body']);
        });
    }
};