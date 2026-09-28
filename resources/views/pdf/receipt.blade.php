<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #141b26; }
        .header-table { width: 100%; margin-bottom: 24px; }
        .header-table td { vertical-align: top; }
        .company-name { font-size: 18px; font-weight: bold; }
        .doc-title { font-size: 20px; font-weight: bold; text-align: right; }
        .meta-table { width: 100%; margin-bottom: 20px; }
        .label { color: #5b6472; }
        .amount-box { border: 1px solid #e1e4e9; background-color: #f5f6f8; padding: 12px; margin-bottom: 20px; text-align: center; }
        .amount-value { font-size: 22px; font-weight: bold; }
        .items-table { width: 100%; border-collapse: collapse; margin-bottom: 16px; }
        .items-table th, .items-table td { border: 1px solid #e1e4e9; padding: 6px 8px; text-align: left; }
        .items-table th { background-color: #f5f6f8; }
        .text-right { text-align: right; }
        .status-reversed { color: #b3261e; font-weight: bold; }
        .footer { margin-top: 32px; font-size: 10px; color: #5b6472; }
    </style>
</head>
<body>
    <table class="header-table">
        <tr>
            <td>
                <div class="company-name">{{ $company->name }}</div>
                @if($company->address)<div>{{ $company->address }}</div>@endif
                @if($company->phone)<div>{{ $company->phone }}</div>@endif
            </td>
            <td class="doc-title">PAYMENT RECEIPT</td>
        </tr>
    </table>

    <table class="meta-table">
        <tr>
            <td width="50%">
                <div class="label">Received from</div>
                <div><strong>{{ $payment->customer->name }}</strong></div>
                <div>{{ $payment->customer->customer_number }}</div>
            </td>
            <td width="50%" class="text-right">
                <div><span class="label">Receipt number:</span> {{ $payment->payment_number }}</div>
                <div><span class="label">Date:</span> {{ $payment->payment_date->format('d M Y') }}</div>
                @if($payment->installmentPlan)
                <div><span class="label">Plan:</span> {{ $payment->installmentPlan->plan_number }}</div>
                @endif
                @if($payment->status->value === 'reversed')
                <div class="status-reversed">REVERSED</div>
                @endif
            </td>
        </tr>
    </table>

    <div class="amount-box">
        <div class="label">Amount received</div>
        <div class="amount-value">{{ $company->currency }} {{ number_format($payment->amount, 2) }}</div>
        <div>
            {{ ucfirst(str_replace('_', ' ', $payment->payment_method->value)) }}
            @if($payment->reference_number) &middot; Ref: {{ $payment->reference_number }} @endif
        </div>
    </div>

    @if($payment->allocations->isNotEmpty())
    <table class="items-table">
        <thead>
            <tr>
                <th>Installment</th>
                <th class="text-right">Principal</th>
                <th class="text-right">Interest</th>
                <th class="text-right">Late fee</th>
                <th class="text-right">Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach($payment->allocations as $allocation)
            <tr>
                <td>#{{ $allocation->installment?->installment_number }} due {{ $allocation->installment?->due_date?->format('d M Y') }}</td>
                <td class="text-right">{{ $company->currency }} {{ number_format($allocation->principal_amount, 2) }}</td>
                <td class="text-right">{{ $company->currency }} {{ number_format($allocation->interest_amount, 2) }}</td>
                <td class="text-right">{{ $company->currency }} {{ number_format($allocation->late_fee_amount, 2) }}</td>
                <td class="text-right">{{ $company->currency }} {{ number_format($allocation->allocated_amount, 2) }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @endif

    <div><span class="label">Received by:</span> {{ $payment->receivedBy->name }}</div>

    @if($payment->reversal)
    <div class="footer status-reversed">
        This payment was reversed on {{ $payment->reversal->reversed_at->format('d M Y') }}. Reason: {{ $payment->reversal->reason }}
    </div>
    @endif

    <div class="footer">
        This receipt is computer-generated and reflects the transaction as recorded in the system at the time of printing.
    </div>
</body>
</html>