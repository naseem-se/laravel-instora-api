<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSuperAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->company_id !== null) {
            throw new AuthorizationException('This action is restricted to platform administrators.');
        }

        return $next($request);
    }
}