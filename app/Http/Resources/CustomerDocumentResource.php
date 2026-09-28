<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CustomerDocumentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'document_type' => $this->document_type,
            'document_number' => $this->document_number,
            'issued_at' => $this->issued_at?->toDateString(),
            'expires_at' => $this->expires_at?->toDateString(),
            'file_name' => basename($this->file_path),
            'file_url' => route('api.v1.customers.documents.download', [
                'customerId' => $this->customer_id,
                'documentId' => $this->id,
            ]),
            'storage_disk' => $this->storage_disk,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}