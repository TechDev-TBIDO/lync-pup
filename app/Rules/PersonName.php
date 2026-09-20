<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * A person's name or job position. Letters only (accented letters count),
 * plus spaces and the punctuation that real names/titles use: slash, hyphen,
 * apostrophe, dot and comma. Digits and every other symbol are rejected.
 *
 * The same character set is enforced on the client by the `data-person-name`
 * guard in partials/input-guards.blade.php — keep the two in sync.
 */
class PersonName implements ValidationRule
{
    /** Character class shared with the client-side guard (JS uses the same set). */
    public const PATTERN = '/^(?=.*\p{L})[\p{L}\p{M}\s\/\-\'’.,]+$/u';

    public const MESSAGE = 'The :attribute may only contain letters, spaces and the characters / - \' . ,';

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
