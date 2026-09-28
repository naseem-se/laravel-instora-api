<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentListResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'payment_number' => $this->payment_number,
            'customer_name' => $this->whenLoaded('customer', fn () => $this->customer->name),
            'plan_number' => $this->whenLoaded('installmentPlan', fn () => $this->installmentPlan?->plan_number),
            'amount' => $this->amount,
            'payment_method' => $this->payment_method->value,
            'status' => $this->status->value,
            'payment_date' => $this->payment_date?->toDateString(),
        ];
    }
}