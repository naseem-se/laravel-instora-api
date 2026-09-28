<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SettlementResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'settlement_date' => $this->settlement_date?->toDateString(),
            'outstanding_amount' => $this->outstanding_amount,
            'discount_amount' => $this->discount_amount,
            'late_fee_waived' => $this->late_fee_waived,
            'final_amount' => $this->final_amount,
            'payment_id' => $this->payment_id,
            'approved_by' => $this->whenLoaded('approvedBy', fn () => $this->approvedBy?->name),
        ];
    }
}