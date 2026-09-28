<?php

namespace App\Services;

use App\Exceptions\IdempotencyKeyConflictException;
use App\Exceptions\IdempotencyKeyInProgressException;
use App\Exceptions\IdempotencyReplayException;
use App\Models\IdempotencyKey;
use Illuminate\Database\QueryException;

class IdempotencyService
{

    public function begin(string $route, int $companyId, ?string $key, array $payload): ?IdempotencyKey
    {
        if (! $key) {
            return null;
        }

        $hash = hash('sha256', json_encode($payload));

        try {
            return IdempotencyKey::create([
                'company_id' => $companyId,
                'route' => $route,
                'key' => $key,
                'request_hash' => $hash,
            ]);
        } catch (QueryException $e) {

            if ($e->getCode() !== '23000') {
                throw $e;
            }

            $existing = IdempotencyKey::where('company_id', $companyId)
                ->where('route', $route)
                ->where('key', $key)
                ->first();

            if (! $existing || $existing->request_hash !== $hash) {
                throw new IdempotencyKeyConflictException();
            }

            if ($existing->response_body === null) {
                throw new IdempotencyKeyInProgressException();
            }

            throw new IdempotencyReplayException($existing->response_status, $existing->response_body);
        }
    }

    public function complete(?IdempotencyKey $record, int $status, array $body): void
    {
        $record?->update(['response_status' => $status, 'response_body' => $body]);
    }
}