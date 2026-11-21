<?php

namespace App\Support;

use Illuminate\Validation\ValidationException;

class LeadCredits
{
    public static function quantity(mixed $value, string $field = 'credits', int $minimum = 0): int
    {
        if (!is_numeric($value) || (float) $value < $minimum || floor((float) $value) !== (float) $value || (float) $value > 1000000000000) {
            throw ValidationException::withMessages([$field => 'Enter a whole number of lead credits, at least '.$minimum.'.']);
        }
        return (int) $value;
    }
}
