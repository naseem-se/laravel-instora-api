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
        .items-table { width: 100%; border-collapse: collapse; margin-bottom: 16px; }
        .items-table th, .items-table td { border: 1px solid #e1e4e9; padding: 6px 8px; text-align: left; }
        .items-table th { background-color: #f5f6f8; }
        .text-right { text-align: right; }
        .totals-table { width: 260px; margin-left: auto; border-collapse: collapse; }
        .totals-table td { padding: 4px 8px; }
        .totals-table .total-row td { font-weight: bold; border-top: 1px solid #141b26; }
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
                @if($company->email)<div>{{ $company->email }}</div>@endif
            </td>
            <td class="doc-title">INVOICE</td>
        </tr>
    </table>

    <table class="meta-table">
        <tr>
            <td width="50%">
                <div class="label">Bill to</div>
                <div><strong>{{ $customer->name }}</strong></div>
                <div>{{ $customer->customer_number }}</div>
                @if($customer->phone)<div>{{ $customer->phone }}</div>@endif
                @if($customer->address)<div>{{ $customer->address }}</div>@endif
            </td>
            <td width="50%" class="text-right">
                <div><span class="label">Invoice number:</span> {{ $invoice->invoice_number }}</div>
                <div><span class="label">Invoice date:</span> {{ $invoice->invoice_date->format('d M Y') }}</div>
                @if($plan)<div><span class="label">Plan number:</span> {{ $plan->plan_number }}</div>@endif
                @if(isset($sale))<div><span class="label">Sale number:</span> {{ $sale->sale_number }}</div>@endif
                <div><span class="label">Status:</span> {{ ucfirst($invoice->status->value) }}</div>
            </td>
        </tr>
    </table>

    <table class="items-table">
        <thead>
            <tr>
                <th>Description</th>
                <th class="text-right">Qty</th>
                <th class="text-right">Unit price</th>
                <th class="text-right">Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach($invoice->items as $item)
            <tr>
                <td>{{ $item->description }}</td>
                <td class="text-right">{{ number_format($item->quantity, 2) }}</td>
                <td class="text-right">{{ $company->currency }} {{ number_format($item->unit_price, 2) }}</td>
                <td class="text-right">{{ $company->currency }} {{ number_format($item->total, 2) }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <table class="totals-table">
        <tr><td>Subtotal</td><td class="text-right">{{ $company->currency }} {{ number_format($invoice->subtotal, 2) }}</td></tr>
        @if($invoice->additional_charges > 0)
        <tr><td>Additional charges</td><td class="text-right">{{ $company->currency }} {{ number_format($invoice->additional_charges, 2) }}</td></tr>
        @endif
        @if($invoice->tax > 0)
        <tr><td>Tax</td><td class="text-right">{{ $company->currency }} {{ number_format($invoice->tax, 2) }}</td></tr>
        @endif
        @if($invoice->interest > 0)
        <tr><td>Financing charge</td><td class="text-right">{{ $company->currency }} {{ number_format($invoice->interest, 2) }}</td></tr>
        @endif
        @if($invoice->discount > 0)
        <tr><td>Discount</td><td class="text-right">-{{ $company->currency }} {{ number_format($invoice->discount, 2) }}</td></tr>
        @endif
        <tr class="total-row"><td>Total</td><td class="text-right">{{ $company->currency }} {{ number_format($invoice->total_amount, 2) }}</td></tr>
        <tr><td>Paid</td><td class="text-right">{{ $company->currency }} {{ number_format($invoice->paid_amount, 2) }}</td></tr>
        <tr><td>Balance due</td><td class="text-right">{{ $company->currency }} {{ number_format($invoice->balance_amount, 2) }}</td></tr>
    </table>

    <div class="footer">
        @if($plan)
        This invoice reflects the total amount financed through installment plan {{ $plan->plan_number }} and is payable per the plan's installment schedule.
        @else
        Thank you for your purchase. This invoice is your proof of sale.
        @endif
    </div>
</body>
</html>