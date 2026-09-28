<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AgingInstallmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'plan_id' => $this->installment_plan_id,
            'plan_number' => $this->whenLoaded('installmentPlan', fn () => $this->installmentPlan?->plan_number),
            'customer_name' => $this->whenLoaded('customer', fn () => $this->customer?->name),
            'installment_number' => $this->installment_number,
            'due_date' => $this->due_date?->toDateString(),
            // now - due_date, clamped to 0 for not-yet-due installments in
            // the "current" bucket.
            'days_overdue' => $this->due_date
                ? max(0, now()->startOfDay()->diffInDays($this->due_date->copy()->startOfDay(), false) * -1)
                : null,
            'remaining_amount' => $this->remaining_amount,
            'status' => $this->status->value,
        ];
    }
}