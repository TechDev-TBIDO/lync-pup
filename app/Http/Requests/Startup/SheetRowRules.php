<?php

namespace App\Http\Requests\Startup;

use App\Rules\PersonName;
use App\Rules\PhMobile;

/**
 * Shared column shapes for the Information Sheet's row tables — Core Team
 * Formation, Incubation Involvement, L&D Interventions and References.
 *
 * Every column is required, exactly like the rest of PUP-TBIDO Form No. 001:
 * the form says "Indicate N/A If Not Applicable", so a blank cell means
 * "unanswered", not "does not apply". Columns that hold a real type — dates,
 * email, phone, hours — still have to carry a valid value, since "N/A" in
 * those would break the PDF exports and downstream parsing.
 *
 * A row that is entirely blank never reaches here: the founder-side page drops
 * empty "add new" rows before saving (see submitInfoSheetForms()).
 */
trait SheetRowRules
{
    /**
     * Trim each cell and fold a pasted line break into a space. The row inputs
     * block Enter, but a paste can still smuggle one in.
     */
    protected function prepareForValidation(): void
    {
        $payload = [];

        foreach ($this->all() as $field => $value) {
            if (! is_string($value) || in_array($field, ['_token', '_method'], true)) {
                continue;
            }

            $clean = preg_replace('/\s*\R\s*/u', ' ', trim($value));

            if ($clean !== $value) {
                $payload[$field] = $clean;
            }
        }

        if ($payload) {
            $this->merge($payload);
        }
    }

    /** Letters only, plus the punctuation real names carry. */
    protected function rowName(int $max): array
    {
        return ['required', 'string', 'max:'.$max, 'regex:/^(n\/a|[\p{L}][\p{L}\s\.\,\-\x{2019}\']*)$/iu'];
    }

    /** Words, digits and ordinary punctuation — organisations, titles, addresses. */
    protected function rowText(int $max): array
    {
        return ['required', 'string', 'max:'.$max, 'regex:/^(n\/a|[\p{L}\p{N}\#][\p{L}\p{N}\s\.\,\-\#\/\(\)\&\x{2019}\']*)$/iu'];
    }

    /** Never-numeric answers: sex, civil status, citizenship. */
    protected function rowWords(int $max): array
    {
        return ['required', 'string', 'max:'.$max, 'regex:/^(n\/a|[\p{L}][\p{L}\s\.\-\x{2019}\']*)$/iu'];
    }

    /** A count of hours, or N/A. */
    protected function rowHours(): array
    {
        return ['required', 'string', 'max:20', 'regex:/^(n\/a|\d+(\.\d+)?)$/i'];
    }

    /**
     * A Philippine mobile number in exactly one of two shapes - 09XXXXXXXXX
     * or +639XXXXXXXXX - digits only, no spaces, dashes or any other
     * punctuation (see App\Rules\PhMobile), the same shape every phone field
     * in the app now enforces.
     */
    protected function rowPhone(): array
    {
        return ['required', 'string', 'max:13', new PhMobile];
    }

    /**
     * Fails only on the literal answer "N/A" (any case, surrounding
     * whitespace trimmed). A few of the shapes below - rowDesignation and
     * rowAddress - allow a forward slash for genuine reasons ("Marketing /
     * Sales Lead", "123 Rizal St. / Unit 4"), which means their character
     * class alone can't tell a real slash from the one in "N/A": both are
     * just letters plus an allowed slash to the regex. This closure is the
     * explicit backstop those two rules append themselves - see each one's
     * own doc comment.
     */
    protected function notNA(): \Closure
    {
        return function ($attribute, $value, $fail) {
            if (is_string($value) && strcasecmp(trim($value), 'N/A') === 0) {
                $fail('N/A is not accepted here.');
            }
        };
    }

