<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (! config('permission.teams') || DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        $teamColumn = config('permission.column_names.team_foreign_key', 'company_id');
        $tableNames = config('permission.table_names');

        foreach (['model_has_permissions', 'model_has_roles'] as $tableName) {
            DB::statement("ALTER TABLE `{$tableNames[$tableName]}` MODIFY `{$teamColumn}` BIGINT UNSIGNED NULL");
        }
    }

    public function down(): void
    {
        if (! config('permission.teams') || DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        $teamColumn = config('permission.column_names.team_foreign_key', 'company_id');
        $tableNames = config('permission.table_names');

        foreach (['model_has_permissions', 'model_has_roles'] as $tableName) {
            DB::statement("ALTER TABLE `{$tableNames[$tableName]}` MODIFY `{$teamColumn}` BIGINT UNSIGNED NOT NULL");
        }
    }
};