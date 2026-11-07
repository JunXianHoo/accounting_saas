<?php

namespace App\Support;

use UnexpectedValueException;

final class Decimal
{
    /** @return numeric-string */
    public static function format(string|int|float $value): string
    {
        $number = (string) $value;

        if (! is_numeric($number)) {
            throw new UnexpectedValueException('Expected a numeric decimal value.');
        }

        return bcadd($number, '0', 2);
    }
}
