<?php

namespace App\Support;

use App\Models\Installment;
use App\Models\InstallmentPlan;
use App\Models\Payment;

class NotificationVariables
{
    /**
     * @return array<string, string>
     */
    public static function forPlan(InstallmentPlan $plan, array $extra = []): array
    {
        $plan->loadMissing(['company', 'product']);

        $currency = $plan->company?->currency ?? 'PKR';

        return array_merge([
            'product_name' => $plan->product?->name ?: 'your purchase',
            'plan_number' => (string) $plan->plan_number,
            'number_of_installments' => (string) $plan->number_of_installments,
            'total_amount' => Money::format($currency, $plan->total_amount),
            'paid_amount' => Money::format($currency, $plan->paid_amount),
            'remaining_balance' => Money::format($currency, $plan->remaining_amount),
        ], $extra);
    }

    /**
     * @return array<string, string>
     */
    public static function forInstallment(Installment $installment, array $extra = []): array
    {
        $installment->loadMissing(['installmentPlan.company', 'installmentPlan.product']);

        $plan = $installment->installmentPlan;
        $currency = $plan->company?->currency ?? 'PKR';

        return self::forPlan($plan, array_merge([
            'installment_number' => (string) $installment->installment_number,
            'installment_amount' => Money::format($currency, $installment->remaining_amount),
            'due_date' => $installment->due_date->toFormattedDateString(),
        ], $extra));
    }

    /**
     * @return array<string, string>
     */
    public static function forPayment(Payment $payment, InstallmentPlan $plan, array $extra = []): array
    {
        $payment->loadMissing('invoice');
        $currency = $plan->company?->currency ?? $payment->company?->currency ?? 'PKR';

        return self::forPlan($plan, array_merge([
            'payment_amount' => Money::format($currency, $payment->amount),
            'payment_number' => (string) $payment->payment_number,
            'invoice_number' => (string) ($payment->invoice?->invoice_number ?? $plan->invoice?->invoice_number ?? ''),
        ], $extra));
    }
}
