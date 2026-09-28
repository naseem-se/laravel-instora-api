<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InstallmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'installment_number' => $this->installment_number,
            'due_date' => $this->due_date?->toDateString(),
            'principal_amount' => $this->principal_amount,
            'interest_amount' => $this->interest_amount,
            'late_fee_amount' => $this->late_fee_amount,
            'scheduled_amount' => $this->scheduled_amount,
            'paid_amount' => $this->paid_amount,
            'remaining_amount' => $this->remaining_amount,
            'status' => $this->status->value,
            'paid_at' => $this->paid_at?->toIso8601String(),
        ];
    }
}