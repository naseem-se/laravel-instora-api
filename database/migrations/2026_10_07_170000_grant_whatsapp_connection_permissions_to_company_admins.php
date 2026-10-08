<?php

use App\Models\Company;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        $permissions = collect(['whatsapp.view', 'whatsapp.configure', 'whatsapp.send'])
            ->map(fn (string $name) => Permission::firstOrCreate([
                'name' => $name,
                'guard_name' => 'web',
            ]));

        $registrar = app(PermissionRegistrar::class);
        $originalTeamId = $registrar->getPermissionsTeamId();

        Company::query()->pluck('id')->each(function (int $companyId) use ($registrar, $permissions): void {
            $registrar->setPermissionsTeamId($companyId);

            $role = Role::query()
                ->where('name', 'Company Admin')
                ->where('company_id', $companyId)
                ->first();

            $role?->givePermissionTo($permissions);
        });

        $registrar->setPermissionsTeamId($originalTeamId);
    }

    public function down(): void
    {
        // Preserve grants in case company administrator permissions are customized.
    }
};
