<?php

namespace App\Policies;

use App\Models\CustomerDocument;
use App\Models\User;

class CustomerDocumentPolicy
{
    public function view(User $user, CustomerDocument $document): bool
    {
        return $user->can('customers.view') && $this->sameCompany($user, $document);
    }

    public function create(User $user): bool
    {
        return $user->can('customers.update');
    }

    public function delete(User $user, CustomerDocument $document): bool
    {
        return $user->can('customers.update') && $this->sameCompany($user, $document);
    }

    private function sameCompany(User $user, CustomerDocument $document): bool
    {
        return $user->company_id === null || $user->company_id === $document->company_id;
    }
}