<?php

namespace App\Rules;

use App\Support\SsrfSafeUrlValidator;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class SafeProviderUrl implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! app(SsrfSafeUrlValidator::class)->isSafe((string) $value)) {
            $fail('The :attribute must be a reachable public HTTPS address. Local, private, and unresolvable addresses are not allowed.');
        }
    }
}