<?php

namespace App\Console\Commands;

use App\Enums\InstallmentPlanStatus;
use App\Enums\InstallmentStatus;
use App\Enums\NotificationChannel;
use App\Enums\NotificationType;
use App\Models\Installment;
use App\Services\InstallmentOverdueStatusService;
use App\Services\LateFeeService;
use App\Services\NotificationService;
use App\Support\NotificationVariables;
use Illuminate\Console\Command;

class SendInstallmentRemindersCommand extends Command
{
    protected $signature = 'reminders:send-installments';

    protected $description = 'Marks overdue installments/plans, applies accrued late fees, and dispatches due-date and overdue payment reminders.';

    private const REMINDER_OFFSETS = [-3, 0, 1, 7, 15];

    public function __construct(
        private readonly InstallmentOverdueStatusService $overdueStatus,
        private readonly LateFeeService $lateFees,
        private readonly NotificationService $notifications,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $flipped = $this->overdueStatus->markOverdue();
        $this->info("Marked {$flipped} installment(s) overdue.");

        $feesApplied = $this->lateFees->applyLateFees();
        $this->info("Applied late fees to {$feesApplied} installment(s).");

        $processed = 0;

        foreach (self::REMINDER_OFFSETS as $offset) {
            $targetDate = now()->addDays($offset)->toDateString();

            Installment::with(['installmentPlan.company', 'installmentPlan.product', 'customer'])
                ->whereIn('status', [InstallmentStatus::Pending, InstallmentStatus::Partial, InstallmentStatus::Overdue])
                ->whereDate('due_date', $targetDate)
                ->whereHas('installmentPlan', fn ($q) => $q->whereIn('status', [InstallmentPlanStatus::Active, InstallmentPlanStatus::Overdue]))
                ->chunkById(200, function ($installments) use ($offset, &$processed) {
                    foreach ($installments as $installment) {
                        $type = match (true) {
                            $offset < 0 => NotificationType::InstallmentDueSoon,
                            $offset === 0 => NotificationType::InstallmentDueToday,
                            default => NotificationType::InstallmentOverdue,
                        };

                        $log = $this->notifications->send(
                            companyId: $installment->company_id,
                            customerId: $installment->customer_id,
                            type: $type,
                            channel: NotificationChannel::Email,
                            variables: NotificationVariables::forInstallment($installment),
                            referenceType: 'installment',
                            referenceId: $installment->id,
                            distinguisher: (string) $offset,
                        );

                        if ($log) {
                            $processed++;
                        }
                    }
                });
        }

        $this->info("Processed {$processed} reminder(s).");

        return self::SUCCESS;
    }
}