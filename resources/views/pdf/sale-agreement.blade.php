<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #17212b; line-height: 1.5; }
        h1 { font-size: 20px; margin: 0 0 4px; }
        h2 { font-size: 13px; margin: 22px 0 8px; border-bottom: 1px solid #ccd3dc; padding-bottom: 5px; }
        .muted { color: #586575; }
        .row { width: 100%; margin-top: 16px; }
        .row td { vertical-align: top; width: 50%; }
        table.schedule { width: 100%; border-collapse: collapse; margin-top: 10px; }
        .schedule th, .schedule td { border: 1px solid #ccd3dc; padding: 6px 8px; text-align: left; }
        .schedule th { background: #f1f4f7; }
        .right { text-align: right !important; }
        .signatures { margin-top: 54px; width: 100%; }
        .signatures td { width: 50%; padding-right: 36px; }
        .line { margin-top: 42px; border-top: 1px solid #586575; padding-top: 5px; }
    </style>
</head>
<body>
    <h1>Installment Sale Agreement</h1>
    <div class="muted">{{ $company->name }} · Agreement {{ $sale->sale_number }}</div>

    <table class="row"><tr>
        <td><strong>Seller</strong><br>{{ $company->name }}<br>{{ $company->address }}<br>{{ $company->phone }}</td>
        <td><strong>Customer</strong><br>{{ $customer->name }}<br>Customer No: {{ $customer->customer_number }}<br>{{ $customer->address }}<br>{{ $customer->phone }}</td>
    </tr></table>

    <h2>Sale details</h2>
    <p>Sale: {{ $sale->sale_number }}<br>Plan: {{ $plan->plan_number }}<br>Agreement date: {{ optional($sale->sold_at)->format('d M Y') ?? now()->format('d M Y') }}</p>
    <table class="schedule">
        <thead><tr><th>Product</th><th>SKU</th><th class="right">Qty</th><th class="right">Unit price</th><th class="right">Line total</th></tr></thead>
        <tbody>@foreach($sale->items as $item)<tr>
            <td>{{ $item->product_name }}</td><td>{{ $item->sku ?? '—' }}</td><td class="right">{{ $item->quantity }}</td>
            <td class="right">{{ $company->currency }} {{ number_format($item->unit_price, 2) }}</td>
            <td class="right">{{ $company->currency }} {{ number_format($item->line_total, 2) }}</td>
        </tr>@endforeach</tbody>
    </table>

    <h2>Payment terms</h2>
    <table class="schedule"><tbody>
        <tr><th>Sale total</th><td>{{ $company->currency }} {{ number_format($sale->total_amount, 2) }}</td><th>Down payment</th><td>{{ $company->currency }} {{ number_format($sale->down_payment, 2) }}</td></tr>
        <tr><th>Financed amount</th><td>{{ $company->currency }} {{ number_format($plan->financed_amount, 2) }}</td><th>Finance charge</th><td>{{ $company->currency }} {{ number_format($plan->interest_amount, 2) }}</td></tr>
        <tr><th>Total payable</th><td>{{ $company->currency }} {{ number_format($plan->total_amount + $sale->down_payment, 2) }}</td><th>Frequency</th><td>{{ ucfirst($plan->installment_frequency->value) }}</td></tr>
        <tr><th>Installment amount</th><td>{{ $company->currency }} {{ number_format($plan->installment_amount, 2) }}</td><th>Number of payments</th><td>{{ $plan->number_of_installments }}</td></tr>
    </tbody></table>

    <p>The customer agrees to pay each scheduled installment by its due date. Any applicable late charges are governed by the plan terms and applicable law. The seller and customer acknowledge the sale and payment schedule shown in this agreement.</p>

    <table class="schedule"><thead><tr><th>#</th><th>Due date</th><th class="right">Amount</th></tr></thead><tbody>
        @foreach($plan->installments as $installment)<tr><td>{{ $installment->installment_number }}</td><td>{{ $installment->due_date->format('d M Y') }}</td><td class="right">{{ $company->currency }} {{ number_format($installment->scheduled_amount, 2) }}</td></tr>@endforeach
    </tbody></table>

    <table class="signatures"><tr><td><div class="line">Customer signature / thumbprint</div></td><td><div class="line">For {{ $company->name }}</div></td></tr></table>
    <p class="muted">Printed {{ now()->format('d M Y H:i') }} · Keep this agreement with your invoice.</p>
</body>
</html>