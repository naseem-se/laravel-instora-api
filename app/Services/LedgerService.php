<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\CustomerLedger;
use App\Support\Money;
use Carbon\Carbon;

class LedgerService
{
    public function record(
        int $companyId,
        int $customerId,
        string $transactionType,
        string $referenceType,
        int $referenceId,
        int $debitCents,
        int $creditCents,
        string $description,
        ?int $createdBy = null,
        ?Carbon $transactionDate = null,
        ?int $installmentPlanId = null,
    ): CustomerLedger {

        Customer::where('id', $customerId)->lockForUpdate()->first();

        $lastEntry = CustomerLedger::where('customer_id', $customerId)->orderByDesc('id')->first();
        $previousBalanceCents = $lastEntry ? Money::toCents($lastEntry->balance) : 0;
        $newBalanceCents = $previousBalanceCents + $debitCents - $creditCents;

        $entry = new CustomerLedger([
            'customer_id' => $customerId,
            'transaction_type' => $transactionType,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'debit' => Money::fromCents($debitCents),
            'credit' => Money::fromCents($creditCents),
            'transaction_date' => $transactionDate ?? now(),
            'description' => $description,
        ]);
        $entry->company_id = $companyId;
        $entry->installment_plan_id = $installmentPlanId;
        $entry->setAttribute('balance', Money::fromCents($newBalanceCents));
        $entry->created_by = $createdBy;
        $entry->save();

        return $entry;
    }
}