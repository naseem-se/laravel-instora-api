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

        $tableNames = config('permission.table_names');

        $convertPrimaryKey = function (string $table, string $uniqueIndex, string $columns): void {
            $indexes = collect(DB::select("SHOW INDEX FROM `{$table}`"))
                ->pluck('Key_name')
                ->unique()
                ->all();

            if (in_array('PRIMARY', $indexes, true)) {
                DB::statement("ALTER TABLE `{$table}` DROP PRIMARY KEY");
            }

            if (! in_array($uniqueIndex, $indexes, true)) {
                DB::statement("ALTER TABLE `{$table}` ADD UNIQUE KEY `{$uniqueIndex}` ({$columns})");
            }
        };

        $convertPrimaryKey(
            $tableNames['model_has_permissions'],
            'model_has_permissions_permission_model_type_unique',
            '`company_id`, `permission_id`, `model_id`, `model_type`',
        );
        $convertPrimaryKey(
            $tableNames['model_has_roles'],
            'model_has_roles_role_model_type_unique',
            '`company_id`, `role_id`, `model_id`, `model_type`',
        );
    }

    public function down(): void
    {
        if (! config('permission.teams') || DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        $tableNames = config('permission.table_names');

        DB::statement("ALTER TABLE `{$tableNames['model_has_permissions']}` DROP INDEX `model_has_permissions_permission_model_type_unique`");
        DB::statement("ALTER TABLE `{$tableNames['model_has_permissions']}` ADD PRIMARY KEY (`company_id`, `permission_id`, `model_id`, `model_type`)");
        DB::statement("ALTER TABLE `{$tableNames['model_has_roles']}` DROP INDEX `model_has_roles_role_model_type_unique`");
        DB::statement("ALTER TABLE `{$tableNames['model_has_roles']}` ADD PRIMARY KEY (`company_id`, `role_id`, `model_id`, `model_type`)");
    }
};