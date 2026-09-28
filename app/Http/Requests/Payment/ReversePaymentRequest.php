<?php

namespace App\Http\Requests\Payment;

use Illuminate\Foundation\Http\FormRequest;

class ReversePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // authorize() call for 'reverse' happens in the controller
    }

    public function rules(): array
    {
        // Required - unlike plan cancellation, reversing money that already
        // moved is high-stakes enough to demand a documented reason every time.
        return ['reason' => ['required', 'string', 'max:1000']];
    }
}