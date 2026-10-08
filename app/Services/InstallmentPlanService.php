<?php

namespace App\Services;

use App\Enums\AuditAction;
use App\Enums\FinancialChargeType;
use App\Enums\InstallmentFrequency;
use App\Enums\InstallmentPlanStatus;
use App\Enums\InvoiceStatus;
use App\Enums\LateFeeType;
use App\Exceptions\PlanNotApprovableException;
use App\Exceptions\PlanNotCancellableException;
use App\Exceptions\PlanNotDeletableException;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InstallmentPlan;
use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use App\Services\Installment\DueDateGenerator;
use App\Services\Installment\InstallmentCalculator;
use App\Services\Installment\ScheduleGeneratorService;
use App\Support\CompanyContext;
use App\Support\Money;
use App\Support\NotificationVariables;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Enums\NotificationChannel;
use App\Enums\NotificationType;
use App\Services\NotificationService;

class InstallmentPlanService
{
    public function __construct(
        private readonly InstallmentCalculator $calculator,
        private readonly DueDateGenerator $dueDateGenerator,
        private readonly ScheduleGeneratorService $scheduleGenerator,
        private readonly LedgerService $ledger,
        private readonly InvoiceService $invoices,
        private readonly NotificationService $notifications,
        private readonly AuditLogger $audit,
        private readonly PaymentService $payments,
    ) {}

    public function create(array $data, CompanyContext $context, User $actor): InstallmentPlan
    {
        $companyId = $context->requireCompanyId();

        return DB::transaction(function () use ($data, $companyId, $actor) {
            Company::where('id', $companyId)->lockForUpdate()->first();
            Customer::query()->where('company_id', $companyId)->whereKey($data['customer_id'])->firstOrFail();

            $chargeType = FinancialChargeType::from($data['financial_charge_type']);
            $lateFeeType = LateFeeType::from($data['late_fee_type']);
            $frequency = InstallmentFrequency::from($data['installment_frequency']);
            $numberOfInstallments = (int) $data['number_of_installments'];

            $product = null;
            if (! empty($data['product_id'])) {
                $product = Product::where('company_id', $companyId)
                    ->whereKey($data['product_id'])
                    ->where('status', 'active')
                    ->firstOrFail();
            }

            $principalCents = Money::toCents($data['principal_amount'] ?? $product?->cash_price);
            $downPaymentCents = Money::toCents($data['down_payment'] ?? 0);

            $calculation = $this->calculator->calculate(
                principalCents: $principalCents,
                downPaymentCents: $downPaymentCents,
                chargeType: $chargeType,
                interestRate: $chargeType === FinancialChargeType::None ? null : $data['interest_rate'],
                numberOfInstallments: $numberOfInstallments,
            );

            $customIntervalDays = $frequency === InstallmentFrequency::Custom
                ? (int) $data['custom_interval_days']
                : null;

            $dueDates = $this->dueDateGenerator->generate(
                Carbon::parse($data['start_date']),
                $frequency,
                $numberOfInstallments,
                $customIntervalDays,
            );

            $plan = new InstallmentPlan([
                'customer_id' => $data['customer_id'],
                'product_id' => $product?->id,
                'start_date' => $data['start_date'],
                'financial_charge_type' => $chargeType,
                'interest_type' => $chargeType === FinancialChargeType::None ? null : ($data['interest_type'] ?? 'flat'),
                'interest_rate' => $chargeType === FinancialChargeType::None ? null : $data['interest_rate'],
                'late_fee_type' => $lateFeeType,
                'late_fee_rate' => in_array($lateFeeType, [LateFeeType::Percentage, LateFeeType::PercentagePerDay], true)
                    ? $data['late_fee_rate'] : null,
                'late_fee_amount' => in_array($lateFeeType, [LateFeeType::Fixed, LateFeeType::PerDay], true)
                    ? $data['late_fee_amount'] : null,
                'maximum_late_fee' => $data['maximum_late_fee'] ?? null,
                'installment_frequency' => $frequency,
                'custom_interval_days' => $customIntervalDays,
                'number_of_installments' => $numberOfInstallments,
                'notes' => $data['notes'] ?? null,
            ]);

            $plan->company_id = $companyId;
            $plan->customer_id = $data['customer_id'];
            $plan->setAttribute('principal_amount', Money::fromCents($principalCents));
            $plan->setAttribute('down_payment', Money::fromCents($downPaymentCents));
            $plan->setAttribute('financed_amount', Money::fromCents($calculation->financedAmountCents));
            $plan->setAttribute('interest_amount', Money::fromCents($calculation->interestAmountCents));
            $plan->setAttribute('total_amount', Money::fromCents($calculation->totalAmountCents));
            $plan->setAttribute('installment_amount', Money::fromCents($calculation->installmentAmountCents));
            $plan->setAttribute('paid_amount', '0.00');
            $plan->setAttribute('remaining_amount', Money::fromCents($calculation->totalAmountCents));
            $plan->setAttribute('end_date', end($dueDates)->toDateString());
            $plan->status = InstallmentPlanStatus::Pending;
            $plan->created_by = $actor->id;
            $plan->plan_number = $this->nextPlanNumber($companyId);
            $plan->save();

            $this->scheduleGenerator->generate($plan, $calculation, $dueDates);

            $this->audit->log(
                AuditAction::InstallmentPlanCreated->value,
                entity: $plan,
                newValues: [
                    'plan_number' => $plan->plan_number,
                    'total_amount' => $plan->total_amount,
                    'number_of_installments' => $plan->number_of_installments,
                ],
                companyId: $companyId,
                userId: $actor->id,
            );

            return $plan;
        });
    }

