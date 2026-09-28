<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'category_id' => $this->category_id,
            'category_name' => $this->whenLoaded('category', fn () => $this->category?->name),
            'sku' => $this->sku,
            'name' => $this->name,
            'description' => $this->description,
            'cost_price' => $this->cost_price,
            'cash_price' => $this->cash_price,
            'installment_price' => $this->installment_price,
            'stock_quantity' => $this->stock_quantity,
            'status' => $this->status->value,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}