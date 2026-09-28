<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! config('permission.teams')) {
            return;
        }

        $teamColumn = config('permission.column_names.team_foreign_key', 'company_id');
        $tableNames = config('permission.table_names');

        foreach (['model_has_permissions', 'model_has_roles'] as $tableName) {
            Schema::table($tableNames[$tableName], function (Blueprint $table) use ($teamColumn) {
                $table->unsignedBigInteger($teamColumn)->nullable()->change();
            });
        }
    }

    public function down(): void
    {
        if (! config('permission.teams')) {
            return;
        }

        $teamColumn = config('permission.column_names.team_foreign_key', 'company_id');
        $tableNames = config('permission.table_names');

        foreach (['model_has_permissions', 'model_has_roles'] as $tableName) {
            Schema::table($tableNames[$tableName], function (Blueprint $table) use ($teamColumn) {
                $table->unsignedBigInteger($teamColumn)->nullable(false)->change();
            });
        }
    }
};