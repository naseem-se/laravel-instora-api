<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InstallmentPlanResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'plan_number' => $this->plan_number,
            'customer' => $this->whenLoaded('customer', fn () => [
                'id' => $this->customer->id,
                'name' => $this->customer->name,
                'customer_number' => $this->customer->customer_number,
            ]),
            'product' => $this->whenLoaded('product', fn () => $this->product ? [
                'id' => $this->product->id,
                'name' => $this->product->name,
                'sku' => $this->product->sku,
                'installment_price' => $this->product->installment_price,
                'cash_price' => $this->product->cash_price,
            ] : null),
            'start_date' => $this->start_date?->toDateString(),
            'end_date' => $this->end_date?->toDateString(),
            'principal_amount' => $this->principal_amount,
            'down_payment' => $this->down_payment,
            'financed_amount' => $this->financed_amount,
            'financial_charge_type' => $this->financial_charge_type->value,
            'interest_type' => $this->interest_type,
            'interest_rate' => $this->interest_rate,
            'interest_amount' => $this->interest_amount,
            'late_fee_type' => $this->late_fee_type->value,
            'late_fee_rate' => $this->late_fee_rate,
            'late_fee_amount' => $this->late_fee_amount,
            'maximum_late_fee' => $this->maximum_late_fee,
            'total_amount' => $this->total_amount,
            'paid_amount' => $this->paid_amount,
            'remaining_amount' => $this->remaining_amount,
            'installment_frequency' => $this->installment_frequency->value,
            'custom_interval_days' => $this->custom_interval_days,
            'number_of_installments' => $this->number_of_installments,
            'installment_amount' => $this->installment_amount,
            'status' => $this->status->value,
            'created_by' => $this->whenLoaded('createdBy', fn () => $this->createdBy?->name),
            'approved_by' => $this->whenLoaded('approvedBy', fn () => $this->approvedBy?->name),
            'approved_at' => $this->approved_at?->toIso8601String(),
            'notes' => $this->notes,
            'installments' => InstallmentResource::collection($this->whenLoaded('installments')),
            'invoice' => $this->whenLoaded('invoice', fn () => $this->invoice ? new InvoiceResource($this->invoice) : null),
            'settlements' => SettlementResource::collection($this->whenLoaded('settlements')),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}