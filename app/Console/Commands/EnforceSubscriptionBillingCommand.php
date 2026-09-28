<?php

namespace App\Console\Commands;

use App\Enums\CompanyStatus;
use App\Enums\SubscriptionInvoiceStatus;
use App\Enums\SubscriptionStatus;
use App\Models\Company;
use App\Models\PlatformSubscription;
use App\Models\PlatformSubscriptionInvoice;
use App\Services\SubscriptionService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class EnforceSubscriptionBillingCommand extends Command
{
    protected $signature = 'subscriptions:enforce';

    protected $description = 'Generates due subscription invoices, marks overdue ones, and auto-suspends companies whose grace period has lapsed.';

    public function __construct(private readonly SubscriptionService $subscriptions)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $this->info('Generated '.$this->generateDueInvoices().' subscription invoice(s).');
        $this->info('Marked '.$this->markOverdueInvoices().' invoice(s) overdue.');
        $this->info('Auto-suspended '.$this->suspendLapsedCompanies().' company/companies for non-payment.');

        return self::SUCCESS;
    }

    private function generateDueInvoices(): int
    {
        $count = 0;

        PlatformSubscription::whereIn('status', [SubscriptionStatus::Active, SubscriptionStatus::Grace])
            ->where('current_period_end', '<=', now()->toDateString())
            ->chunkById(100, function ($subscriptions) use (&$count) {
                foreach ($subscriptions as $subscription) {
                    /** @var PlatformSubscription $subscription */
                    $periodStart = Carbon::parse($subscription->current_period_end);
                    $periodEnd = $this->subscriptions->nextAnchorDate(
                        $periodStart->copy()->addDay(),
                        $subscription->billing_anchor_day,
                    );

                    $this->subscriptions->createInvoice($subscription, $periodStart, $periodEnd);

                    $subscription->setAttribute('current_period_start', $periodStart->toDateString());
                    $subscription->setAttribute('current_period_end', $periodEnd->toDateString());
                    $subscription->save();

                    $count++;
                }
            });

        return $count;
    }

    private function markOverdueInvoices(): int
    {
        return PlatformSubscriptionInvoice::where('status', SubscriptionInvoiceStatus::Pending)
            ->where('due_date', '<', now()->toDateString())
            ->update(['status' => SubscriptionInvoiceStatus::Overdue]);
    }

    private function suspendLapsedCompanies(): int
    {
        $suspended = 0;

        PlatformSubscription::whereIn('status', [SubscriptionStatus::Active, SubscriptionStatus::Grace])
            ->chunkById(100, function ($subscriptions) use (&$suspended) {
                foreach ($subscriptions as $subscription) {
                    /** @var PlatformSubscription $subscription */
                    $overdueInvoice = PlatformSubscriptionInvoice::where('subscription_id', $subscription->id)
                        ->where('status', SubscriptionInvoiceStatus::Overdue)
                        ->orderBy('due_date')
                        ->first();

                    if (! $overdueInvoice) {
                        continue;
                    }

                    $daysOverdue = now()->startOfDay()->diffInDays(Carbon::parse($overdueInvoice->due_date)->startOfDay());

                    if ($daysOverdue <= $subscription->grace_period_days) {
                        // Still inside the window - flag it but leave the
                        // company's operational status untouched.
                        if ($subscription->status !== SubscriptionStatus::Grace) {
                            $subscription->status = SubscriptionStatus::Grace;
                            $subscription->save();
                        }

                        continue;
                    }

                    $subscription->status = SubscriptionStatus::Suspended;
                    $subscription->setAttribute('suspended_at', now());
                    $subscription->save();

                    $company = Company::find($subscription->company_id);

                    if ($company && $company->status === CompanyStatus::Active) {
                        $company->status = CompanyStatus::Suspended;
                        $company->suspension_reason = 'non_payment';
                        $company->save();
                        $suspended++;
                    }
                }
            });

        return $suspended;
    }
}