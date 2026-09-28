<?php

namespace Database\Seeders;

use App\Models\Company;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class PermissionSeeder extends Seeder
{
    public const PERMISSIONS = [
        'customers.view', 'customers.create', 'customers.update', 'customers.delete',

        'products.view', 'products.create', 'products.update', 'products.delete',

        'installments.view', 'installments.create', 'installments.update', 'installments.delete',
        'installments.approve', 'installments.cancel', 'installments.settle',

        'payments.view', 'payments.create', 'payments.update', 'payments.reverse',

        'reports.view',

        'notifications.view', 'notifications.send', 'notifications.manage',

        'whatsapp.view', 'whatsapp.configure', 'whatsapp.send', 'whatsapp.manage',

        'users.view', 'users.create', 'users.update', 'users.delete',

        'companies.view', 'companies.update',
    ];

    public const SUPER_ADMIN_ONLY = [
        'companies.create', 'companies.delete',
    ];

    public function run(): void
    {
        foreach ([...self::PERMISSIONS, ...self::SUPER_ADMIN_ONLY] as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        $this->syncExistingCompanyAdminRoles();
    }

    public static function companyAdminDefaults(): array
    {
        return self::PERMISSIONS;
    }
    private function syncExistingCompanyAdminRoles(): void
    {
        $registrar = app(PermissionRegistrar::class);
        $originalTeamId = $registrar->getPermissionsTeamId();

        Company::pluck('id')->each(function (int $companyId) use ($registrar) {
            $registrar->setPermissionsTeamId($companyId);

            /** @var Role|null $role */
            $role = Role::query()
                ->where('name', 'Company Admin')
                ->where('company_id', $companyId)
                ->first();

            $role?->syncPermissions(self::companyAdminDefaults());
        });

        $registrar->setPermissionsTeamId($originalTeamId);
    }
}