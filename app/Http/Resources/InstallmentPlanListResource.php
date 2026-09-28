<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InstallmentPlanListResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'plan_number' => $this->plan_number,
            'customer_name' => $this->whenLoaded('customer', fn () => $this->customer->name),
            'product_name' => $this->whenLoaded('product', fn () => $this->product?->name),
            'total_amount' => $this->total_amount,
            'paid_amount' => $this->paid_amount,
            'remaining_amount' => $this->remaining_amount,
            'number_of_installments' => $this->number_of_installments,
            'status' => $this->status->value,
            'start_date' => $this->start_date?->toDateString(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}