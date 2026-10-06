<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SaleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'sale_number' => $this->sale_number,
            'payment_type' => $this->payment_type,
            'status' => $this->status,
            'customer' => $this->whenLoaded('customer', fn () => [
                'id' => $this->customer?->id,
                'name' => $this->customer?->name,
                'customer_number' => $this->customer?->customer_number,
            ]),
            'items' => $this->whenLoaded('items', fn () => $this->items->map(fn ($item) => [
                'id' => $item->id,
                'product_id' => $item->product_id,
                'product_name' => $item->product_name,
                'sku' => $item->sku,
                'quantity' => $item->quantity,
                'unit_price' => $item->unit_price,
                'line_total' => $item->line_total,
                'warehouse' => $item->warehouse?->name,
            ])),
            'subtotal' => $this->subtotal,
            'discount' => $this->discount,
            'additional_charges' => $this->additional_charges,
            'tax' => $this->tax,
            'total_amount' => $this->total_amount,
            'down_payment' => $this->down_payment,
            'payment_method' => $this->payment_method,
            'invoice' => $this->whenLoaded('invoice', fn () => $this->invoice ? [
                'id' => $this->invoice->id,
                'invoice_number' => $this->invoice->invoice_number,
                'status' => $this->invoice->status->value,
                'balance_amount' => $this->invoice->balance_amount,
            ] : null),
            'installment_plan' => $this->whenLoaded('installmentPlan', fn () => $this->installmentPlan ? [
                'id' => $this->installmentPlan->id,
                'plan_number' => $this->installmentPlan->plan_number,
                'status' => $this->installmentPlan->status->value,
            ] : null),
            'returns' => $this->whenLoaded('returns', fn () => $this->returns->map(fn ($return) => [
                'return_number' => $return->return_number,
                'type' => $return->type,
                'amount' => $return->amount,
                'reason' => $return->reason,
                'created_at' => $return->created_at?->toIso8601String(),
            ])),
            'notes' => $this->notes,
            'sold_at' => $this->sold_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}