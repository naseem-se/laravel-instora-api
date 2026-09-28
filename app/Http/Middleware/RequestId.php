<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class RequestId
{
    public const HEADER = 'X-Request-ID';

    public function handle(Request $request, Closure $next): Response
    {
        $requestId = $request->header(self::HEADER);

        // Reject anything that isn't a clean ID so client input can't poison the logs.
        if (! is_string($requestId) || ! preg_match('/^[A-Za-z0-9_\-]{8,64}$/', $requestId)) {
            $requestId = 'req_'.Str::lower((string) Str::ulid());
        }

        $request->headers->set(self::HEADER, $requestId);
        Log::shareContext(['request_id' => $requestId]);

        $response = $next($request);
        $response->headers->set(self::HEADER, $requestId);

        return $response;
    }
}