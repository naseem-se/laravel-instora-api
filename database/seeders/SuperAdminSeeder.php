<?php

namespace Database\Seeders;

use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->setPermissionsTeamId(null);

        $role = Role::firstOrCreate(['name' => 'Super Admin', 'guard_name' => 'web', 'company_id' => null]);
        $role->syncPermissions(Permission::all());

        $email = env('SUPER_ADMIN_EMAIL');
        $password = env('SUPER_ADMIN_PASSWORD');

        if (! $email || ! $password) {
            $this->command?->warn(
                'SUPER_ADMIN_EMAIL / SUPER_ADMIN_PASSWORD not set in .env - skipping Super '.
                'Admin user creation. Set both and rerun: php artisan db:seed --class=SuperAdminSeeder'
            );

            return;
        }

        $user = User::firstOrCreate(
            ['email' => $email],
            [
                'company_id' => null,
                'name' => 'Super Admin',
                'password' => $password, // hashed via the model's 'hashed' cast
                'status' => UserStatus::Active,
            ]
        );

        $user->syncRoles([$role]);
    }
}