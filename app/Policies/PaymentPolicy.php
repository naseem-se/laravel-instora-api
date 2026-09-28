<?php

namespace App\Policies;

use App\Models\Payment;
use App\Models\User;

class PaymentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('payments.view');
    }

    public function view(User $user, Payment $payment): bool
    {
        return $user->can('payments.view') && $this->sameCompany($user, $payment);
    }

    public function create(User $user): bool
    {
        return $user->can('payments.create');
    }

    public function reverse(User $user, Payment $payment): bool
    {
        return $user->can('payments.reverse') && $this->sameCompany($user, $payment);
    }

    private function sameCompany(User $user, Payment $payment): bool
    {
        return $user->company_id === null || $user->company_id === $payment->company_id;
    }
}