    /**
     * A single "contains a letter" check still lets something like
     * "1234567890a" through - one letter tacked onto a run of digits long
     * enough to clear a min: length. Real prose is made mostly of letters,
     * with a digit here and there (a unit number, a year) rather than the
     * other way around, so this fails whenever digits actually outnumber
     * letters - "123 Rizal St." (3 digits, 10 letters) passes; "1234567890a"
     * or a bare phone number typed into a name/address field does not.
     */
    protected function meaningfulText(string $message): \Closure
    {
        return function ($attribute, $value, $fail) use ($message) {
            if (! is_string($value)) {
                return;
            }

            $letters = preg_match_all('/\p{L}/u', $value);
            $digits = preg_match_all('/\p{N}/u', $value);

            if ($letters === 0 || $digits > $letters) {
                $fail($message);
            }
        };
    }

    /**
     * A real person's name: letters, spaces, and . , - ' only - no digits, no
     * N/A (unlike rowName() above, which is shared with rows that may
     * genuinely have nothing to put there). Suffixes like "Jr." or "III"
     * are just letters and a period, so they already fit. The comma is
     * allowed (not required) so "Surname, Firstname" works even without a
     * middle name or extension, matching the Core Team table's
     * "Surname, Firstname, Middle Name, Ext" column format.
     */
    protected function rowPersonName(int $max): array
    {
        return ['required', 'string', 'max:'.$max, new PersonName, $this->notNA()];
    }

    /**
     * The Core Team Formation name column specifically: not just a real
     * name (rowPersonName above), but the exact "Surname, Firstname[,
     * Middle Name[, Ext]]" shape its own header asks for - two to four
     * comma-separated parts, each one or more space-separated words so a
     * compound surname like "Dela Cruz" still counts as one part. A bare
     * "Elias Navarro" (no comma at all) no longer passes; neither does a
     * fifth part. A missing Middle Name or Ext is left out entirely rather
     * than filled with "N/A" - a stray "N/A" breaks the match here exactly
     * like it does in rowPersonName, since a slash isn't an allowed
     * character in any part.
     */
    protected function rowFullName(int $max): array
    {
        $word = '[\p{L}][\p{L}\.\-\x{2019}\']*';
        $part = $word.'(?:\s+'.$word.')*';

        return ['required', 'string', 'max:'.$max, new PersonName, 'regex:/^'.$part.'(?:,\s*'.$part.'){1,3}$/iu'];
    }

    /**
     * A job title or role: letters, spaces, and / - ' . , only (PersonName) - no digits, no N/A.
     * The slash is allowed for real designations ("Marketing / Sales
     * Lead"), so notNA() is appended to actually catch the literal "N/A"
     * that character class alone would otherwise let through.
     */
    protected function rowDesignation(int $max): array
    {
        return [
            'required', 'string', 'max:'.$max, new PersonName,
            $this->notNA(),
        ];
    }

    /**
     * A real address: letters, numbers, spaces, and , . - # / - no N/A.
     * Same slash caveat as rowDesignation() above - notNA() is what
     * actually blocks the literal "N/A".
     */
    protected function rowAddress(int $max): array
    {
        // A real address is never this short - min:10 matches the same
        // floor the founder's own residential/permanent address already
        // uses on the main sheet. meaningfulText() catches what min: alone
        // can't: a run of digits long enough to clear that floor on its own.
        return [
            'required', 'string', 'max:'.$max, 'min:10',
            'regex:/^[\p{L}\p{N}\#][\p{L}\p{N}\s\.\,\-\#\/]*$/iu',
            $this->notNA(),
            $this->meaningfulText('Please enter a real address, not just numbers.'),
        ];
    }

    /**
     * A citizenship/nationality: letters, spaces, and . - ' only - no N/A.
     * min:3 blocks a stray single letter ("r") from passing as one -
     * no real demonym in common use is shorter than that.
     */
    protected function rowCitizenship(int $max): array
    {
        return ['required', 'string', 'max:'.$max, 'min:3', 'regex:/^[\p{L}][\p{L}\s\.\-\x{2019}\']*$/iu'];
    }

    /**
     * Fallback wording, so a column without its own message still tells the
     * founder what to do rather than showing Laravel's default.
     */
    protected function rowMessages(array $messages = []): array
    {
        return $messages + [
            'required' => 'Required. Type N/A if it does not apply.',
            'regex' => 'Check this entry — it contains characters that are not allowed here.',
            'date' => 'Enter a valid date.',
            'email' => 'Enter a valid email address, for example name@email.com.',
        ];
    }
}
