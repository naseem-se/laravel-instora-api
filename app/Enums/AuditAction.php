<?php

namespace App\Enums;

enum AuditAction: string
{
    case Login = 'user.login';
    case LoginFailed = 'user.login_failed';
    case Logout = 'user.logout';
    case PasswordReset = 'user.password_reset';
    case CompanyCreated = 'company.created';
    case CompanyUpdated = 'company.updated';
    case CompanyDeleted = 'company.deleted';
    case UserCreated = 'user.created';
    case UserUpdated = 'user.updated';
    case CustomerCreated = 'customer.created';
    case CustomerUpdated = 'customer.updated';
    case CustomerDeleted = 'customer.deleted';
    case InstallmentPlanCreated = 'installment_plan.created';
    case InstallmentPlanApproved = 'installment_plan.approved';
    case InstallmentPlanCancelled = 'installment_plan.cancelled';
    case InstallmentPlanSettled = 'installment_plan.settled';
    case InstallmentPlanDeleted = 'installment_plan.deleted';
    case LateFeeWaived = 'installment.late_fee_waived';
    case PaymentCreated = 'payment.created';
    case PaymentReversed = 'payment.reversed';
    case SaleCreated = 'sale.created';
    case SaleCancelled = 'sale.cancelled';
    case SaleReturned = 'sale.returned';
    case NotificationRetried = 'notification.retried';
    case WhatsAppProviderConfigured = 'whatsapp_provider.configured';
    case WhatsAppProviderUpdated = 'whatsapp_provider.updated';
    case WhatsAppProviderDeleted = 'whatsapp_provider.deleted';
    case WhatsAppTestMessageSent = 'whatsapp_provider.test_message_sent';
    case WhatsAppAccessChanged = 'whatsapp_access.changed';
}