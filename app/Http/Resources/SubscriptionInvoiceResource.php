<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SubscriptionInvoiceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'invoice_number' => $this->invoice_number,
            'period_start' => $this->period_start?->toDateString(),
            'period_end' => $this->period_end?->toDateString(),
            'amount' => $this->amount,
            'currency' => $this->currency,
            'due_date' => $this->due_date?->toDateString(),
            'status' => $this->status->value,
            'paid_at' => $this->paid_at?->toIso8601String(),
        ];
    }
}