    public function approve(InstallmentPlan $plan, User $actor): InstallmentPlan
    {
        if ($plan->status !== InstallmentPlanStatus::Pending) {
            throw new PlanNotApprovableException();
        }

        return DB::transaction(function () use ($plan, $actor) {
            Company::where('id', $plan->company_id)->lockForUpdate()->first();

            $plan->status = InstallmentPlanStatus::Active;
            $plan->approved_by = $actor->id;
            $plan->setAttribute('approved_at', now());
            $plan->save();

            // The customer now owes the full plan total - post it as a debit.
            // Every payment against this plan posts a matching credit.
            $sale = Sale::query()->where('installment_plan_id', $plan->id)->first();
            $this->ledger->record(
                companyId: $plan->company_id,
                customerId: $plan->customer_id,
                transactionType: $sale ? 'sale' : 'installment_plan',
                referenceType: $sale ? 'sale' : 'installment_plan',
                referenceId: $sale?->id ?? $plan->id,
                debitCents: $sale
                    ? Money::toCents($sale->total_amount) + Money::toCents($plan->interest_amount)
                    : Money::toCents($plan->total_amount),
                creditCents: 0,
                description: $sale ? "Installment sale {$sale->sale_number}" : "Installment plan {$plan->plan_number} approved",
                createdBy: $actor->id,
                installmentPlanId: $plan->id,
            );

            // Billing document, generated exactly once per plan - see the
            // Phase 9 decision notes on why this lives here and not in a
            // separate manual step.
            $invoice = $this->invoices->createForPlan($plan, $actor);

            if ($sale) {
                $sale->invoice_id = $invoice->id;
                $sale->status = 'completed';
                $sale->sold_at = now();
                $sale->save();

                $downPaymentCents = Money::toCents($sale->down_payment);
                if ($downPaymentCents > 0) {
                    $this->payments->recordSalePayment(
                        $sale,
                        $invoice,
                        $downPaymentCents,
                        \App\Enums\PaymentMethod::from($sale->payment_method),
                        $actor,
                        "Down payment for sale {$sale->sale_number}",
                    );
                }
            }

            $notificationVariables = NotificationVariables::forPlan($plan);
            if ($sale) {
                $currency = $plan->company?->currency ?? 'PKR';
                $notificationVariables = array_merge($notificationVariables, [
                    'total_amount' => Money::format($currency, Money::fromCents(
                        Money::toCents($sale->total_amount) + Money::toCents($plan->interest_amount)
                    )),
                    'paid_amount' => Money::format($currency, $sale->down_payment),
                    'remaining_balance' => Money::format($currency, $plan->total_amount),
                ]);
            }

            $this->notifications->sendViaWhatsAppOrEmail(
                companyId: $plan->company_id,
                customerId: $plan->customer_id,
                type: NotificationType::PlanApproved,
                variables: $notificationVariables,
                referenceType: 'invoice',
                referenceId: $invoice->id,
            );

            $this->audit->log(
                AuditAction::InstallmentPlanApproved->value,
                entity: $plan,
                newValues: ['status' => 'active', 'approved_by' => $actor->id],
                companyId: $plan->company_id,
                userId: $actor->id,
            );

            return $plan;
        });
    }

