<?php

namespace App\Policies;

use App\Models\Customer;
use App\Models\User;

class CustomerPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('customers.view');
    }

    public function view(User $user, Customer $customer): bool
    {
        return $user->can('customers.view') && $this->sameCompany($user, $customer);
    }

    public function create(User $user): bool
    {
        return $user->can('customers.create');
    }

    public function update(User $user, Customer $customer): bool
    {
        return $user->can('customers.update') && $this->sameCompany($user, $customer);
    }

    public function delete(User $user, Customer $customer): bool
    {
        return $user->can('customers.delete') && $this->sameCompany($user, $customer);
    }

    private function sameCompany(User $user, Customer $customer): bool
    {
        return $user->company_id === null || $user->company_id === $customer->company_id;
    }
}