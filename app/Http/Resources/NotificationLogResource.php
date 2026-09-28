<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NotificationLogResource extends JsonResource
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
            'error_code' => $this->error_code,
            'error_message' => $this->error_message,
            'rendered_subject' => $this->rendered_subject,
            'rendered_body' => $this->rendered_body,
            'customer' => $this->whenLoaded('customer', fn () => [
                'id' => $this->customer->id,
                'name' => $this->customer->name,
            ]),
            'scheduled_for' => $this->scheduled_for?->toIso8601String(),
            'sent_at' => $this->sent_at?->toIso8601String(),
            'delivered_at' => $this->delivered_at?->toIso8601String(),
            'read_at' => $this->read_at?->toIso8601String(),
            'delivery_attempts' => NotificationDeliveryAttemptResource::collection($this->whenLoaded('deliveryAttempts')),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}