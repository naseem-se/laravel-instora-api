<?php

namespace App\Policies;

use App\Models\InstallmentPlan;
use App\Models\User;

class InstallmentPlanPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('installments.view');
    }

    public function view(User $user, InstallmentPlan $plan): bool
    {
        return $user->can('installments.view') && $this->sameCompany($user, $plan);
    }

    public function create(User $user): bool
    {
        return $user->can('installments.create');
    }

    public function approve(User $user, InstallmentPlan $plan): bool
    {
        return $user->can('installments.approve') && $this->sameCompany($user, $plan);
    }

    public function cancel(User $user, InstallmentPlan $plan): bool
    {
        return $user->can('installments.cancel') && $this->sameCompany($user, $plan);
    }

    public function settle(User $user, InstallmentPlan $plan): bool
    {
        return $user->can('installments.settle') && $this->sameCompany($user, $plan);
    }

    public function delete(User $user, InstallmentPlan $plan): bool
    {
        return $user->can('installments.delete')
            && $this->sameCompany($user, $plan)
            && $plan->status->value === 'completed';
    }

    private function sameCompany(User $user, InstallmentPlan $plan): bool
    {
        return $user->company_id === null || $user->company_id === $plan->company_id;
    }
}