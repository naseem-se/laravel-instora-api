<?php

namespace App\Services\Installment;

use App\Enums\InstallmentFrequency;
use Carbon\Carbon;
use InvalidArgumentException;

class DueDateGenerator
{
    /**
     * @return list<Carbon>
     */
    public function generate(
        Carbon $startDate,
        InstallmentFrequency $frequency,
        int $count,
        ?int $customIntervalDays = null,
    ): array {
        $dates = [];

        for ($installmentNumber = 1; $installmentNumber <= $count; $installmentNumber++) {
            $dates[] = $this->dueDateFor($startDate, $frequency, $installmentNumber, $customIntervalDays);
        }

        return $dates;
    }

    /**
     * Every date is computed from the original start_date, never chained
     * from the previously computed date. Chaining drifts: starting Jan 31, a
     * chained "+1 month" pins to Feb 28, and a further "+1 month" from Feb 28
     * lands on Mar 28 instead of Mar 31. Computing addMonthsNoOverflow($n)
     * from the fixed anchor every time keeps each date correctly anchored.
     */
    private function dueDateFor(
        Carbon $startDate,
        InstallmentFrequency $frequency,
        int $installmentNumber,
        ?int $customIntervalDays,
    ): Carbon {
        return match ($frequency) {
            InstallmentFrequency::Daily => $startDate->copy()->addDays($installmentNumber),
            InstallmentFrequency::Weekly => $startDate->copy()->addWeeks($installmentNumber),
            InstallmentFrequency::Biweekly => $startDate->copy()->addWeeks($installmentNumber * 2),
            InstallmentFrequency::Monthly => $startDate->copy()->addMonthsNoOverflow($installmentNumber),
            InstallmentFrequency::Quarterly => $startDate->copy()->addMonthsNoOverflow($installmentNumber * 3),
            InstallmentFrequency::Custom => $startDate->copy()->addDays(
                ($customIntervalDays ?? throw new InvalidArgumentException(
                    'custom_interval_days is required when installment_frequency is custom.'
                )) * $installmentNumber
            ),
        };
    }
}