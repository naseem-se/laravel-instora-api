<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SubscriptionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'company_name' => $this->whenLoaded('company', fn () => $this->company?->name),
            'monthly_fee' => $this->monthly_fee,
            'currency' => $this->currency,
            'billing_anchor_day' => $this->billing_anchor_day,
            'grace_period_days' => $this->grace_period_days,
            'status' => $this->status->value,
            'current_period_start' => $this->current_period_start?->toDateString(),
            'current_period_end' => $this->current_period_end?->toDateString(),
            'suspended_at' => $this->suspended_at?->toIso8601String(),
        ];
    }
}