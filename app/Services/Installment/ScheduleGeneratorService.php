<?php

namespace App\Services\Installment;

use App\Enums\InstallmentStatus;
use App\Models\Installment;
use App\Models\InstallmentPlan;
use App\Support\Money;
use Carbon\Carbon;

class ScheduleGeneratorService
{
    /**
     * Persists the full installment schedule for a plan. Called exactly
     * once, inside the same transaction as plan creation - see
     * InstallmentPlanService. There is no "regenerate schedule" path.
     *
     * @param list<Carbon> $dueDates
     */
    public function generate(InstallmentPlan $plan, InstallmentCalculation $calculation, array $dueDates): void
    {
        foreach ($calculation->installmentBreakdown as $index => $share) {
            $installment = new Installment();
            $installment->company_id = $plan->company_id;
            $installment->installment_plan_id = $plan->id;
            $installment->customer_id = $plan->customer_id;
            $installment->installment_number = $index + 1;
            $installment->setAttribute('due_date', $dueDates[$index]);
            $installment->setAttribute('principal_amount', Money::fromCents($share['principal_cents']));
            $installment->setAttribute('interest_amount', Money::fromCents($share['interest_cents']));
            $installment->setAttribute('scheduled_amount', Money::fromCents($share['scheduled_cents']));
            $installment->setAttribute('remaining_amount', Money::fromCents($share['scheduled_cents']));
            $installment->status = InstallmentStatus::Pending;
            $installment->save();
        }
    }
}