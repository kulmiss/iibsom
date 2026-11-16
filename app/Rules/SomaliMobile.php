<?php

namespace App\Rules;

use App\Support\Phone\SomaliPhone;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class SomaliMobile implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! SomaliPhone::isValid($value)) {
            $fail(__('account.validation.phone'));
        }
    }
}
