<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NotificationLogListResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type->value,
            'channel' => $this->channel->value,
            'recipient' => $this->recipient,
            'status' => $this->status->value,
            'skip_reason' => $this->skip_reason,
            'customer_name' => $this->whenLoaded('customer', fn () => $this->customer->name),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}