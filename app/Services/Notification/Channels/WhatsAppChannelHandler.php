<?php

namespace App\Services\Notification\Channels;

use App\Enums\NotificationType;
use App\Models\Customer;
use App\Models\InstallmentPlan;
use App\Models\Invoice;
use App\Models\NotificationLog;
use App\Models\Payment;
use App\Services\Notification\Contracts\NotificationChannelHandler;
use App\Services\Notification\NotificationSendResult;
use App\Services\WhatsAppService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Log;

/**
 * Sends WhatsApp notifications. If the notification type has an associated
 * PDF (invoice for PlanApproved, statement for PaymentReceived), the PDF
 * is generated and sent as a document alongside the text message.
 */
class WhatsAppChannelHandler implements NotificationChannelHandler
{
    public function __construct(private readonly WhatsAppService $whatsapp) {}

    public function send(NotificationLog $log): NotificationSendResult
    {
        $template = $log->template;

        if ($template && $template->whatsapp_template_name) {
            $dispatch = $this->sendViaTemplate($log, $template);
        } else {
            $dispatch = $this->sendViaText($log);
        }

        if (! $dispatch->authorized) {
            return NotificationSendResult::skipped($dispatch->skipReason);
        }

        $result = $dispatch->sendResult;

        if (! $result->success) {
            return NotificationSendResult::failed($result->errorCode, $result->errorMessage, $result->retryable);
        }

        // After the text message succeeds, try to send the PDF document.
        $this->trySendPdfAttachment($log);

        return NotificationSendResult::sent($result->providerMessageId);
    }

    private function sendViaText(NotificationLog $log): \App\Services\WhatsApp\WhatsAppDispatchResult
    {
        return $this->whatsapp->sendText(
            notificationLogId: $log->id,
            companyId: $log->company_id,
            customerId: $log->customer_id,
            recipient: $log->recipient,
            text: $log->rendered_body ?? '',
        );
    }

    private function sendViaTemplate(NotificationLog $log, \App\Models\NotificationTemplate $template): \App\Services\WhatsApp\WhatsAppDispatchResult
    {
        $components = [];
        if ($log->rendered_body) {
            $components = [
                [
                    'type' => 'body',
                    'parameters' => [
                        ['type' => 'text', 'text' => $log->rendered_body],
                    ],
                ],
            ];
        }

        return $this->whatsapp->sendTemplate(
            notificationLogId: $log->id,
            companyId: $log->company_id,
            customerId: $log->customer_id,
            recipient: $log->recipient,
            templateName: $template->whatsapp_template_name,
            languageCode: $template->whatsapp_template_language ?? 'en',
            components: $components,
        );
    }

    /**
     * Generate and send the PDF attachment (invoice or statement) as a
     * WhatsApp document message — same PDFs that the email channel attaches.
     */
    private function trySendPdfAttachment(NotificationLog $log): void
    {
        try {
            $pdfData = null;
            $fileName = null;

            // PlanApproved → Invoice PDF
            if ($log->type === NotificationType::PlanApproved && $log->invoice_id) {
                $invoice = Invoice::with(['items', 'company'])->find($log->invoice_id);
                $plan = $invoice?->installment_plan_id
                    ? InstallmentPlan::withTrashed()->find($invoice->installment_plan_id)
                    : null;
                $customer = $invoice?->customer_id
                    ? Customer::withTrashed()->find($invoice->customer_id)
                    : null;

                if ($invoice && $plan && $customer && $invoice->company) {
                    $pdf = Pdf::loadView('pdf.invoice', [
                        'invoice' => $invoice,
                        'plan' => $plan,
                        'customer' => $customer,
                        'company' => $invoice->company,
                    ]);
                    $pdfData = $pdf->output();
                    $fileName = "invoice_{$invoice->invoice_number}.pdf";
                }
            }

            // PaymentReceived → Statement PDF
            if (! $pdfData && $log->type === NotificationType::PaymentReceived && $log->payment_id) {
                $payment = Payment::with([
                    'installmentPlan.customer',
                    'installmentPlan.company',
                    'installmentPlan.installments',
                    'installmentPlan.payments',
                    'installmentPlan.invoice.items',
                ])->find($log->payment_id);

                if ($payment && $payment->installmentPlan) {
                    $plan = $payment->installmentPlan;
                    $pdf = Pdf::loadView('pdf.statement', [
                        'plan' => $plan,
                        'customer' => $plan->customer,
                        'company' => $plan->company,
                        'invoice' => $plan->invoice,
                        'installments' => $plan->installments,
                        'payments' => $plan->payments,
                        'currency' => $plan->company->currency,
                    ]);
                    $pdfData = $pdf->output();
                    $fileName = "statement_{$plan->plan_number}.pdf";
                }
            }

            if (! $pdfData || ! $fileName) {
                return; // No PDF needed for this notification type.
            }

            // Save the PDF temporarily and send it as a WhatsApp document.
            $tempPath = storage_path("app/temp/{$fileName}");
            if (! is_dir(dirname($tempPath))) {
                mkdir(dirname($tempPath), 0755, true);
            }
            file_put_contents($tempPath, $pdfData);

            try {
                $this->whatsapp->sendDocument(
                    companyId: $log->company_id,
                    customerId: $log->customer_id,
                    recipient: $log->recipient,
                    filePath: $tempPath,
                    fileName: $fileName,
                    caption: $fileName,
                );
            } finally {
                // Always clean up the temp file.
                @unlink($tempPath);
            }
        } catch (\Throwable $e) {
            // Log but don't fail the notification — the text message already went through.
            Log::warning('WhatsApp: PDF attachment send failed.', [
                'notification_id' => $log->id,
                'type' => $log->type->value,
                'error' => $e->getMessage(),
            ]);
        }
    }
}