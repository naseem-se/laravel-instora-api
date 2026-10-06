<?php

namespace Tests\Feature;

use App\Enums\CompanyStatus;
use App\Enums\SubscriptionInvoiceStatus;
use App\Enums\SubscriptionStatus;
use App\Models\Company;
use App\Models\PlatformSubscription;
use App\Models\PlatformSubscriptionInvoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EnforceSubscriptionBillingCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_overdue_invoice_immediately_suspends_company_regardless_of_grace_period(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 7)->setTime(7, 0));

        $company = Company::create([
            'name' => 'Overdue Subscription Test',
            'code' => 'overdue-subscription-test',
        ]);

        $subscription = new PlatformSubscription([
            'monthly_fee' => 2000,
            'currency' => 'PKR',
            'billing_anchor_day' => 1,
            'grace_period_days' => 10,
            'current_period_start' => '2026-10-01',
            'current_period_end' => '2026-11-01',
        ]);
        $subscription->company_id = $company->id;
        $subscription->status = SubscriptionStatus::Active;
        $subscription->save();

        $invoice = new PlatformSubscriptionInvoice([
            'period_start' => '2026-10-01',
            'period_end' => '2026-11-01',
            'amount' => 2000,
            'currency' => 'PKR',
            'due_date' => '2026-10-06',
        ]);
        $invoice->subscription_id = $subscription->id;
        $invoice->company_id = $company->id;
        $invoice->invoice_number = 'SUB-TEST-00001';
        $invoice->status = SubscriptionInvoiceStatus::Pending;
        $invoice->save();

        $this->artisan('subscriptions:enforce')->assertExitCode(0);

        $this->assertSame(SubscriptionInvoiceStatus::Overdue, $invoice->fresh()->status);
        $this->assertSame(SubscriptionStatus::Suspended, $subscription->fresh()->status);
        $this->assertSame(CompanyStatus::Suspended, $company->fresh()->status);
        $this->assertSame('non_payment', $company->fresh()->suspension_reason);
    }
}