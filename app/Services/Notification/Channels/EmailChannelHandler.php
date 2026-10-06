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
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Mail;
use Throwable;

class EmailChannelHandler implements NotificationChannelHandler
{
    public function send(NotificationLog $log): NotificationSendResult
    {
        try {
            $log->loadMissing('company');
            $companyName = $log->company?->name ?? 'Account notice';
            $subject = $log->rendered_subject ?: $companyName;
            $html = $this->renderHtml($companyName, $subject, $log->rendered_body ?? '');

            $attachmentData = null;
            $attachmentName = null;

            if ($log->type === NotificationType::PlanApproved && $log->invoice_id) {
              $invoice = Invoice::with(['items', 'company'])->find($log->invoice_id);
              $plan = $invoice?->installment_plan_id
                ? InstallmentPlan::withTrashed()->find($invoice->installment_plan_id)
                : null;
              $customer = $invoice?->customer_id ? Customer::withTrashed()->find($invoice->customer_id) : null;

              if ($invoice && $plan && $customer && $invoice->company) {
                $pdf = Pdf::loadView('pdf.invoice', [
                  'invoice' => $invoice,
                  'plan' => $plan,
                  'customer' => $customer,
                  'company' => $invoice->company,
                ]);
                $attachmentData = $pdf->output();
                $attachmentName = "invoice_{$invoice->invoice_number}.pdf";
              }

              if (! $attachmentData) {
                throw new \RuntimeException('The approved installment invoice could not be prepared.');
              }
            }

            if (! $attachmentData && $log->type === NotificationType::PaymentReceived && $log->payment_id) {
                $payment = Payment::with([
                    'installmentPlan.customer',
                    'installmentPlan.company',
                    'installmentPlan.installments',
                    'installmentPlan.payments',
                    'installmentPlan.invoice.items'
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
                    $attachmentData = $pdf->output();
                    $attachmentName = "statement_{$plan->plan_number}.pdf";
                }
            }

            Mail::html($html, function ($message) use ($log, $subject, $attachmentData, $attachmentName) {
                $message->to($log->recipient)->subject($subject);
                
                if ($attachmentData && $attachmentName) {
                    $message->attachData($attachmentData, $attachmentName, [
                        'mime' => 'application/pdf',
                    ]);
                }
            });

            return NotificationSendResult::sent();
        } catch (Throwable $e) {
            report($e);
            return NotificationSendResult::failed('EMAIL_SEND_FAILED', 'The email could not be sent.', retryable: true);
        }
    }

    private function renderHtml(string $companyName, string $subject, string $body): string
    {
        $escapedCompany = e($companyName);
        $escapedSubject = e($subject);
        $escapedBody = $this->formatBodyHtml($body);

        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>{$escapedSubject}</title>
</head>
<body style="margin:0;padding:0;background:#f3f4f6;">
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f3f4f6;padding:32px 12px;">
    <tr>
      <td align="center">
        <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;background:#ffffff;border:1px solid #e5e7eb;border-radius:8px;overflow:hidden;">
          <tr>
            <td style="background:#1e3a5f;color:#ffffff;padding:24px 32px;font-family:Arial,Helvetica,sans-serif;">
              <div style="font-size:18px;font-weight:700;letter-spacing:0.03em;">{$escapedCompany}</div>
              <div style="margin-top:6px;font-size:13px;color:#dbe4f0;">Installment account notice</div>
            </td>
          </tr>
          <tr>
            <td style="padding:32px;color:#1f2937;font-size:15px;line-height:1.7;font-family:Arial,Helvetica,sans-serif;">
              {$escapedBody}
            </td>
          </tr>
          <tr>
            <td style="padding:16px 32px 28px;border-top:1px solid #e5e7eb;color:#6b7280;font-size:12px;font-family:Arial,Helvetica,sans-serif;line-height:1.5;">
              This is an automated message from {$escapedCompany}. Please do not reply directly to this email.
            </td>
          </tr>
        </table>
      </td>
    </tr>
  </table>
</body>
</html>
HTML;
    }

    private function formatBodyHtml(string $body): string
    {
        $lines = preg_split("/\r\n|\n|\r/", $body) ?: [];
        $html = [];
        $inDetails = false;

        $closeDetails = function () use (&$html, &$inDetails): void {
            if ($inDetails) {
                $html[] = '</table>';
                $inDetails = false;
            }
        };

        foreach ($lines as $line) {
            $trimmed = trim($line);

            if ($trimmed === '') {
                $closeDetails();
                continue;
            }

            if (preg_match('/^(?:•\s*)?([^:]{2,40}):\s+(.+)$/u', $trimmed, $match)) {
                if (! $inDetails) {
                    $html[] = '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:16px 0;border:1px solid #e5e7eb;border-radius:6px;overflow:hidden;">';
                    $inDetails = true;
                }

                $label = e($match[1]);
                $value = e($match[2]);
                $html[] = '<tr>'
                    .'<td style="width:42%;padding:9px 14px;background:#f8fafc;color:#64748b;font-size:13px;border-bottom:1px solid #eef2f7;">'.$label.'</td>'
                    .'<td style="padding:9px 14px;color:#111827;font-size:13px;font-weight:600;border-bottom:1px solid #eef2f7;">'.$value.'</td>'
                    .'</tr>';

                continue;
            }

            $closeDetails();
            $html[] = '<p style="margin:0 0 12px;">'.e($trimmed).'</p>';
        }

        $closeDetails();

        return implode('', $html);
    }
}