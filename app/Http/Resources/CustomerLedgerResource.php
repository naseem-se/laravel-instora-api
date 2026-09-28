<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CustomerLedgerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'transaction_type' => $this->transaction_type,
            'reference_type' => $this->reference_type,
            'reference_id' => $this->reference_id,
            'installment_plan_id' => $this->installment_plan_id,
            'debit' => $this->debit,
            'credit' => $this->credit,
            'balance' => $this->balance,
            'transaction_date' => $this->transaction_date?->toIso8601String(),
            'description' => $this->description,
        ];
    }
}