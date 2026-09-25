<?php

namespace App\Http\Requests\Startup;

use App\Models\InformationSheet;
use App\Rules\PersonName;
use App\Rules\PhMobile;
use App\Support\SheetOptions;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class UpdateInformationSheetRequest extends FormRequest
{
    /**
     * These lock checks used to live in the controller, run only after
     * validation passed - but with every field on the sheet required, an
     * incomplete/locked-out save attempt would fail validation first and
     * never reach them, surfacing a generic "fix these fields" redirect
     * instead of the specific "this is locked" message. Checking here
     * instead runs before rules() at all, so a locked sheet always gets a
     * clean 403 regardless of what the payload contains.
     */
    public function authorize(): bool
    {
        if (! $this->user()->isStartup()) {
            return false;
        }

        $startup = $this->user()->startup;
        $sheet = $startup?->informationSheet;

        abort_unless($startup?->isProfileComplete(), 403, 'Please complete your Startup Profile first before filling out the Information Sheet.');
        abort_if($sheet && $sheet->approval_status === 'Approved', 403, 'This Information Sheet is approved and locked. Contact your Coordinator for changes.');
        abort_if($startup->evaluationDayLockActive(), 403, 'This Information Sheet is locked for today - your evaluation is scheduled today. It reopens tomorrow if the evaluation does not push through.');

        return true;
    }

    /**
     * PUP-TBIDO Form No. 001 is filled out in capital letters, so the entries
     * are upper-cased on the way in — the inputs also render uppercase, and
     * this makes the stored value match what the founder sees (and what the
     * PDF export prints).
     *
     * Email is deliberately excluded: the part before the @ is case-sensitive
     * on some mail servers, so upper-casing it can break delivery.
     */
    protected function prepareForValidation(): void
    {
        $upper = [
            // I. Founder's information
            'surname', 'first_name', 'middle_name', 'name_extension', 'blood_type',
            'gsis_no', 'pagibig_no', 'philhealth_no', 'sss_no', 'tin',
            'residential_address', 'permanent_address', 'sex', 'civil_status',
            'citizenship_by_birth', 'citizenship_dual', 'place_of_birth',
            // 28-31. Startup registration
            'sec_registration', 'business_id_number', 'dti_registration_number', 'business_tin',
            // 32 & 34. Distinctions and memberships
            'non_academic_distinctions', 'membership_associations',
        ];

        // Fields that legitimately hold several lines. Everything else is a
        // one-line answer, so a pasted line break is folded into a space —
        // the inputs prevent Enter, but paste can still smuggle one in.
        $multiline = [
            'startup_overview', 'scholarships_academic_honors',
            'non_academic_distinctions', 'membership_associations',
        ];

        $payload = [];

        foreach ($this->all() as $field => $value) {
            if (! is_string($value) || in_array($field, ['_token', '_method'], true)) {
                continue;
            }

            $clean = in_array($field, $multiline, true)
                ? trim($value)
                : preg_replace('/\s*\R\s*/u', ' ', trim($value));

            if (in_array($field, $upper, true)) {
                $clean = mb_strtoupper($clean, 'UTF-8');
            }

            if ($clean !== $value) {
                $payload[$field] = $clean;
            }
        }

        if ($payload) {
            $this->merge($payload);
        }

        // Items 23, 32 and 34 are optional row tables: an empty one is saved
        // blank - N/A is not needed.

        // 5 & 6. The founder types a number and picks its unit; the sheet stores
        // metres and kilograms, which is what the column names promise and what
        // the PDF prints. Converting here means every rule below - and
        // blankedFields() - only ever sees the canonical value.
        $measurements = [
            'height_m' => ['input' => 'height_input', 'unit' => 'height_unit', 'factors' => ['cm' => 0.01, 'in' => 0.0254, 'm' => 1.0, 'ft' => 0.3048]],
            'weight_kg' => ['input' => 'weight_input', 'unit' => 'weight_unit', 'factors' => ['kg' => 1.0, 'lb' => 0.45359237]],
        ];

        foreach ($measurements as $target => $spec) {
            if (! $this->has($spec['input'])) {
                continue;
            }

            $raw = trim((string) $this->input($spec['input']));
            $factor = $spec['factors'][$this->input($spec['unit'])] ?? null;

            // A non-number or an unknown unit is passed through untouched so
            // the rules below produce the message, rather than silently
            // storing a zero. A genuinely blank input is merged as null
            // (not '') specifically so a draft Save's 'nullable' rule (see
            // rules() below) actually skips the between/numeric checks on
            // this derived field — merging '' here would leave the field
            // failing those checks even when nothing was typed.
            $this->merge([
                $target => $raw === ''
                    ? null
                    : (! is_numeric($raw) || $factor === null
                        ? $raw
                        : (string) round((float) $raw * $factor, 2)),
            ]);
        }
    }

    /**
     * 'save' persists whatever the founder has filled in so far without
     * requiring the sheet to be complete — a founder can save progress and
     * come back later. 'submit' is the final action: every field must pass
     * its full rule (same as before this Save/Submit split existed), and is
     * the only action that actually puts the sheet in front of an admin
     * (see Startup\InformationSheetController::update()). Defaults to
     * 'submit' so a request that somehow omits the field still gets the
     * stricter behavior rather than silently accepting an incomplete sheet.
     */
    public function isDraftSave(): bool
    {
        return $this->input('intent') === 'save';
    }

    /**
     * Two independent cross-field checks that no single column's own rule
     * can express on its own — see each guard method's doc comment.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $this->guardFilledFieldsStayFilled($validator);
            $this->guardEducationalBackgroundConsistency($validator);
            $this->guardAtLeastOneEducationLevel($validator);
        });
    }

    /**
     * "Once filled, never blank" - a required field that already holds a saved
     * answer can be replaced but not cleared. A final Submit always enforces
     * it. A draft Save is free to clear fields only while the sheet has never
     * been submitted (the founder is still working on it); once it has been
     * submitted - or an evaluation is scheduled - every later edit, draft
     * Save included, can replace an answer but not blank it. See
     * InformationSheet::blankedFields().
     */
    private function guardFilledFieldsStayFilled(Validator $validator): void
    {
        $startup = $this->user()->startup;
        $sheet = $startup?->informationSheet;

        if (! $sheet) {
            return;
        }

        if ($this->isDraftSave() && ! $sheet->submission_date && ! $startup->hasScheduledEvaluation()) {
            return;
        }

        // Only the required fields. height_m / weight_kg are the derived
        // values behind the height/weight boxes, so a blanked box is caught
        // here under the field the page anchors its error to.
        $required = collect($this->strictRules())
            ->filter(fn ($fieldRules) => in_array('required', $fieldRules, true))
            ->keys()
            ->all();

        $data = collect($validator->getData())->except(['_token', '_method'])->all();

        foreach ($sheet->blankedFields($data, $required) as $field) {
            // A Submit that leaves it blank already failed 'required' - one
            // message per field is enough.
            if ($validator->errors()->has($field)) {
                continue;
            }

            $validator->errors()->add(
                $field,
                "This field was already filled in, so it can't be left blank - keep the current answer or replace it with a new one."
            );
        }
    }

    /**
     * 22. Educational Background: every level is optional on its own, but
     * the item as a whole needs at least one level filled in. Only checked
     * when the table was actually submitted.
     */
    private function guardAtLeastOneEducationLevel(Validator $validator): void
    {
        if ($this->isDraftSave()) {
            return;
        }

        $levels = ['secondary', 'vocational', 'college', 'graduate'];

        if (! $this->has('secondary_school')) {
            return;
        }

        foreach ($levels as $level) {
            $school = trim((string) $this->input("{$level}_school"));
            if ($school !== '' && strcasecmp($school, 'N/A') !== 0) {
                return;
            }
        }

        if (! $validator->errors()->has('secondary_school')) {
            $validator->errors()->add('secondary_school', 'Please fill in at least one level of your educational background.');
        }
    }

    /**
     * Item 22, Educational Background: "Name of School" is the switch for
     * its whole row. N/A there means the founder never reached that level,
     * so the other three columns are auto-filled with N/A and locked on the
     * page itself (see the schoolNA Alpine state in edit.blade.php) — this
     * is the server-side half of that same rule, since a direct POST could
     * otherwise bypass the locked inputs entirely. Checked both ways: a row
     * with a real school name can't leave degree/units/year as N/A, and a
     * row genuinely marked N/A can't sneak a real value into one of the
     * other three (which would contradict "I never attended this level").
     */
    private function guardEducationalBackgroundConsistency(Validator $validator): void
    {
        $data = $validator->getData();

        $levels = [
            'secondary' => 'secondary school',
            'vocational' => 'vocational course',
            'college' => 'college',
            'graduate' => 'graduate studies',
        ];

        $isNA = function ($value) {
            return is_string($value) && strcasecmp(trim($value), 'N/A') === 0;
        };

        foreach ($levels as $key => $label) {
            $schoolField = $key.'_school';
            $otherFields = [$key.'_degree_course', $key.'_highest_level_unit', $key.'_year_graduated'];

            if (! array_key_exists($schoolField, $data)) {
                continue;
            }

            $schoolIsNA = $isNA($data[$schoolField] ?? null);

            foreach ($otherFields as $field) {
                if (! array_key_exists($field, $data)) {
                    continue;
                }

                $fieldIsNA = $isNA($data[$field] ?? null);

                // Year Graduated is optional: N/A or blank is always fine (e.g.
                // still studying). Only a real year under an N/A school is a
                // contradiction.
                if ($field === $key.'_year_graduated') {
                    $blankYear = trim((string) ($data[$field] ?? '')) === '';

                    if ($schoolIsNA && ! $fieldIsNA && ! $blankYear) {
                        $validator->errors()->add($field, "The {$label} name is N/A, so this must be N/A or blank.");
                    }

                    continue;
                }

                if (! $schoolIsNA && $fieldIsNA) {
                    $validator->errors()->add(
                        $field,
                        "You entered a {$label} name, so this can't be N/A — please provide a value or clear the school name too."
                    );
                }

                if ($schoolIsNA && ! $fieldIsNA) {
                    $validator->errors()->add(
                        $field,
                        "The {$label} name is N/A, so this must be N/A too."
                    );
                }
            }
        }
    }

    /**
     * On a final Submit, every field on the founder's Information Sheet is
     * required — the PUP form itself says "Indicate N/A If Not Applicable",
     * so blanks mean "unanswered", not "doesn't apply". Text and ID fields
     * therefore accept the literal "N/A"; fields with a real type (email,
     * phone, dates, height/weight, year graduated) still have to hold a
     * valid value, since "N/A" in those columns would break exports and
     * downstream parsing.
     *
     * A draft Save runs the exact same rules minus 'required' (see
     * looseForDraft() below) — so a value that IS typed still has to look
     * right, but a field can be left blank for now.
     */
    public function rules(): array
    {
        return $this->isDraftSave() ? $this->looseForDraft($this->strictRules()) : $this->strictRules();
    }

    /**
     * Swaps every top-level 'required' for 'nullable', leaving every other
     * rule (format regexes, closures, in:) untouched. Combined with
     * ConvertEmptyStringsToNull (global middleware) turning a blank input
     * into null before validation runs, this means: nothing typed → skipped
     * entirely; something typed → still has to pass its normal rule.
     */
    private function looseForDraft(array $rules): array
    {
        foreach ($rules as $field => $fieldRules) {
            // A draft may leave anything unfinished - including a half-filled
            // Educational Background row (required_with).
            $rules[$field] = array_values(array_filter(array_map(
                fn ($rule) => $rule === 'required' ? 'nullable' : $rule,
                $fieldRules
            ), fn ($rule) => ! (is_string($rule) && str_starts_with($rule, 'required_with:'))));

            if (! in_array('nullable', $rules[$field], true) && ! in_array('required', $rules[$field], true)) {
                array_unshift($rules[$field], 'nullable');
            }
        }

        return $rules;
    }

    private function strictRules(): array
    {
        $text = fn (int $max) => ['required', 'string', 'max:'.$max];

        // Names: letters only (plus the punctuation real names carry — spaces,
        // hyphens, apostrophes, periods, Ñ/accents). No digits, no symbols.
        // Everyone has a surname and a first name, so - unlike Middle Name and
        // Name Extension just below - N/A is not a valid answer here: the
        // character class has no slash in it, so "N/A" simply can't match.
        $properName = fn (int $max) => [
            'required', 'string', 'max:'.$max,
            'regex:/^[\p{L}][\p{L}\s\.\-\x{2019}\']*$/iu',
            new PersonName,
        ];

        // Same shape as above, but N/A is a real answer here - not everyone
        // has a middle name or a suffix.
        $name = fn (int $max) => [
            'required', 'string', 'max:'.$max,
            'regex:/^(n\/a|[\p{L}][\p{L}\s\.\-\x{2019}\']*)$/iu',
            new PersonName,
        ];

        // Words only: letters plus the punctuation that shows up inside real
        // words (spaces, hyphens, apostrophes, periods). Used for answers that
        // are never numeric — civil status wording, dual citizenship. N/A is
        // a real answer here (not everyone has a dual citizenship).
        $words = fn (int $max) => [
            'required', 'string', 'max:'.$max,
            'regex:/^(n\/a|[\p{L}][\p{L}\s\.\-\x{2019}\']*)$/iu',
        ];

        // Same shape as $words, but N/A is not a real answer: everyone has a
        // citizenship by birth, so this is only used for citizenship_by_birth.
        $citizenshipByBirth = fn (int $max) => [
            'required', 'string', 'max:'.$max,
            'regex:/^[\p{L}][\p{L}\s\.\-\x{2019}\']*$/iu',
        ];

        // A single "contains a letter" check still lets something like
        // "1234567890a" through - one letter tacked onto a run of digits
        // long enough to clear a min: length. Real prose is made mostly of
        // letters, with a digit here and there (a house number, a year)
        // rather than the other way around, so this fails whenever digits
        // actually outnumber letters - "Sta. Mesa, Manila" or "123 Rizal
        // St." pass; "1234567890" or "1234567890a" do not. N/A itself is
        // unaffected wherever N/A is a real answer - "N/A" has letters in
        // it, so it always passes this check on its own merits.
        $meaningfulText = function ($attribute, $value, $fail) {
            if (! is_string($value)) {
                return;
            }

            $letters = preg_match_all('/\p{L}/u', $value);
            $digits = preg_match_all('/\p{N}/u', $value);

            if ($letters === 0 || $digits > $letters) {
                $fail('Enter a real answer, not just numbers or symbols.');
            }
        };

        // Place names: words, digits and commas - some barangays and streets are
        // numbered, e.g. "Sta. Mesa, Manila" or "Barangay 176, Caloocan".
        // N/A is not a real answer here - everyone was born somewhere.
        $place = fn (int $max) => [
            'required', 'string', 'max:'.$max,
            'regex:/^[\p{L}\p{N}][\p{L}\p{N}\s\.\,\-\x{2019}\']*$/iu',
            $meaningfulText,
        ];

        // Addresses: words and house/unit numbers, plus the punctuation an
        // address actually uses. Rejects @ ! $ % ^ * = < > and friends. A real
        // address is never this short, so a minimum length is what actually
        // keeps out "N/A" and other non-answers - the character class alone
        // wouldn't, since digits, letters and / are all valid address text.
        // N/A is not a real answer here - everyone has an address to declare.
        $address = fn (int $max) => [
            'required', 'string', 'max:'.$max, 'min:10',
            'regex:/^[\p{L}\p{N}\#][\p{L}\p{N}\s\.\,\-\#\/\(\)\&\x{2019}\']*$/iu',
            $meaningfulText,
        ];

        // Name of School: letters, numbers, spaces, and . - ' & ( ) - no
        // slash, no comma, e.g. "Polytechnic University of the Philippines"
        // or "St. Paul's College".
        $schoolName = fn (int $max) => [
            'required', 'string', 'max:'.$max,
            'regex:/^(n\/a|[\p{L}\p{N}][\p{L}\p{N}\s\.\-\&\(\)\x{2019}\']*)$/iu',
            $meaningfulText,
        ];

        // Degree / Course: letters, numbers, spaces, and . - / & ( ) - no
        // apostrophe, no comma, e.g. "BS Computer Science / IT" or
        // "Bachelor's" (spelled without the apostrophe, since that one isn't
        // allowed here).
        $degreeCourse = fn (int $max) => [
            'required', 'string', 'max:'.$max,
            'regex:/^(n\/a|[\p{L}\p{N}][\p{L}\p{N}\s\.\-\/\&\(\)]*)$/iu',
            $meaningfulText,
        ];

        // Highest Level / Unit: letters, numbers, spaces, and . - / ( ) plus an
        // apostrophe - no ampersand, no comma, e.g. "4th Year", "36 units" or
        // "Bachelor's Degree".
        $highestLevelUnit = fn (int $max) => [
            'required', 'string', 'max:'.$max,
            'regex:/^(n\/a|[\p{L}\p{N}][\p{L}\p{N}\s\.\-\/\(\)\x{2019}\']*)$/iu',
            $meaningfulText,
        ];

        // Blood type: A, B, AB or O with a + or - sign.
        $bloodType = [
            'required', 'string', 'max:10',
            'regex:/^(n\/a|(a|b|ab|o)\s?[+\-])$/i',
        ];

        // The startup overview: letters, numbers, spaces and common
        // punctuation . , ! ? ' - ( ) only - no emoji, no HTML/code, no other
        // symbols. Must start with a letter or number, so a string of bare
        // punctuation can't pass as a description. This is the one prose
        // field where N/A is never a real answer - every startup has
        // something to say about what it does - so a closure backstops the
        // regex above: "N/A" itself already fails that regex (no slash in
        // the character class), but spacing/punctuation variants like "NA",
        // "N.A." or "N / A" would otherwise still read as ordinary letters
        // and slip through.
        $notApplicableOverview = function ($attribute, $value, $fail) {
            if (! is_string($value)) {
                return;
            }

            $lettersOnly = strtoupper(preg_replace('/[^\p{L}]/u', '', $value));

            if (in_array($lettersOnly, ['NA', 'NONE', 'NOTAPPLICABLE'], true)) {
                $fail('Please describe the startup — N/A is not accepted here.');
            }
        };

        $prose = fn (int $max) => [
            'required', 'string', 'max:'.$max, 'min:10',
            // Ordinary sentence punctuation: . , ! ? ' - ( ) plus ; : / & % "
            // and curly quotes - an overview is normal prose ("B2B; SaaS",
            // "50% of users", "Web/Mobile").
            'regex:/^[\p{L}\p{N}][\p{L}\p{N}\s\.\,\!\?\'\-\(\);:\/&%"\x{2018}\x{2019}\x{201C}\x{201D}]*$/u',
            $notApplicableOverview,
            $meaningfulText,
        ];

        // Items 31 and 34 (Non-Academic Distinctions, Membership in
        // Associations) are row tables the founder may genuinely have nothing
        // to put in, so N/A is a real answer - prepareForValidation() above
        // turns a blank into "N/A" before this even runs. Anything actually
        // typed is restricted to letters, numbers, spaces and . , & ' - ( ) /.
        $optionalProse = fn (int $max) => [
            'nullable', 'string', 'max:'.$max, 'min:3',
            'regex:/^(n\/a|[\p{L}\p{N}][\p{L}\p{N}\s\.\,\&\'\-\(\)\/]*)$/iu',
            $meaningfulText,
        ];

        // Item 23 is a packed list (see the row-table widget in the view) -
        // one scholarship/honor per line, normally shaped "<name>, <year>" or
        // "<name>, <year>-<year>", e.g. "Dean's Lister, 2016-2018". The
        // "no markup" check above isn't strict enough to catch a symbols-only
        // entry or a bogus year, so each line gets checked on its own here.
        $scholarshipEntry = function ($attribute, $value, $fail) {
            if (! is_string($value)) {
                return;
            }

            $trimmed = trim($value);

            if ($trimmed === '' || strcasecmp($trimmed, 'N/A') === 0) {
                return;
            }

            $currentYear = (int) date('Y');

            foreach (preg_split('/\r\n|\r|\n/', $trimmed) as $line) {
                $line = trim($line);

                if ($line === '') {
                    continue;
                }

                // Letters, numbers, spaces, and . , - ' & / ( ) - the comma is
                // the separator between the name and the year, not part of
                // the name itself, but it's simplest to allow it everywhere
                // in the line and let the "at least one letter" check below
                // catch an entry that's really just digits or symbols.
                $validChars = preg_match('/^[\p{L}\p{N}][\p{L}\p{N}\s\.,\-\'\&\/\(\)]*$/u', $line);
                $hasLetter = preg_match('/\p{L}/u', $line);

                if (! $validChars || ! $hasLetter) {
                    $fail('Please enter a valid scholarship or academic honor.');
                    return;
                }

                $lastComma = strrpos($line, ',');

                if ($lastComma === false) {
                    continue;
                }

                $yearPart = trim(substr($line, $lastComma + 1));

                // Nothing after the last comma, or nothing that even looks
                // like a year (e.g. "Dean's Lister, College of Engineering")
                // - not every entry has a year, so this isn't required on
                // its own.
                if ($yearPart === '' || ! preg_match('/\d/', $yearPart)) {
                    continue;
                }

                if (preg_match('/^(19|20)\d{2}$/', $yearPart)) {
                    if ((int) $yearPart > $currentYear) {
                        $fail('Please enter a valid year or year range.');
                        return;
                    }
                } elseif (preg_match('/^(19|20)\d{2}\s*-\s*(19|20)\d{2}$/', $yearPart)) {
                    [$from, $to] = array_map('trim', explode('-', $yearPart));

                    if ((int) $from > $currentYear || (int) $to > $currentYear) {
                        $fail('Please enter a valid year or year range.');
                        return;
                    }
                } else {
                    $fail('Please enter a valid year or year range.');
                    return;
                }
            }
        };


        // "N/A" or a 4-digit year that isn't later than this year - a real
        // transcript can't have graduated someone yet to come.
        $currentYear = (int) date('Y');
        $year = [
            'required', 'string', 'max:10',
            'regex:/^(n\/a|(19|20)\d{2})$/i',
            function ($attribute, $value, $fail) use ($currentYear) {
                if (is_string($value) && strcasecmp(trim($value), 'N/A') === 0) {
                    return;
                }

                if (is_numeric($value) && (int) $value > $currentYear) {
                    $fail('Year graduated cannot be in the future.');
                }
            },
        ];

        // Items 8-12 (GSIS, Pag-IBIG, PhilHealth, SSS, TIN) and 28-31 (SEC,
        // Business ID, DTI, Business TIN): optional free text - no format,
        // no digit-count check. Only the database column's own length limit
        // (50 / 100 characters) is kept so an overlong value can't fail the save.
        $idText = fn (int $max) => ['nullable', 'string', 'max:'.$max];

        $rules = [
            // The sheet's own overview column. Pre-filled from the Startup
            // Profile's business_description the first time, then independent —
            // editing it here never changes the Profile.
            'startup_overview' => $prose(5000),

            'surname' => $properName(100),
            'first_name' => $properName(100),
            'middle_name' => $name(100),
            'name_extension' => $name(20),
            // The two boxes the founder actually types in, plus the unit each
            // one is in. Digits only - the control strips anything else, and
            // this rejects whatever slips past it.
            'height_input' => ['required', 'string', 'max:20', 'regex:/^\d+(\.\d+)?$/'],
            'height_unit' => ['required', 'in:cm,in,m,ft'],
            'weight_input' => ['required', 'string', 'max:20', 'regex:/^\d+(\.\d+)?$/'],
            'weight_unit' => ['required', 'in:kg,lb'],

            // Derived above, then range-checked so a slipped decimal point
            // (17.5 m, 5 kg) is caught before it reaches the export.
            'height_m' => ['required', 'numeric', 'between:0.5,2.5'],
            'weight_kg' => ['required', 'numeric', 'between:20,500'],
            'blood_type' => $bloodType,
            'gsis_no' => $idText(50),
            'pagibig_no' => $idText(50),
            'philhealth_no' => $idText(50),
            'sss_no' => $idText(50),
            // Item 12 — the founder's own TIN, distinct from Item 31's
            // business_tin. Free text, like the other ID numbers above.
            'tin' => $idText(50),
            'residential_address' => $address(255),
            'permanent_address' => $address(255),
            // Both come from a fixed control now (segmented buttons / a dropdown),
            // so the list itself is the rule - no spelling variants reach the
            // exports. SheetOptions is the single source of truth for both.
            'sex' => ['required', 'string', 'in:'.implode(',', SheetOptions::sexes())],
            'civil_status' => ['required', 'string', 'in:'.implode(',', SheetOptions::civilStatuses())],
            'citizenship_by_birth' => $citizenshipByBirth(100),
            // Optional: blank (no second citizenship) is fine, N/A not needed.
            'citizenship_dual' => array_values(array_diff(['nullable', ...$words(100)], ['required'])),
            'place_of_birth' => $place(150),
            // The picker is capped at the same bounds (see $dobMin / $dobMax in the
            // view). Repeated here because a request can arrive without it.
            'date_of_birth' => ['required', 'date', 'before:2010-01-01', 'after:1900-01-01'],
            'mobile_no' => ['required', 'string', 'max:13', new PhMobile],
            'founder_email' => ['required', 'email', 'max:150'],

            'secondary_school' => $schoolName(150),
            'secondary_degree_course' => $degreeCourse(150),
            'secondary_highest_level_unit' => $highestLevelUnit(100),
            'secondary_year_graduated' => $year,
            'vocational_school' => $schoolName(150),
            'vocational_degree_course' => $degreeCourse(150),
            'vocational_highest_level_unit' => $highestLevelUnit(100),
            'vocational_year_graduated' => $year,
            'college_school' => $schoolName(150),
            'college_degree_course' => $degreeCourse(150),
            'college_highest_level_unit' => $highestLevelUnit(100),
            'college_year_graduated' => $year,
            'graduate_school' => $schoolName(150),
            'graduate_degree_course' => $degreeCourse(150),
            'graduate_highest_level_unit' => $highestLevelUnit(100),
            'graduate_year_graduated' => $year,
            // 500 chars, not $optionalProse's usual 2000 - this is a short
            // packed list, not a paragraph.
            'scholarships_academic_honors' => [
                'nullable', 'string', 'max:500',
                'regex:/^[^<>{}|\\^~]*$/u',
                $scholarshipEntry,
            ],

            'sec_registration' => $idText(100),
            'business_id_number' => $idText(100),
            'dti_registration_number' => $idText(100),
            'business_tin' => $idText(100),
            'non_academic_distinctions' => $optionalProse(2000),
            'membership_associations' => $optionalProse(2000),

            // Stamped by the controller on save — never typed, so it is not
            // validated as user input.
        ];

        // Optional items (see InformationSheet::OPTIONAL_FIELDS): blank is
        // fine, and anything typed - including N/A - still has to pass the
        // field's normal format rule.
        foreach (InformationSheet::OPTIONAL_FIELDS as $field) {
            if (isset($rules[$field])) {
                $rules[$field] = array_map(fn ($rule) => $rule === 'required' ? 'nullable' : $rule, $rules[$field]);
            }
        }

        // 22. Educational Background: each level is optional on its own - a
        // level the founder never reached can simply stay blank (N/A is not
        // needed). But once any of a level's School / Degree / Highest Level
        // cells is filled, all three are required for that level. (At least
        // one level must be filled - see guardAtLeastOneEducationLevel().)
        foreach (['secondary', 'vocational', 'college', 'graduate'] as $level) {
            $cells = ["{$level}_school", "{$level}_degree_course", "{$level}_highest_level_unit"];
            foreach ($cells as $cell) {
                $others = implode(',', array_diff($cells, [$cell]));
                $rules[$cell] = [
                    'nullable',
                    "required_with:{$others}",
                    ...array_values(array_filter($rules[$cell], fn ($rule) => $rule !== 'required')),
                ];
            }
        }

        return $rules;
    }

    public function messages(): array
    {
        $messages = [
            // Personal information
            'surname.required' => 'Please enter your surname.',
            'surname.regex' => 'Surname can only contain letters, spaces, hyphens and periods.',
            'first_name.required' => 'Please enter your first name.',
            'first_name.regex' => 'First name can only contain letters, spaces, hyphens and periods.',
            'middle_name.required' => 'Please enter your middle name or N/A.',
            'middle_name.regex' => 'Middle name can only contain letters, spaces, hyphens and periods.',
            'name_extension.required' => 'Please enter your name extension or N/A.',
            'name_extension.regex' => 'Name extension can only contain letters and periods.',

            'height_input.required' => 'Please enter your height.',
            'height_input.regex' => 'Please enter a valid height.',
            'height_unit.required' => 'Choose cm, in, m or ft for the height.',
            'height_unit.in' => 'Choose cm, in, m or ft for the height.',
            'height_m.required' => 'Please enter your height.',
            'height_m.numeric' => 'Please enter a valid height.',
            'height_m.between' => 'Please enter a valid height.',

            'weight_input.required' => 'Please enter your weight.',
            'weight_input.regex' => 'Please enter a valid weight.',
            'weight_unit.required' => 'Choose kg or lb for the weight.',
            'weight_unit.in' => 'Choose kg or lb for the weight.',
            'weight_kg.required' => 'Please enter your weight.',
            'weight_kg.numeric' => 'Please enter a valid weight.',
            'weight_kg.between' => 'Please enter a valid weight.',
            'blood_type.required' => 'Please enter your blood type or N/A.',

            'residential_address.required' => 'Please enter your residential address.',
            'residential_address.regex' => 'Letters, numbers and . , - # / & only. N/A not accepted.',
            'residential_address.min' => 'Please enter your complete residential address.',
            'permanent_address.regex' => 'Letters, numbers and . , - # / & only. N/A not accepted.',
            'permanent_address.min' => 'Please enter your complete permanent address.',
            'blood_type.regex' => 'E.g. O+, A-, AB+, or N/A.',
            'sex.in' => 'Choose Male or Female.',
            'civil_status.in' => 'Choose one of the listed civil statuses.',
            'citizenship_by_birth.regex' => 'Letters only, e.g. Filipino. N/A not accepted.',
            'citizenship_dual.regex' => 'Use letters only, or N/A if there is none.',
            'place_of_birth.regex' => 'Letters, numbers, commas and periods only. N/A not accepted.',
            'scholarships_academic_honors.regex' => 'Remove the < > { } | \\ ^ ~ characters.',
            'non_academic_distinctions.regex' => 'Please enter a valid distinction, recognition, or eligibility.',
            'non_academic_distinctions.min' => 'Please enter a valid distinction, recognition, or eligibility.',
            'membership_associations.regex' => 'Please enter a valid organization or association.',
            'membership_associations.min' => 'Please enter a valid organization or association.',
            'startup_overview.regex' => 'Startup overview may only use letters, numbers and normal punctuation (. , ! ? ; : \' " - ( ) / & %).',
            'startup_overview.min' => 'Please describe the startup in at least 10 characters.',
            'permanent_address.required' => 'Please enter your permanent address.',
            'sex.required' => 'Please select your sex.',
            'civil_status.required' => 'Please select your civil status.',
            'citizenship_by_birth.required' => 'Please enter your citizenship.',
            'citizenship_dual.required' => 'Please enter your dual citizenship or N/A.',
            'place_of_birth.required' => 'Please enter your place of birth.',
            'date_of_birth.required' => 'Please enter your date of birth.',
            'date_of_birth.date' => 'Please enter a valid date of birth.',
            'date_of_birth.before' => 'Please enter a valid date of birth.',
            'date_of_birth.after' => 'Please enter a valid date of birth.',

            'mobile_no.required' => 'Please enter your mobile number.',
            'founder_email.required' => 'Please enter your email address.',
            'founder_email.email' => 'Please enter a valid email address.',

            // Business registration

            // Long-form entries
            'startup_overview.required' => 'Describe what the startup does.',

            // Declaration
        ];

        // Education table — four levels, four columns each, all worded the same
        // way so the founder is told exactly which row is missing.
        $levels = [
            'secondary' => 'secondary school',
            'vocational' => 'vocational course',
            'college' => 'college',
            'graduate' => 'graduate studies',
        ];

        foreach ($levels as $key => $label) {
            $messages[$key.'_school.required'] = 'Please enter the name of the school or N/A.';
            $messages[$key.'_degree_course.required'] = 'Please enter the degree/course or N/A.';
            $messages[$key.'_highest_level_unit.required'] = 'Please enter the highest level/unit or N/A.';
            $messages[$key.'_year_graduated.required'] = 'Please enter the year graduated or N/A.';
            $messages[$key.'_year_graduated.regex'] = "Year graduated for {$label} must be a 4-digit year, for example 2018.";
            $messages[$key.'_school.regex'] = "The {$label} name can only contain letters, numbers and . - ' & ( ) punctuation.";
            $messages[$key.'_degree_course.regex'] = "The {$label} degree or course can only contain letters, numbers and . - / & ( ) punctuation.";
            $messages[$key.'_highest_level_unit.regex'] = "The {$label} level or units can only contain letters, numbers and . - / ( ) ' punctuation.";
        }

        // Fallback for anything not named above.
        $messages['required'] = 'This field is required. Enter N/A if it does not apply.';

        foreach (['secondary' => 'secondary school', 'vocational' => 'vocational course', 'college' => 'college', 'graduate' => 'graduate studies'] as $level => $label) {
            $messages["{$level}_school.required_with"] = "Please enter the school name for {$label}, or clear this row.";
            $messages["{$level}_degree_course.required_with"] = "Please enter the degree or course for {$label}, or clear this row.";
            $messages["{$level}_highest_level_unit.required_with"] = "Please enter the highest level or units for {$label}, or clear this row.";
        }

        return $messages;
    }

    public function attributes(): array
    {
        return [
            'height_m' => 'height',
            'height_input' => 'height',
            'height_unit' => 'height unit',
            'weight_kg' => 'weight',
            'weight_input' => 'weight',
            'weight_unit' => 'weight unit',
            'gsis_no' => 'GSIS no.',
            'pagibig_no' => 'Pag-IBIG no.',
            'philhealth_no' => 'PhilHealth no.',
            'sss_no' => 'SSS no.',
            'tin' => 'TIN',
            'mobile_no' => 'mobile no.',
            'founder_email' => 'email address',
            'citizenship_by_birth' => 'citizenship by birth',
            'citizenship_dual' => 'dual citizenship',
            'sec_registration' => 'SEC registration',
            'business_tin' => 'business TIN',
            'dti_registration_number' => 'DTI registration number',
            'startup_overview' => 'startup overview',
            'scholarships_academic_honors' => 'scholarships / academic honors',
            'non_academic_distinctions' => 'non-academic distinctions',
            'membership_associations' => 'membership in associations',
        ];
    }
}