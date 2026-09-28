<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #141b26; margin: 0; padding: 0; }
        .page-wrapper { padding: 20px 24px; }

        /* Header */
        .header { width: 100%; margin-bottom: 20px; border-bottom: 3px solid #1e3a5f; padding-bottom: 16px; }
        .header-table { width: 100%; }
        .header-table td { vertical-align: top; }
        .company-name { font-size: 20px; font-weight: bold; color: #1e3a5f; letter-spacing: 0.5px; }
        .company-detail { font-size: 10px; color: #5b6472; margin-top: 2px; }
        .doc-title { font-size: 22px; font-weight: bold; text-align: right; color: #1e3a5f; letter-spacing: 1px; }
        .doc-subtitle { font-size: 10px; text-align: right; color: #5b6472; margin-top: 4px; }

        /* Meta info */
        .meta-section { width: 100%; margin-bottom: 18px; }
        .meta-table { width: 100%; }
        .meta-table td { vertical-align: top; padding: 0; }
        .meta-block { background: #f8fafc; border: 1px solid #e1e4e9; border-radius: 4px; padding: 10px 14px; }
        .meta-label { font-size: 9px; font-weight: bold; text-transform: uppercase; color: #8592a3; letter-spacing: 0.8px; margin-bottom: 4px; }
        .meta-value { font-size: 12px; font-weight: bold; color: #141b26; }
        .meta-sub { font-size: 10px; color: #5b6472; margin-top: 2px; }

        /* Summary Cards */
        .summary-bar { width: 100%; margin-bottom: 18px; }
        .summary-table { width: 100%; border-collapse: collapse; }
        .summary-cell { background: #f0f5ff; border: 1px solid #d0ddf0; text-align: center; padding: 10px 6px; }
        .summary-cell.highlight { background: #1e3a5f; color: #ffffff; }
        .summary-cell .card-label { font-size: 8px; text-transform: uppercase; letter-spacing: 0.6px; color: #5b6472; margin-bottom: 4px; }
        .summary-cell.highlight .card-label { color: #bdd1ec; }
        .summary-cell .card-value { font-size: 14px; font-weight: bold; }
        .summary-cell.highlight .card-value { color: #ffffff; }

        /* Invoice section */
        .section-title { font-size: 13px; font-weight: bold; color: #1e3a5f; margin: 18px 0 8px 0; padding-bottom: 4px; border-bottom: 1px solid #d0ddf0; }

        .invoice-table { width: 100%; border-collapse: collapse; margin-bottom: 12px; }
        .invoice-table th { background: #1e3a5f; color: #ffffff; font-size: 9px; text-transform: uppercase; letter-spacing: 0.5px; padding: 7px 8px; text-align: left; }
        .invoice-table th.text-right { text-align: right; }
        .invoice-table td { padding: 6px 8px; border-bottom: 1px solid #e8ecf1; font-size: 10px; }
        .invoice-table tr:nth-child(even) td { background: #fafbfc; }

        /* Totals */
        .totals-table { width: 240px; margin-left: auto; border-collapse: collapse; margin-bottom: 14px; }
        .totals-table td { padding: 4px 8px; font-size: 10px; }
        .totals-table .total-row td { font-weight: bold; border-top: 2px solid #1e3a5f; font-size: 12px; padding-top: 6px; }

        /* Payment history table */
        .payment-table { width: 100%; border-collapse: collapse; margin-bottom: 12px; }
        .payment-table th { background: #2d5a3d; color: #ffffff; font-size: 9px; text-transform: uppercase; letter-spacing: 0.5px; padding: 7px 8px; text-align: left; }
        .payment-table th.text-right { text-align: right; }
        .payment-table td { padding: 6px 8px; border-bottom: 1px solid #e8ecf1; font-size: 10px; }
        .payment-table tr:nth-child(even) td { background: #fafbfc; }

        /* Installment schedule table */
        .schedule-table { width: 100%; border-collapse: collapse; margin-bottom: 12px; }
        .schedule-table th { background: #4a3d6b; color: #ffffff; font-size: 9px; text-transform: uppercase; letter-spacing: 0.5px; padding: 7px 8px; text-align: left; }
        .schedule-table th.text-right { text-align: right; }
        .schedule-table td { padding: 6px 8px; border-bottom: 1px solid #e8ecf1; font-size: 10px; }
        .schedule-table tr:nth-child(even) td { background: #fafbfc; }

        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .bold { font-weight: bold; }
        .text-success { color: #1b7c3e; }
        .text-danger { color: #b3261e; }
        .text-warning { color: #c47600; }
        .text-muted { color: #8592a3; }

        .status-badge { display: inline-block; padding: 2px 8px; border-radius: 10px; font-size: 9px; font-weight: bold; text-transform: uppercase; letter-spacing: 0.5px; }
        .status-paid { background: #dcfce7; color: #166534; }
        .status-pending { background: #f3f4f6; color: #6b7280; }
        .status-overdue { background: #fef2f2; color: #991b1b; }
        .status-partial { background: #fef9c3; color: #854d0e; }
        .status-active { background: #dbeafe; color: #1e40af; }
        .status-cancelled { background: #f3f4f6; color: #6b7280; }
        .status-completed { background: #dcfce7; color: #166534; }
        .status-reversed { background: #fef2f2; color: #991b1b; }
        .status-settled { background: #e0e7ff; color: #3730a3; }

        /* Balance box */
        .balance-box { background: #f0f5ff; border: 2px solid #1e3a5f; border-radius: 6px; padding: 14px 18px; margin-top: 14px; }
        .balance-box-table { width: 100%; }
        .balance-label { font-size: 11px; color: #5b6472; }
        .balance-value { font-size: 18px; font-weight: bold; color: #1e3a5f; text-align: right; }

        /* Footer */
        .footer { margin-top: 24px; padding-top: 10px; border-top: 1px solid #e1e4e9; font-size: 9px; color: #8592a3; line-height: 1.5; }
        .footer-note { font-size: 10px; color: #5b6472; margin-bottom: 6px; }

        .page-break { page-break-before: always; }
    </style>
</head>
<body>
<div class="page-wrapper">
    {{-- ═══════════ HEADER ═══════════ --}}
    <div class="header">
        <table class="header-table">
            <tr>
                <td width="60%">
                    <div class="company-name">{{ $company->name }}</div>
                    @if($company->address)<div class="company-detail">{{ $company->address }}</div>@endif
                    @if($company->phone)<div class="company-detail">📞 {{ $company->phone }}</div>@endif
                    @if($company->email)<div class="company-detail">✉ {{ $company->email }}</div>@endif
                </td>
                <td width="40%">
                    <div class="doc-title">STATEMENT</div>
                    <div class="doc-subtitle">Installment Plan Details</div>
                    <div class="doc-subtitle">Generated: {{ now()->format('d M Y, h:i A') }}</div>
                </td>
            </tr>
        </table>
    </div>

    {{-- ═══════════ CUSTOMER & PLAN META ═══════════ --}}
    <table class="meta-table">
        <tr>
            <td width="48%">
                <div class="meta-block">
                    <div class="meta-label">Customer</div>
                    <div class="meta-value">{{ $customer->name }}</div>
                    <div class="meta-sub">{{ $customer->customer_number }}</div>
                    @if($customer->phone)<div class="meta-sub">{{ $customer->phone }}</div>@endif
                    @if($customer->address)<div class="meta-sub">{{ $customer->address }}</div>@endif
                </div>
            </td>
            <td width="4%">&nbsp;</td>
            <td width="48%">
                <div class="meta-block">
                    <div class="meta-label">Plan Details</div>
                    <div class="meta-value">{{ $plan->plan_number }}</div>
                    <div class="meta-sub">Status: <span class="status-badge status-{{ $plan->status->value }}">{{ ucfirst($plan->status->value) }}</span></div>
                    <div class="meta-sub">Period: {{ $plan->start_date->format('d M Y') }} — {{ $plan->end_date->format('d M Y') }}</div>
                    <div class="meta-sub">Frequency: {{ ucfirst($plan->installment_frequency->value) }}</div>
                </div>
            </td>
        </tr>
    </table>

    {{-- ═══════════ FINANCIAL SUMMARY ═══════════ --}}
    <table class="summary-table" style="margin-top: 14px;">
        <tr>
            <td class="summary-cell" width="20%">
                <div class="card-label">Principal</div>
                <div class="card-value">{{ $currency }} {{ number_format($plan->principal_amount, 2) }}</div>
            </td>
            <td class="summary-cell" width="20%">
                <div class="card-label">Down Payment</div>
                <div class="card-value">{{ $currency }} {{ number_format($plan->down_payment, 2) }}</div>
            </td>
            <td class="summary-cell" width="20%">
                <div class="card-label">Total Amount</div>
                <div class="card-value">{{ $currency }} {{ number_format($plan->total_amount, 2) }}</div>
            </td>
            <td class="summary-cell" width="20%">
                <div class="card-label">Paid</div>
                <div class="card-value text-success">{{ $currency }} {{ number_format($plan->paid_amount, 2) }}</div>
            </td>
            <td class="summary-cell highlight" width="20%">
                <div class="card-label">Balance Due</div>
                <div class="card-value">{{ $currency }} {{ number_format($plan->remaining_amount, 2) }}</div>
            </td>
        </tr>
    </table>

    {{-- ═══════════ INVOICE DETAILS ═══════════ --}}
    @if($invoice)
    <div class="section-title">📄 Invoice — {{ $invoice->invoice_number }}</div>
    <table class="invoice-table">
        <thead>
            <tr>
                <th>Description</th>
                <th class="text-right">Qty</th>
                <th class="text-right">Unit Price</th>
                <th class="text-right">Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach($invoice->items as $item)
            <tr>
                <td>{{ $item->description }}</td>
                <td class="text-right">{{ number_format($item->quantity, 2) }}</td>
                <td class="text-right">{{ $currency }} {{ number_format($item->unit_price, 2) }}</td>
                <td class="text-right">{{ $currency }} {{ number_format($item->total, 2) }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <table class="totals-table">
        <tr><td>Subtotal</td><td class="text-right">{{ $currency }} {{ number_format($invoice->subtotal, 2) }}</td></tr>
        @if($invoice->interest > 0)
        <tr><td>Financing charge</td><td class="text-right">{{ $currency }} {{ number_format($invoice->interest, 2) }}</td></tr>
        @endif
        @if($invoice->discount > 0)
        <tr><td>Discount</td><td class="text-right text-danger">-{{ $currency }} {{ number_format($invoice->discount, 2) }}</td></tr>
        @endif
        <tr class="total-row"><td>Invoice Total</td><td class="text-right">{{ $currency }} {{ number_format($invoice->total_amount, 2) }}</td></tr>
    </table>
    @endif

    {{-- ═══════════ PAYMENT HISTORY ═══════════ --}}
    <div class="section-title">💳 Payment History</div>
    @if($payments->isEmpty())
        <p class="text-muted" style="font-size: 10px; margin: 6px 0;">No payments recorded yet.</p>
    @else
    <table class="payment-table">
        <thead>
            <tr>
                <th>#</th>
                <th>Payment Number</th>
                <th>Date</th>
                <th>Method</th>
                <th>Reference</th>
                <th class="text-right">Amount</th>
                <th class="text-center">Status</th>
            </tr>
        </thead>
        <tbody>
            @php $paymentIndex = 0; $totalPaid = 0; @endphp
            @foreach($payments as $payment)
            @php
                $paymentIndex++;
                if ($payment->status->value === 'completed') {
                    $totalPaid += (float)$payment->amount;
                }
            @endphp
            <tr>
                <td class="bold">{{ $paymentIndex }}</td>
                <td>{{ $payment->payment_number }}</td>
                <td>{{ $payment->payment_date->format('d M Y') }}</td>
                <td>{{ ucfirst(str_replace('_', ' ', $payment->payment_method->value)) }}</td>
                <td>{{ $payment->reference_number ?? '—' }}</td>
                <td class="text-right bold">{{ $currency }} {{ number_format($payment->amount, 2) }}</td>
                <td class="text-center">
                    <span class="status-badge status-{{ $payment->status->value }}">{{ ucfirst($payment->status->value) }}</span>
                </td>
            </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr style="border-top: 2px solid #2d5a3d;">
                <td colspan="5" class="bold text-right" style="padding-top: 8px;">Total Payments</td>
                <td class="text-right bold text-success" style="padding-top: 8px;">{{ $currency }} {{ number_format($totalPaid, 2) }}</td>
                <td></td>
            </tr>
        </tfoot>
    </table>
    @endif

    {{-- ═══════════ INSTALLMENT SCHEDULE ═══════════ --}}
    <div class="section-title">📋 Installment Schedule</div>
    <table class="schedule-table">
        <thead>
            <tr>
                <th>#</th>
                <th>Due Date</th>
                <th class="text-right">Principal</th>
                <th class="text-right">Interest</th>
                <th class="text-right">Late Fee</th>
                <th class="text-right">Scheduled</th>
                <th class="text-right">Paid</th>
                <th class="text-right">Remaining</th>
                <th class="text-center">Status</th>
            </tr>
        </thead>
        <tbody>
            @foreach($installments as $inst)
            <tr>
                <td class="bold">{{ $inst->installment_number }}</td>
                <td>{{ $inst->due_date->format('d M Y') }}</td>
                <td class="text-right">{{ $currency }} {{ number_format($inst->principal_amount, 2) }}</td>
                <td class="text-right">{{ $currency }} {{ number_format($inst->interest_amount, 2) }}</td>
                <td class="text-right">{{ number_format($inst->late_fee_amount, 2) > 0 ? $currency . ' ' . number_format($inst->late_fee_amount, 2) : '—' }}</td>
                <td class="text-right">{{ $currency }} {{ number_format($inst->scheduled_amount, 2) }}</td>
                <td class="text-right text-success">{{ $currency }} {{ number_format($inst->paid_amount, 2) }}</td>
                <td class="text-right {{ (float)$inst->remaining_amount > 0 ? 'text-danger bold' : '' }}">{{ $currency }} {{ number_format($inst->remaining_amount, 2) }}</td>
                <td class="text-center">
                    <span class="status-badge status-{{ $inst->status->value }}">{{ ucfirst($inst->status->value) }}</span>
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>

    {{-- ═══════════ BALANCE BOX ═══════════ --}}
    <div class="balance-box">
        <table class="balance-box-table">
            <tr>
                <td width="50%">
                    <div class="balance-label">Total Plan Amount</div>
                    <div style="font-size: 14px; font-weight: bold; color: #141b26;">{{ $currency }} {{ number_format($plan->total_amount, 2) }}</div>
                </td>
                <td width="25%" class="text-center">
                    <div class="balance-label">Total Paid</div>
                    <div class="text-success" style="font-size: 14px; font-weight: bold;">{{ $currency }} {{ number_format($plan->paid_amount, 2) }}</div>
                </td>
                <td width="25%">
                    <div class="balance-label text-right">Outstanding Balance</div>
                    <div class="balance-value">{{ $currency }} {{ number_format($plan->remaining_amount, 2) }}</div>
                </td>
            </tr>
        </table>
    </div>

    {{-- ═══════════ FOOTER ═══════════ --}}
    <div class="footer">
        <div class="footer-note">
            This statement is a comprehensive record of installment plan <strong>{{ $plan->plan_number }}</strong>
            for <strong>{{ $customer->name }}</strong>, including all invoiced items, payments received, and scheduled installments.
        </div>
        <div>
            This document is computer-generated by {{ $company->name }} and does not require a signature.
            For any discrepancies, please contact us at {{ $company->phone ?? $company->email ?? '' }}.
        </div>
    </div>
</div>
</body>
</html>
