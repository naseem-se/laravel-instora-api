<?php

namespace App\Services;

use App\Enums\AuditAction;
use App\Enums\UserStatus;
use App\Models\User;
use App\Support\CompanyContext;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

class UserService
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function create(array $data, CompanyContext $context, User $actor): User
    {
        $companyId = $context->requireCompanyId();

        return DB::transaction(function () use ($data, $companyId, $actor) {
            // company_id and status are guarded on User, so they must be set
            // via direct property assignment, not inside the create() array -
            // $guarded blocks internal mass assignment too, not just request input.
            $user = new User([
                'name' => $data['name'],
                'email' => $data['email'],
                'phone' => $data['phone'] ?? null,
                'password' => $data['password'],
            ]);
            $user->company_id = $companyId;
            $user->status = UserStatus::Active;
            $user->save();

            app(PermissionRegistrar::class)->setPermissionsTeamId($companyId);
            $user->assignRole($data['role']);

            $this->audit->log(
                AuditAction::UserCreated->value,
                entity: $user,
                newValues: ['name' => $user->name, 'email' => $user->email, 'role' => $data['role']],
                companyId: $companyId,
                userId: $actor->id,
            );

            return $user;
        });
    }

    public function update(User $user, array $data, User $actor): User
    {
        return DB::transaction(function () use ($user, $data, $actor) {
            $original = $user->only(['name', 'phone', 'status']);
            $originalRole = $user->roles->pluck('name')->first();

            $user->fill(collect($data)->only(['name', 'phone'])->toArray());

            if (isset($data['status'])) {
                $user->status = $data['status'];
            }

            $user->save();

            if (isset($data['role'])) {
                app(PermissionRegistrar::class)->setPermissionsTeamId($user->company_id);
                $user->syncRoles([$data['role']]);
            }

            $this->audit->log(
                AuditAction::UserUpdated->value,
                entity: $user,
                oldValues: [...$original, 'role' => $originalRole],
                newValues: [...$user->only(['name', 'phone', 'status']), 'role' => $data['role'] ?? $originalRole],
                companyId: $user->company_id,
                userId: $actor->id,
            );

            return $user;
        });
    }
}