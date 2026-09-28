<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'payment_number' => $this->payment_number,
            'customer' => $this->whenLoaded('customer', fn () => [
                'id' => $this->customer->id,
                'name' => $this->customer->name,
            ]),
            'installment_plan' => $this->whenLoaded('installmentPlan', fn () => $this->installmentPlan ? [
                'id' => $this->installmentPlan->id,
                'plan_number' => $this->installmentPlan->plan_number,
            ] : null),
            'payment_date' => $this->payment_date?->toDateString(),
            'amount' => $this->amount,
            'payment_method' => $this->payment_method->value,
            'reference_number' => $this->reference_number,
            'notes' => $this->notes,
            'status' => $this->status->value,
            'received_by' => $this->whenLoaded('receivedBy', fn () => $this->receivedBy?->name),
            'allocations' => PaymentAllocationResource::collection($this->whenLoaded('allocations')),
            'reversal' => $this->whenLoaded('reversal', fn () => $this->reversal ? [
                'reason' => $this->reversal->reason,
                'reversed_by' => $this->reversal->reversedBy?->name,
                'reversed_at' => $this->reversal->reversed_at?->toIso8601String(),
            ] : null),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}