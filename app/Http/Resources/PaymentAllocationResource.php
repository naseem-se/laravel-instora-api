<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentAllocationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'installment_number' => $this->whenLoaded('installment', fn () => $this->installment?->installment_number),
            'due_date' => $this->whenLoaded('installment', fn () => $this->installment?->due_date?->toDateString()),
            'principal_amount' => $this->principal_amount,
            'interest_amount' => $this->interest_amount,
            'late_fee_amount' => $this->late_fee_amount,
            'allocated_amount' => $this->allocated_amount,
        ];
    }
}