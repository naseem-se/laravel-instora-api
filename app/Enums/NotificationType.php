<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

enum NotificationType: string
{
    use HasValues;

    case InstallmentDueSoon = 'installment_due_soon';
    case InstallmentDueToday = 'installment_due_today';
    case InstallmentOverdue = 'installment_overdue';
    case PaymentReceived = 'payment_received';
    case PlanApproved = 'plan_approved';
    // Unused until the Settlement phase exists - defined now so the schema
    // and preference logic don't need to change when it does.
    case PlanSettled = 'plan_settled';
    case PaymentReversed = 'payment_reversed';
}