    public function delete(InstallmentPlan $plan, User $actor): void
    {
        DB::transaction(function () use ($plan, $actor) {
            $lockedPlan = InstallmentPlan::query()->whereKey($plan->id)->lockForUpdate()->firstOrFail();
            if ($lockedPlan->status !== InstallmentPlanStatus::Completed) {
                throw new PlanNotDeletableException();
            }

            $lockedPlan->delete();
            $this->audit->log(
                AuditAction::InstallmentPlanDeleted->value,
                entity: $lockedPlan,
                oldValues: ['plan_number' => $lockedPlan->plan_number, 'status' => $lockedPlan->status->value],
                companyId: $lockedPlan->company_id,
                userId: $actor->id,
            );
        });
    }

    public function cancel(InstallmentPlan $plan, User $actor, ?string $reason = null): InstallmentPlan
    {
        if (! in_array($plan->status, [InstallmentPlanStatus::Pending, InstallmentPlanStatus::Active, InstallmentPlanStatus::Overdue], true)) {
            throw new PlanNotCancellableException();
        }

        if (Money::toCents($plan->paid_amount) > 0) {
            throw new PlanNotCancellableException(
                'This plan has recorded payments and cannot be cancelled directly. Use a settlement instead.'
            );
        }

        return DB::transaction(function () use ($plan, $actor, $reason) {
            $wasActive = in_array($plan->status, [InstallmentPlanStatus::Active, InstallmentPlanStatus::Overdue], true);
            $currentStatus = $plan->status instanceof InstallmentPlanStatus
                ? $plan->status->value
                : (string) $plan->status;

            $plan->status = InstallmentPlanStatus::Cancelled;
            $plan->save();

            if ($wasActive) {
                $sale = Sale::query()->where('installment_plan_id', $plan->id)->first();
                if (! $sale) {
                    $this->ledger->record(
                        companyId: $plan->company_id,
                        customerId: $plan->customer_id,
                        transactionType: 'installment_plan_cancellation',
                        referenceType: 'installment_plan',
                        referenceId: $plan->id,
                        debitCents: 0,
                        creditCents: Money::toCents($plan->total_amount),
                        description: "Installment plan {$plan->plan_number} cancelled",
                        createdBy: $actor->id,
                        installmentPlanId: $plan->id,
                    );
                }

                $invoice = Invoice::query()
                    ->where('installment_plan_id', $plan->id)
                    ->first();
                if ($invoice) {
                    $invoice->status = InvoiceStatus::Cancelled;
                    $invoice->save();
                }
            }

            $this->audit->log(
                AuditAction::InstallmentPlanCancelled->value,
                entity: $plan,
                oldValues: ['status' => $currentStatus],
                newValues: ['status' => 'cancelled', 'reason' => $reason],
                companyId: $plan->company_id,
                userId: $actor->id,
            );

            return $plan;
        });
    }

    private function nextPlanNumber(int $companyId): string
    {
        $prefix = 'INS-'.now()->year.'-';

        $count = InstallmentPlan::withTrashed()
            ->where('company_id', $companyId)
            ->where('plan_number', 'like', $prefix.'%')
            ->count();

        return $prefix.str_pad((string) ($count + 1), 5, '0', STR_PAD_LEFT);
    }
}