<?php

namespace App\Http\Controllers;

use App\Support\CompanyContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

abstract class Controller
{
    use AuthorizesRequests;

    /**
     * Resolve a company-owned model by ID, scoped to the current tenant.
     * A foreign-tenant ID behaves identically to a nonexistent one (404) -
     * it never confirms the record exists elsewhere. Super Admin bypasses
     * the scope entirely.
     */
    protected function findOwned(string $modelClass, int|string $id, CompanyContext $context): Model
    {
        $query = $modelClass::query();

        return $context->isSuperAdmin()
            ? $query->findOrFail($id)
            : $query->where('company_id', $context->requireCompanyId())->findOrFail($id);
    }
}