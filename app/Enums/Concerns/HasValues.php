<?php

namespace App\Enums\Concerns;

/**
 * @mixin \UnitEnum
 * @method static array cases()
 */
trait HasValues
{
    /** All raw enum values, e.g. for Rule::in() in Form Requests. */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}