<?php

namespace App\Services;

use App\Enums\InstallmentPlanStatus;
use App\Enums\InstallmentStatus;
use App\Models\Installment;
use App\Models\InstallmentPlan;

class InstallmentOverdueStatusService
{
    public function markOverdue(): int
    {
        $today = now()->toDateString();
        $count = 0;

        Installment::whereIn('status', [InstallmentStatus::Pending, InstallmentStatus::Partial])
            ->where('due_date', '<', $today)
            ->chunkById(200, function ($installments) use (&$count) {
                foreach ($installments as $installment) {
                    /** @var Installment $installment */
                    $installment->status = InstallmentStatus::Overdue;
                    $installment->save();
                    $count++;
                }
            });

        InstallmentPlan::where('status', InstallmentPlanStatus::Active)
            ->whereHas('installments', fn ($q) => $q->where('status', InstallmentStatus::Overdue))
            ->update(['status' => InstallmentPlanStatus::Overdue]);

        return $count;
    }
}