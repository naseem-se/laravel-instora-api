<?php

namespace App\Support;

use App\Enums\NotificationChannel;
use App\Enums\NotificationType;

class DefaultNotificationTemplates
{
    /**
     * @return array<string, array{subject: string, body: string, whatsapp_body: string}>
     */
    public static function all(): array
    {
        return [
            NotificationType::InstallmentDueSoon->value => [
                'subject' => '{{company_name}} — upcoming installment for {{product_name}}',
                'body' => <<<'TXT'
                Dear {{customer_name}},

                This is a courtesy reminder from {{company_name}} that an installment on your account is coming due.

                Account summary
                Customer: {{customer_name}}
                Company: {{company_name}}
                Product: {{product_name}}
                Plan number: {{plan_number}}
                Installment: {{installment_number}} of {{number_of_installments}}
                Amount due: {{installment_amount}}
                Due date: {{due_date}}
                Paid to date: {{paid_amount}}
                Remaining balance: {{remaining_balance}}

                Kindly arrange payment on or before the due date to keep your account in good standing. If you have already paid, please disregard this message.

                Sincerely,
                {{company_name}}
                {{company_phone}}
                {{company_email}}
                TXT,
                'whatsapp_body' => <<<'TXT'
                *{{company_name}}*
                Hello {{customer_name}},

                Reminder: your installment for *{{product_name}}* is coming due.

                Customer: {{customer_name}}
                Plan: {{plan_number}}
                Installment: {{installment_number}} of {{number_of_installments}}
                Amount due: {{installment_amount}}
                Due date: {{due_date}}
                Remaining balance: {{remaining_balance}}

                Please pay on or before the due date. If already paid, you may ignore this message.

                — {{company_name}}
                TXT,
            ],
            NotificationType::InstallmentDueToday->value => [
                'subject' => '{{company_name}} — installment due today for {{product_name}}',
                'body' => <<<'TXT'
                Dear {{customer_name}},

                This is a reminder from {{company_name}} that your installment is due today.

                Account summary
                Customer: {{customer_name}}
                Company: {{company_name}}
                Product: {{product_name}}
                Plan number: {{plan_number}}
                Installment: {{installment_number}} of {{number_of_installments}}
                Amount due today: {{installment_amount}}
                Due date: {{due_date}}
                Paid to date: {{paid_amount}}
                Remaining balance: {{remaining_balance}}

                Please complete payment today to keep your account current. If you have already paid, please disregard this message.

                Sincerely,
                {{company_name}}
                {{company_phone}}
                {{company_email}}
                TXT,
                'whatsapp_body' => <<<'TXT'
                *{{company_name}}*
                Hello {{customer_name}},

                Your installment for *{{product_name}}* is *due today*.

                Customer: {{customer_name}}
                Plan: {{plan_number}}
                Installment: {{installment_number}} of {{number_of_installments}}
                Amount due: {{installment_amount}}
                Due date: {{due_date}}
                Remaining balance: {{remaining_balance}}

                Please complete payment today. If already paid, you may ignore this message.

                — {{company_name}}
                TXT,
            ],
            NotificationType::InstallmentOverdue->value => [
                'subject' => '{{company_name}} — overdue installment for {{product_name}}',
                'body' => <<<'TXT'
                Dear {{customer_name}},

                Our records show that an installment on your {{company_name}} account is now overdue. Please settle the outstanding amount at your earliest convenience.

                Account summary
                Customer: {{customer_name}}
                Company: {{company_name}}
                Product: {{product_name}}
                Plan number: {{plan_number}}
                Installment: {{installment_number}} of {{number_of_installments}}
                Overdue amount: {{installment_amount}}
                Original due date: {{due_date}}
                Paid to date: {{paid_amount}}
                Remaining balance: {{remaining_balance}}

                Prompt payment will help protect your account standing. If payment has already been made, please ignore this notice.

                Sincerely,
                {{company_name}}
                {{company_phone}}
                {{company_email}}
                TXT,
                'whatsapp_body' => <<<'TXT'
                *{{company_name}}*
                Hello {{customer_name}},

                An installment for *{{product_name}}* is now overdue.

                Customer: {{customer_name}}
                Plan: {{plan_number}}
                Installment: {{installment_number}} of {{number_of_installments}}
                Overdue amount: {{installment_amount}}
                Original due date: {{due_date}}
                Remaining balance: {{remaining_balance}}

                Please settle this amount as soon as possible. If already paid, you may ignore this notice.

                — {{company_name}}
                TXT,
            ],
            NotificationType::PaymentReceived->value => [
                'subject' => '{{company_name}} — payment received for {{product_name}}',
                'body' => <<<'TXT'
                Dear {{customer_name}},

                Thank you. {{company_name}} has received your payment and applied it to your installment account.

                Payment confirmation
                Customer: {{customer_name}}
                Company: {{company_name}}
                Product: {{product_name}}
                Plan number: {{plan_number}}
                Payment number: {{payment_number}}
                Invoice number: {{invoice_number}}
                Amount received: {{payment_amount}}
                Paid to date: {{paid_amount}}
                Remaining balance: {{remaining_balance}}

                Please retain this message as confirmation of your payment.

                Sincerely,
                {{company_name}}
                {{company_phone}}
                {{company_email}}
                TXT,
                'whatsapp_body' => <<<'TXT'
                *{{company_name}}*
                Hello {{customer_name}},

                We have received your payment for *{{product_name}}*.

                Customer: {{customer_name}}
                Plan: {{plan_number}}
                Payment no.: {{payment_number}}
                Invoice: {{invoice_number}}
                Amount received: {{payment_amount}}
                Remaining balance: {{remaining_balance}}

                Thank you. Please keep this as your receipt.

                — {{company_name}}
                TXT,
            ],
            NotificationType::PlanApproved->value => [
                'subject' => '{{company_name}} — installment plan approved for {{product_name}}',
                'body' => <<<'TXT'
                Dear {{customer_name}},

                We are pleased to confirm that your installment plan with {{company_name}} has been approved and is now active.

                Plan details
                Customer: {{customer_name}}
                Company: {{company_name}}
                Product: {{product_name}}
                Plan number: {{plan_number}}
                Number of installments: {{number_of_installments}}
                Total amount payable: {{total_amount}}
                Paid to date: {{paid_amount}}
                Remaining balance: {{remaining_balance}}

                Please keep to the scheduled due dates so your account remains in good standing. If you have any questions, quote your plan number when you contact us.

                Sincerely,
                {{company_name}}
                {{company_phone}}
                {{company_email}}
                TXT,
                'whatsapp_body' => <<<'TXT'
                *{{company_name}}*
                Hello {{customer_name}},

                Your installment plan for *{{product_name}}* has been *approved*.

                Customer: {{customer_name}}
                Plan: {{plan_number}}
                Installments: {{number_of_installments}}
                Total payable: {{total_amount}}
                Remaining balance: {{remaining_balance}}

                Please keep to the scheduled due dates. Quote your plan number if you need assistance.

                — {{company_name}}
                TXT,
            ],
            NotificationType::PlanSettled->value => [
                'subject' => '{{company_name}} — installment plan settled for {{product_name}}',
                'body' => <<<'TXT'
                Dear {{customer_name}},

                This confirms that your installment plan with {{company_name}} has been fully settled.

                Account summary
                Customer: {{customer_name}}
                Company: {{company_name}}
                Product: {{product_name}}
                Plan number: {{plan_number}}
                Total amount payable: {{total_amount}}
                Paid to date: {{paid_amount}}
                Remaining balance: {{remaining_balance}}

                Thank you for completing your payments. We appreciate your business and look forward to serving you again.

                Sincerely,
                {{company_name}}
                {{company_phone}}
                {{company_email}}
                TXT,
                'whatsapp_body' => <<<'TXT'
                *{{company_name}}*
                Hello {{customer_name}},

                Your installment plan for *{{product_name}}* is fully settled.

                Customer: {{customer_name}}
                Plan: {{plan_number}}
                Total payable: {{total_amount}}
                Remaining balance: {{remaining_balance}}

                Thank you for completing your payments.

                — {{company_name}}
                TXT,
            ],
            NotificationType::PaymentReversed->value => [
                'subject' => '{{company_name}} — payment reversal for {{product_name}}',
                'body' => <<<'TXT'
                Dear {{customer_name}},

                This is to inform you that a payment on your {{company_name}} account has been reversed. Your outstanding balance has been updated accordingly.

                Account summary
                Customer: {{customer_name}}
                Company: {{company_name}}
                Product: {{product_name}}
                Plan number: {{plan_number}}
                Reversed amount: {{payment_amount}}
                Paid to date: {{paid_amount}}
                Updated remaining balance: {{remaining_balance}}

                If you did not expect this change, or if you need to arrange a replacement payment, please contact us as soon as possible.

                Sincerely,
                {{company_name}}
                {{company_phone}}
                {{company_email}}
                TXT,
                'whatsapp_body' => <<<'TXT'
                *{{company_name}}*
                Hello {{customer_name}},

                A payment on your *{{product_name}}* account has been reversed.

                Customer: {{customer_name}}
                Plan: {{plan_number}}
                Reversed amount: {{payment_amount}}
                Updated remaining balance: {{remaining_balance}}

                If this was unexpected, please contact us as soon as possible.

                — {{company_name}}
                TXT,
            ],
        ];
    }

    public static function bodyFor(string $type, NotificationChannel $channel): ?string
    {
        $content = self::all()[$type] ?? null;

        if (!$content) {
            return null;
        }

        if ($channel === NotificationChannel::Whatsapp) {
            return $content['whatsapp_body'];
        }

        return $content['body'];
    }
}
