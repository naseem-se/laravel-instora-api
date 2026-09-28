<?php

namespace Database\Seeders;

use App\Enums\CompanyStatus;
use App\Enums\UserStatus;
use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class CompanySeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::firstOrCreate(
            ['code' => env('SEED_COMPANY_CODE', 'DEMO')],
            [
                'name' => env('SEED_COMPANY_NAME', 'Demo Company'),
                'email' => env('SEED_COMPANY_EMAIL','naseemshah459@gmail.com'),
                'phone' => env('SEED_COMPANY_PHONE','03412855585'),
                'currency' => env('SEED_COMPANY_CURRENCY', 'PKR'),
                'timezone' => env('SEED_COMPANY_TIMEZONE', 'UTC'),
                'status' => CompanyStatus::Active,
            ],
        );

        $adminRole = $this->syncRoles($company);
        $this->seedAdminUser($company, $adminRole);
    }

    private function syncRoles(Company $company): Role
    {
        $registrar = app(PermissionRegistrar::class);
        $originalTeamId = $registrar->getPermissionsTeamId();

        try {
            $registrar->setPermissionsTeamId($company->id);

            $admin = Role::firstOrCreate([
                'name' => 'Company Admin',
                'guard_name' => 'web',
                'company_id' => $company->id,
            ]);
            $admin->syncPermissions(PermissionSeeder::companyAdminDefaults());

            Role::firstOrCreate([
                'name' => 'Employee',
                'guard_name' => 'web',
                'company_id' => $company->id,
            ])->syncPermissions([]);

            return $admin;
        } finally {
            $registrar->setPermissionsTeamId($originalTeamId);
        }
    }

    private function seedAdminUser(Company $company, Role $adminRole): void
    {
        $email = env('SEED_COMPANY_ADMIN_EMAIL', 'naseemshah459@gmail.com');
        $password = env('SEED_COMPANY_ADMIN_PASSWORD', 'naseem123');

        if (! $email || ! $password) {
            $this->command?->warn(
                'SEED_COMPANY_ADMIN_EMAIL / SEED_COMPANY_ADMIN_PASSWORD not set - skipping seeded company admin user.'
            );

            return;
        }

        $user = User::firstOrNew(['email' => $email]);
        $user->name = env('SEED_COMPANY_ADMIN_NAME', 'Demo Company Admin');
        $user->company_id = $company->id;
        $user->password = $password;
        $user->status = UserStatus::Active;
        $user->save();

        $registrar = app(PermissionRegistrar::class);
        $originalTeamId = $registrar->getPermissionsTeamId();

        try {
            $registrar->setPermissionsTeamId($company->id);
            $user->syncRoles([$adminRole]);
        } finally {
            $registrar->setPermissionsTeamId($originalTeamId);
        }
    }
}