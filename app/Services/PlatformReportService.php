<?php

namespace App\Services;

use App\Enums\CompanyStatus;
use App\Enums\InstallmentPlanStatus;
use App\Models\Company;
use App\Models\InstallmentPlan;
use App\Models\User;

/**
 * Platform-wide statistics for the Super Admin dashboard. Deliberately
 * separate from ReportService, which always scopes via
 * CompanyContext::requireCompanyId() - a Super Admin has no company to
 * scope by, so these queries are intentionally unscoped across every company.
 */
class PlatformReportService
{
    public function dashboard(): array
    {
        return [
            'companies_total' => Company::count(),
            'companies_active' => Company::where('status', CompanyStatus::Active)->count(),
            'companies_suspended' => Company::where('status', CompanyStatus::Suspended)->count(),
            'companies_disabled' => Company::where('status', CompanyStatus::Disabled)->count(),
            'users_total' => User::whereNotNull('company_id')->count(),
            'active_installment_plans' => InstallmentPlan::whereIn('status', [
                InstallmentPlanStatus::Active, InstallmentPlanStatus::Overdue,
            ])->count(),
            'platform_outstanding_by_currency' => $this->outstandingByCurrency(),
            'recent_companies' => Company::orderByDesc('created_at')->limit(5)->get()->map(fn ($c) => [
                'id' => $c->id,
                'name' => $c->name,
                'code' => $c->code,
                'status' => $c->status->value,
                'created_at' => $c->created_at?->toIso8601String(),
            ]),
        ];
    }

    /**
     * Grouped by currency, never summed into one number - companies can use
     * different currencies (Company.currency), and adding PKR + USD
     * together would be meaningless.
     */
    private function outstandingByCurrency(): array
    {
        return InstallmentPlan::query()
            ->join('companies', 'companies.id', '=', 'installment_plans.company_id')
            ->whereIn('installment_plans.status', [InstallmentPlanStatus::Active, InstallmentPlanStatus::Overdue])
            ->selectRaw('companies.currency, SUM(installment_plans.remaining_amount) as total')
            ->groupBy('companies.currency')
            ->get()
            ->map(fn ($row) => ['currency' => $row->currency, 'total' => number_format((float) $row->total, 2, '.', '')])
            ->all();
    }
}