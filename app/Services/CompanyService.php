<?php

namespace App\Services;

use App\Enums\AuditAction;
use App\Enums\CompanyStatus;
use App\Enums\UserStatus;
use App\Models\Company;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class CompanyService
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function create(array $data, User $actor): Company
    {
        return DB::transaction(function () use ($data, $actor) {
            $company = new Company([
                'name' => $data['name'],
                'code' => $data['code'],
                'email' => $data['email'] ?? null,
                'phone' => $data['phone'] ?? null,
                'address' => $data['address'] ?? null,
                'currency' => $data['currency'] ?? 'PKR',
                'timezone' => $data['timezone'] ?? 'UTC',
            ]);
            $company->status = CompanyStatus::Active;
            $company->save();

            $adminRole = $this->seedDefaultRoles($company);

            $adminUser = new User([
                'name' => $data['admin_name'],
                'email' => $data['admin_email'],
                'password' => $data['admin_password'],
            ]);
            $adminUser->company_id = $company->id;
            $adminUser->status = UserStatus::Active;
            $adminUser->save();

            $registrar = app(PermissionRegistrar::class);
            $originalTeamId = $registrar->getPermissionsTeamId();

            try {
                $registrar->setPermissionsTeamId($company->id);
                $adminUser->assignRole($adminRole);
            } finally {
                $registrar->setPermissionsTeamId($originalTeamId);
            }

            $this->audit->log(
                AuditAction::UserCreated->value,
                entity: $adminUser,
                newValues: ['name' => $adminUser->name, 'email' => $adminUser->email, 'role' => $adminRole->name],
                companyId: $company->id,
                userId: $actor->id,
            );

            $this->audit->log(
                AuditAction::CompanyCreated->value,
                entity: $company,
                newValues: $company->only(['name', 'code', 'status']),
                companyId: $company->id,
                userId: $actor->id,
            );

            return $company;
        });
    }

    public function update(Company $company, array $data, User $actor): Company
    {
        return DB::transaction(function () use ($company, $data, $actor) {
            $original = $company->only(['name', 'email', 'phone', 'address', 'currency', 'timezone', 'status']);
            $nameChanged = isset($data['name']) && $data['name'] !== $company->name;

            $company->fill(collect($data)->except(['status'])->toArray());

            if (isset($data['status'])) {
                $company->status = $data['status'];
                $company->suspension_reason = $data['status'] === 'suspended' ? 'manual' : null;
            }

            $company->save();

            if ($nameChanged) {
                $registrar = app(PermissionRegistrar::class);
                $originalTeamId = $registrar->getPermissionsTeamId();

                try {
                    $registrar->setPermissionsTeamId($company->id);
                    foreach ($company->users()->get() as $user) {
                        if ($user->hasRole('Company Admin')) {
                            $user->name = $company->name;
                            $user->save();
                        }
                    }
                } finally {
                    $registrar->setPermissionsTeamId($originalTeamId);
                }
            }

            $this->audit->log(
                AuditAction::CompanyUpdated->value,
                entity: $company,
                oldValues: $original,
                newValues: $company->only(array_keys($original)),
                companyId: $company->id,
                userId: $actor->id,
            );

            return $company;
        });
    }

    public function delete(Company $company, User $actor): void
    {
        DB::transaction(function () use ($company, $actor) {
            // Delete files from storage
            $documents = DB::table('customer_documents')->where('company_id', $company->id)->get();
            foreach ($documents as $doc) {
                \Illuminate\Support\Facades\Storage::disk($doc->storage_disk)->delete($doc->file_path);
            }

            $paymentReceipts = DB::table('payments')
                ->where('company_id', $company->id)
                ->whereNotNull('receipt_path')
                ->whereNotNull('receipt_disk')
                ->get(['receipt_path', 'receipt_disk']);
            foreach ($paymentReceipts as $receipt) {
                \Illuminate\Support\Facades\Storage::disk($receipt->receipt_disk)->delete($receipt->receipt_path);
            }

            $saleReceipts = DB::table('sales')
                ->where('company_id', $company->id)
                ->whereNotNull('receipt_path')
                ->whereNotNull('receipt_disk')
                ->get(['receipt_path', 'receipt_disk']);
            foreach ($saleReceipts as $receipt) {
                \Illuminate\Support\Facades\Storage::disk($receipt->receipt_disk)->delete($receipt->receipt_path);
            }

            // Temporarily disable foreign key checks to delete all related data
            DB::statement('SET FOREIGN_KEY_CHECKS=0;');

            // Delete all related records
            $tables = DB::select('SHOW TABLES');
            foreach ($tables as $tableObj) {
                $table = array_values((array)$tableObj)[0];
                if ($table === 'companies') continue;
                
                $columns = \Illuminate\Support\Facades\Schema::getColumnListing($table);
                if (in_array('company_id', $columns)) {
                    DB::table($table)->where('company_id', $company->id)->delete();
                }
            }

            $companyData = $company->toArray();
            $company->delete();

            DB::statement('SET FOREIGN_KEY_CHECKS=1;');

            $this->audit->log(
                AuditAction::CompanyDeleted->value,
                entity: $company,
                oldValues: $companyData,
                companyId: null,
                userId: $actor->id,
            );
        });
    }

    private function seedDefaultRoles(Company $company): Role
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
            ])
                ->syncPermissions([]);

            return $admin;
        } finally {
            $registrar->setPermissionsTeamId($originalTeamId);
        }
    }
}