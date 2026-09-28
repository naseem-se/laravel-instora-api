<?php

namespace App\Services;

use App\Enums\CustomerStatus;
use App\Enums\InstallmentPlanStatus;
use App\Enums\InstallmentStatus;
use App\Enums\PaymentStatus;
use App\Models\Customer;
use App\Models\Installment;
use App\Models\InstallmentPlan;
use App\Models\Payment;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use InvalidArgumentException;

class ReportService
{
    public const BUCKETS = ['current', '1_30', '31_60', '61_90', '90_plus'];

    public function dashboard(int $companyId): array
    {
        $activePlans = InstallmentPlan::where('company_id', $companyId)
            ->whereIn('status', [InstallmentPlanStatus::Active, InstallmentPlanStatus::Overdue]);

        $overdueInstallments = Installment::where('company_id', $companyId)->where('status', InstallmentStatus::Overdue);

        $collectedThisMonth = Payment::where('company_id', $companyId)
            ->where('status', PaymentStatus::Completed)
            ->where('payment_date', '>=', now()->startOfMonth())
            ->sum('amount');

        $collectedToday = Payment::where('company_id', $companyId)
            ->where('status', PaymentStatus::Completed)
            ->whereDate('payment_date', today())
            ->sum('amount');

        return [
            'active_plans_count' => (clone $activePlans)->count(),
            'total_outstanding' => $this->formatSum((clone $activePlans)->sum('remaining_amount')),
            'overdue_installments_count' => (clone $overdueInstallments)->count(),
            'overdue_amount' => $this->formatSum((clone $overdueInstallments)->sum('remaining_amount')),
            'collected_this_month' => $this->formatSum($collectedThisMonth),
            'collected_today' => $this->formatSum($collectedToday),
            'active_customers_count' => Customer::where('company_id', $companyId)
                ->where('status', CustomerStatus::Active)
                ->count(),
            'new_plans_this_month' => InstallmentPlan::where('company_id', $companyId)
                ->where('created_at', '>=', now()->startOfMonth())
                ->count(),
            'new_customers_this_month' => Customer::where('company_id', $companyId)
                ->where('created_at', '>=', now()->startOfMonth())
                ->count(),
            'installments_due_this_week' => Installment::where('company_id', $companyId)
                ->whereIn('status', [InstallmentStatus::Pending, InstallmentStatus::Partial])
                ->whereBetween('due_date', [today(), today()->addDays(7)])
                ->count(),
            'payment_method_breakdown' => $this->paymentMethodBreakdown($companyId, now()->startOfMonth()->toDateString(), now()->toDateString()),
            'collections_trend' => $this->collectionsTrend($companyId, 14),
            'recent_due_installments' => Installment::with(['customer', 'installmentPlan'])
                ->where('company_id', $companyId)
                ->whereIn('status', [InstallmentStatus::Pending, InstallmentStatus::Partial, InstallmentStatus::Overdue])
                ->where('due_date', '<=', today()->addDays(7))
                ->orderBy('due_date', 'asc')
                ->limit(5)
                ->get()
                ->map(fn($i) => [
                    'id' => $i->id,
                    'amount' => $this->formatSum($i->remaining_amount),
                    'due_date' => $i->due_date->format('Y-m-d'),
                    'status' => $i->status->value,
                    'installment_plan_id' => $i->installment_plan_id,
                    'customer_name' => $i->customer->name,
                ]),
        ];
    }

    /** @return list<array{bucket: string, installment_count: int, total_remaining: string}> */
    public function agingSummary(int $companyId): array
    {
        return collect(self::BUCKETS)->map(function (string $bucket) use ($companyId) {
            $query = $this->installmentsInBucket($companyId, $bucket);

            return [
                'bucket' => $bucket,
                'installment_count' => (clone $query)->count(),
                'total_remaining' => $this->formatSum((clone $query)->sum('remaining_amount')),
            ];
        })->all();
    }

    public function agingInstallments(int $companyId, string $bucket, int $perPage): LengthAwarePaginator
    {
        return $this->installmentsInBucket($companyId, $bucket)
            ->with(['installmentPlan', 'customer'])
            ->orderBy('due_date')
            ->paginate($perPage);
    }

    /** @return list<array{payment_method: string, payment_count: int, total_amount: string}> */
    public function paymentMethodBreakdown(int $companyId, string $from, string $to): array
    {
        return Payment::where('company_id', $companyId)
            ->where('status', PaymentStatus::Completed)
            ->whereBetween('payment_date', [$from, "{$to} 23:59:59"])
            ->selectRaw('payment_method, COUNT(*) as payment_count, SUM(amount) as total_amount')
            ->groupBy('payment_method')
            ->get()
            ->map(fn ($row) => [
                'payment_method' => $row->payment_method,
                'payment_count' => (int) $row->payment_count,
                'total_amount' => $this->formatSum($row->total_amount),
            ])
            ->all();
    }

    public function collectionsTrend(int $companyId, int $days): array
    {
        $startDate = now()->subDays($days - 1)->startOfDay();
        $endDate = now()->endOfDay();

        $payments = Payment::where('company_id', $companyId)
            ->where('status', PaymentStatus::Completed)
            ->whereBetween('payment_date', [$startDate, $endDate])
            ->selectRaw('DATE(payment_date) as date, SUM(amount) as total')
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->keyBy('date');

        $trend = [];
        for ($i = 0; $i < $days; $i++) {
            $date = $startDate->copy()->addDays($i)->format('Y-m-d');
            $trend[] = [
                'date' => $date,
                'total' => $this->formatSum($payments->has($date) ? $payments->get($date)->total : 0),
            ];
        }

        return $trend;
    }

    private function installmentsInBucket(int $companyId, string $bucket): Builder
    {
        $today = Carbon::today();

        $query = Installment::query()
            ->where('company_id', $companyId)
            ->whereIn('status', [InstallmentStatus::Pending, InstallmentStatus::Partial, InstallmentStatus::Overdue])
            ->whereHas('installmentPlan', fn ($q) => $q->where('status', '!=', InstallmentPlanStatus::Cancelled));

        return match ($bucket) {
            'current' => $query->whereDate('due_date', '>=', $today),
            '1_30' => $query->whereDate('due_date', '<', $today)->whereDate('due_date', '>=', $today->copy()->subDays(30)),
            '31_60' => $query->whereDate('due_date', '<', $today->copy()->subDays(30))->whereDate('due_date', '>=', $today->copy()->subDays(60)),
            '61_90' => $query->whereDate('due_date', '<', $today->copy()->subDays(60))->whereDate('due_date', '>=', $today->copy()->subDays(90)),
            '90_plus' => $query->whereDate('due_date', '<', $today->copy()->subDays(90)),
            default => throw new InvalidArgumentException("Unknown aging bucket: {$bucket}"),
        };
    }

    private function formatSum(mixed $sum): string
    {
        return number_format((float) ($sum ?? 0), 2, '.', '');
    }
}