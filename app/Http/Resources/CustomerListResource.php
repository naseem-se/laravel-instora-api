<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CustomerListResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'customer_number' => $this->customer_number,
            'name' => $this->name,
            'phone' => $this->phone,
            'city' => $this->city,
            'cnic_masked' => $this->maskCnic($this->cnic),
            'status' => $this->status->value,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }

    private function maskCnic(?string $cnic): ?string
    {
        if (! $cnic) {
            return null;
        }

        $length = strlen($cnic);

        if ($length <= 4) {
            return $cnic;
        }

        return str_repeat('•', $length - 4).substr($cnic, -4);
    }
}