<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Never exposes credentials or webhook_secret - see Security.md #6. */
class WhatsAppProviderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'configured' => true,
            'provider_type' => $this->provider_type,
            'name' => $this->name,
            'base_url' => $this->base_url,
            'api_version' => $this->api_version,
            'phone_number_id' => $this->phone_number_id,
            'business_account_id' => $this->business_account_id,
            'sender_number' => $this->maskedSenderNumber(),
            'status' => $this->status->value,
            'last_verified_at' => $this->last_verified_at?->toIso8601String(),
            'last_error' => $this->last_error,
            'has_webhook_secret' => (bool) $this->webhook_secret,
        ];
    }

    private function maskedSenderNumber(): ?string
    {
        if (! $this->sender_number) {
            return null;
        }

        $length = strlen($this->sender_number);

        return $length <= 4 ? $this->sender_number : str_repeat('*', $length - 4).substr($this->sender_number, -4);
    }
}