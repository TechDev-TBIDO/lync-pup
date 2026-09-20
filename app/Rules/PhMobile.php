<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Philippine mobile number, exactly 09XXXXXXXXX or +639XXXXXXXXX — no spaces,
 * dashes or other characters. Mirrors the `data-ph-mobile` client guard in
 * partials/input-guards.blade.php.
 */
class PhMobile implements ValidationRule
{
    public const PATTERN = '/^(09\d{9}|\+639\d{9})$/';

    public const MESSAGE = 'The :attribute must use the format 09XXXXXXXXX or +639XXXXXXXXX.';

    public static function passes(mixed $value): bool
    {
        if ($value === null || $value === '') {
            return true;
        }

        return is_string($value) && preg_match(self::PATTERN, $value) === 1;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! self::passes($value)) {
            $fail(self::MESSAGE);
        }
    }
}
