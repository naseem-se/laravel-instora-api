<?php

namespace App\Services;

use App\Enums\CompanyStatus;
use App\Enums\SubscriptionInvoiceStatus;
use App\Enums\SubscriptionStatus;
use App\Models\Company;
use App\Models\PlatformSubscription;
use App\Models\PlatformSubscriptionInvoice;
use App\Models\PlatformSubscriptionPayment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class SubscriptionService
{
    public function create(int $companyId, array $data, User $actor): PlatformSubscription
    {
        return DB::transaction(function () use ($companyId, $data, $actor) {
            $company = Company::findOrFail($companyId);

            $anchorDay = (int) $data['billing_anchor_day'];
            $periodStart = now()->startOfDay();
            $periodEnd = $this->nextAnchorDate($periodStart, $anchorDay);

            $subscription = new PlatformSubscription([
                'monthly_fee' => $data['monthly_fee'],
                'currency' => $data['currency'] ?? $company->currency,
                'billing_anchor_day' => $anchorDay,
                'grace_period_days' => $data['grace_period_days'] ?? 7,
            ]);
            $subscription->company_id = $companyId;
            $subscription->status = SubscriptionStatus::Active;
            $subscription->setAttribute('current_period_start', $periodStart->toDateString());
            $subscription->setAttribute('current_period_end', $periodEnd->toDateString());
            $subscription->created_by = $actor->id;
            $subscription->updated_by = $actor->id;
            $subscription->save();

            $this->createInvoice($subscription, $periodStart, $periodEnd);

            return $subscription;
        });
    }

    public function update(PlatformSubscription $subscription, array $data, User $actor): PlatformSubscription
    {
        $subscription->fill(collect($data)->only(['monthly_fee', 'currency', 'grace_period_days'])->toArray());

        if (isset($data['billing_anchor_day'])) {
            $subscription->billing_anchor_day = $data['billing_anchor_day'];
        }

        $subscription->updated_by = $actor->id;
        $subscription->save();

        return $subscription;
    }

    public function recordPayment(PlatformSubscriptionInvoice $invoice, array $data, User $actor): PlatformSubscriptionPayment
    {
        return DB::transaction(function () use ($invoice, $data, $actor) {
            $invoice = PlatformSubscriptionInvoice::where('id', $invoice->id)->lockForUpdate()->firstOrFail();
            $subscription = PlatformSubscription::where('id', $invoice->subscription_id)->lockForUpdate()->firstOrFail();
            $company = Company::where('id', $invoice->company_id)->lockForUpdate()->firstOrFail();

            $payment = new PlatformSubscriptionPayment([
                'amount' => $data['amount'],
                'currency' => $invoice->currency,
                'payment_date' => $data['payment_date'] ?? now()->toDateString(),
                'payment_method' => $data['payment_method'],
                'reference_number' => $data['reference_number'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);
            $payment->invoice_id = $invoice->id;
            $payment->company_id = $invoice->company_id;
            $payment->recorded_by = $actor->id;
            $payment->save();

            $invoice->status = SubscriptionInvoiceStatus::Paid;
            $invoice->paid_at = now();
            $invoice->save();

            if ($subscription->status !== SubscriptionStatus::Cancelled) {
                $subscription->status = SubscriptionStatus::Active;
                $subscription->suspended_at = null;
                $subscription->save();
            }

            if ($company->status === CompanyStatus::Suspended && $company->suspension_reason === 'non_payment') {
                $company->status = CompanyStatus::Active;
                $company->suspension_reason = null;
                $company->save();
            }

            return $payment->load('recordedBy');
        });
    }

    /** Capped at day 28 - deliberately avoids every month-length edge case (Feb 30, etc.) rather than handling them. */
    public function nextAnchorDate(Carbon $from, int $anchorDay): Carbon
    {
        $anchorDay = min($anchorDay, 28);
        $candidate = $from->copy()->day($anchorDay);

        return $candidate->lte($from) ? $candidate->addMonthNoOverflow() : $candidate;
    }

    public function createInvoice(PlatformSubscription $subscription, Carbon $periodStart, Carbon $periodEnd): PlatformSubscriptionInvoice
    {
        $invoice = new PlatformSubscriptionInvoice([
            'period_start' => $periodStart->toDateString(),
            'period_end' => $periodEnd->toDateString(),
            'amount' => $subscription->monthly_fee,
            'currency' => $subscription->currency,
            'due_date' => $periodStart->toDateString(),
        ]);
        $invoice->subscription_id = $subscription->id;
        $invoice->company_id = $subscription->company_id;
        $invoice->invoice_number = $this->nextInvoiceNumber();
        $invoice->status = SubscriptionInvoiceStatus::Pending;
        $invoice->save();

        return $invoice;
    }

    private function nextInvoiceNumber(): string
    {
        $prefix = 'SUB-'.now()->year.'-';
        
        $latestInvoice = PlatformSubscriptionInvoice::where('invoice_number', 'like', $prefix.'%')
            ->orderBy('invoice_number', 'desc')
            ->first();

        if (!$latestInvoice) {
            return $prefix . '00001';
        }

        $lastNumber = (int) str_replace($prefix, '', $latestInvoice->invoice_number);
        return $prefix . str_pad((string) ($lastNumber + 1), 5, '0', STR_PAD_LEFT);
    }
}