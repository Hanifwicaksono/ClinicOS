<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

class PhoneNumber implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! preg_match('/^\+?[0-9\s().-]+$/D', $value) || ! preg_match('/[0-9]/', $value)) {
            $fail('Nomor telepon harus berisi angka; awalan +, spasi, tanda kurung, titik, dan tanda hubung diperbolehkan.');
        }
    }
}
