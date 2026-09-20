<?php

namespace App\Http\Requests\Admin;

use App\Rules\PersonName;
use App\Rules\PhMobile;
use App\Support\SheetOptions;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class UpdateInformationSheetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
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
            // For TBIDO only. portfolio_manager/cohort_no are deliberately
            // NOT upper-cased here — they're now dropdowns sourced straight
            // from Coordinator::name / Cohort::display_label (see
            // admin/information-sheets/show.blade.php's $selectField()), so
            // upper-casing them would make the saved value stop matching any
            // dropdown option's real casing the next time the page loads.
            'endorsed_by',
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

        // Items 23, 31 and 34 are optional row tables. Emptying one is a real
        // answer - "nothing to declare" - so it is stored as the N/A the paper
        // form asks for, rather than as a blank that reads as "unanswered" in
        // the exports. Only touched when the field was actually submitted, so
        // a partial request cannot wipe an existing entry. Mirrors
        // App\Http\Requests\Startup\UpdateInformationSheetRequest.
        $blankIsNotApplicable = [
            'scholarships_academic_honors',
            'non_academic_distinctions',
            'membership_associations',
        ];

        foreach ($blankIsNotApplicable as $field) {
            if ($this->has($field) && trim((string) $this->input($field)) === '') {
                $this->merge([$field => 'N/A']);
            }
        }

        // 5 & 6. The reviewer types a number and picks its unit; the sheet stores
        // metres and kilograms, which is what the column names promise and what
        // the PDF prints. Converting here means every rule below - and
        // blankedFields() - only ever sees the canonical value. Mirrors
        // App\Http\Requests\Startup\UpdateInformationSheetRequest.
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

            // A blank, a non-number or an unknown unit is passed through
            // untouched so the rules below produce the message, rather than
            // silently storing a zero.
            $this->merge([
                $target => ($raw === '' || ! is_numeric($raw) || $factor === null)
                    ? $raw
                    : (string) round((float) $raw * $factor, 2),
            ]);
        }
    }

    /**
     * Two independent cross-field checks that no single column's own rule
     * can express on its own — mirrors the founder side's guards exactly
     * (App\Http\Requests\Startup\UpdateInformationSheetRequest), so a sheet
     * an admin edits is held to the same cross-field rules as one the
     * founder edits.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $this->guardFilledFieldsStayFilled($validator);
            $this->guardEducationalBackgroundConsistency($validator);
        });
    }

    /**
     * "Once filled, never blank": a required field that already holds a saved
     * answer can be replaced but not cleared - on a draft Save as well as a
     * Submit, whether or not an evaluation is scheduled. A draft Save only
     * relaxes 'required' for fields that were never filled in; it must not
     * become a way to empty an answer that is already on record. See
     * InformationSheet::blankedFields().
     */
    private function guardFilledFieldsStayFilled(Validator $validator): void
    {
        $startup = $this->route('startup');
        $sheet = $startup?->informationSheet;

        if (! $sheet) {
            return;
        }

        // Only the required fields. height_m / weight_kg are the derived
        // values behind the height/weight boxes, so a blanked box is caught
        // here under the field the page anchors its error to.
        $required = collect($this->rules())
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
     * Item 22, Educational Background: "Name of School" is the switch for
     * its whole row. N/A there means the founder never reached that level,
     * so the other three columns must be N/A too, and vice versa — see the
     * matching guard and comment on the founder side
     * (App\Http\Requests\Startup\UpdateInformationSheetRequest).
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
     * Mirrors the founder's rule set exactly
     * (App\Http\Requests\Startup\UpdateInformationSheetRequest::rules()) for
     * every field the founder can also edit, so a sheet an admin saves can
     * never diverge from what the founder's own side would accept — same
     * character classes, same exact-length ID checks, same SEC/DTI/TIN
     * formats, same startup-overview minimum. Only the "For TBIDO Only"
     * fields below (portfolio manager, cohort no., endorsement) and the
     * fields with no edit UI on this page (business_description,
     * target_market, problem_statement, solution_offered,
     * director_approval_date) are admin-specific.
     */
    public function rules(): array
    {
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

        // A Philippine government/business ID number: digits and hyphens
        // only - no spaces - that must total an exact digit count once the
        // hyphens are stripped out. Each agency's number has a fixed,
        // well-known length: GSIS 11, Pag-IBIG 12, PhilHealth 12, SSS 10,
        // business TIN 12 (matching the "123-456-789-000" placeholder).
        $govId = fn (int $digits, string $label) => [
            'required', 'string', 'max:20',
            'regex:/^(n\/a|[0-9\-]+)$/i',
            function ($attribute, $value, $fail) use ($digits, $label) {
                if (is_string($value) && strcasecmp(trim($value), 'N/A') === 0) {
                    return;
                }

                $count = strlen(preg_replace('/[^0-9]/', '', (string) $value));

                if ($count !== $digits) {
                    $fail("Please enter a valid {$label}.");
                }
            },
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
            'required', 'string', 'max:'.$max, 'min:50',
            'regex:/^[\p{L}\p{N}][\p{L}\p{N}\s\.\,\!\?\'\-\(\)]*$/u',
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

        // Item 23 is a packed list (see the row-table widget in the founder
        // view) - one scholarship/honor per line, normally shaped
        // "<name>, <year>" or "<name>, <year>-<year>", e.g. "Dean's Lister,
        // 2016-2018". The "no markup" check above isn't strict enough to
        // catch a symbols-only entry or a bogus year, so each line gets
        // checked on its own here.
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

        // Registration codes are deliberately mixed — "CS201812345",
        // "DTI-0054321", "07000123". Letters, digits and hyphens only - no
        // spaces, no slash, no period - so a junk entry (or a code typed with
        // stray punctuation) can't pass as one. See the founder side's own
        // $code for the full reasoning.
        $code = fn (int $max) => [
            'required', 'string', 'max:'.$max,
            'regex:/^(n\/a|[A-Za-z0-9][A-Za-z0-9\-]*)$/i',
            function ($attribute, $value, $fail) {
                if (! is_string($value) || strcasecmp(trim($value), 'N/A') === 0) {
                    return;
                }

                $trimmed = trim($value);

                if (strlen($trimmed) < 7) {
                    $fail('Please enter a valid ID number, or N/A.');
                    return;
                }

                $stripped = str_replace('-', '', $trimmed);

                if ($stripped !== '' && preg_match('/^(.)\1*$/u', $stripped)) {
                    $fail('Please enter a valid ID number, or N/A.');
                }
            },
        ];

        // SEC registration numbers are a confirmed letter+digit mix, e.g.
        // "CS202412345" - so on top of $code's length and repeated-character
        // checks, a real one must contain at least one letter AND at least
        // one digit.
        $secCode = fn (int $max) => [
            'required', 'string', 'max:'.$max,
            'regex:/^(n\/a|[A-Za-z0-9][A-Za-z0-9\-]*)$/i',
            function ($attribute, $value, $fail) {
                if (! is_string($value) || strcasecmp(trim($value), 'N/A') === 0) {
                    return;
                }

                $trimmed = trim($value);

                if (strlen($trimmed) < 7) {
                    $fail('Please enter a valid SEC registration number, or N/A.');
                    return;
                }

                $stripped = str_replace('-', '', $trimmed);

                if ($stripped !== '' && preg_match('/^(.)\1*$/u', $stripped)) {
                    $fail('Please enter a valid SEC registration number, or N/A.');
                    return;
                }

                if (! preg_match('/[A-Za-z]/', $stripped) || ! preg_match('/[0-9]/', $stripped)) {
                    $fail('SEC registration number must contain both letters and numbers, e.g. CS202412345.');
                }
            },
        ];

        // Cohort no. has no founder-side counterpart - it is a TBIDO-only
        // field and legitimately holds a space ("Cohort 3"), unlike the
        // SEC/DTI/Business-ID codes above, so it keeps its own, looser shape:
        // letters, digits, spaces and . - / punctuation, no length/repeated-
        // character checks.
        $cohortCode = fn (int $max) => [
            'required', 'string', 'max:'.$max,
            'regex:/^(n\/a|[A-Za-z0-9][A-Za-z0-9\s\-\/\.]*)$/i',
        ];

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

        return [
            // The sheet's own overview column, separate from the Startup
            // Profile's business_description (which this page no longer writes).
            'startup_overview' => $prose(5000),

            'surname' => $properName(100),
            'first_name' => $properName(100),
            'middle_name' => $name(100),
            'name_extension' => $name(20),
            // The two boxes actually typed into, plus the unit each one is in.
            // Digits only - the control strips anything else, and this rejects
            // whatever slips past it.
            'height_input' => ['required', 'string', 'max:20', 'regex:/^\d+(\.\d+)?$/'],
            'height_unit' => ['required', 'in:cm,in,m,ft'],
            'weight_input' => ['required', 'string', 'max:20', 'regex:/^\d+(\.\d+)?$/'],
            'weight_unit' => ['required', 'in:kg,lb'],

            // Derived in prepareForValidation(), then range-checked so a slipped
            // decimal point (17.5 m, 5 kg) is caught before it reaches the export.
            'height_m' => ['required', 'numeric', 'between:0.5,2.5'],
            'weight_kg' => ['required', 'numeric', 'between:20,500'],
            'blood_type' => $bloodType,
            'gsis_no' => $govId(11, 'GSIS ID number'),
            'pagibig_no' => $govId(12, 'PAG-IBIG number'),
            'philhealth_no' => $govId(12, 'PhilHealth number'),
            'sss_no' => $govId(10, 'SSS number'),
            // Item 12 — the founder's own TIN, distinct from Item 31's
            // business_tin.
            'tin' => $govId(12, 'TIN'),
            'residential_address' => $address(255),
            'permanent_address' => $address(255),
            // Both come from a fixed control now (segmented buttons / a dropdown),
            // so the list itself is the rule - no spelling variants reach the
            // exports. SheetOptions is the single source of truth for both.
            'sex' => ['required', 'string', 'in:'.implode(',', SheetOptions::sexes())],
            'civil_status' => ['required', 'string', 'in:'.implode(',', SheetOptions::civilStatuses())],
            'citizenship_by_birth' => $citizenshipByBirth(100),
            'citizenship_dual' => $words(100),
            'place_of_birth' => $place(150),
            // The picker is capped at the same bounds on the founder side.
            // Repeated here because a request can arrive without it.
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

            'sec_registration' => $secCode(100),
            'business_id_number' => $code(100),
            'dti_registration_number' => $govId(12, 'DTI registration number'),
            'business_tin' => $govId(12, 'business TIN'),
            'non_academic_distinctions' => $optionalProse(2000),
            'membership_associations' => $optionalProse(2000),

            // Written by the founder's Startup Profile; kept here so an
            // existing value survives a save from this page.
            'business_description' => ['nullable', 'string', 'max:5000'],

            // No edit UI on this page yet, so these stay optional.
            'target_market' => ['nullable', 'string'],
            'problem_statement' => ['nullable', 'string'],
            'solution_offered' => ['nullable', 'string'],

            // Declaration & Endorsement (TBIDO-side fields — never editable by the founder)
            'portfolio_manager' => array_merge($words(150), [new PersonName]),
            'cohort_no' => $cohortCode(20),
            'endorsed_by' => array_merge($words(150), [new PersonName]),
            'endorsement_date' => ['required', 'date'],

            // Filled in after the director signs the printed copy, so it stays
            // optional while everything else on the form is required.
            'director_approval_date' => ['nullable', 'date'],
        ];
    }

    public function messages(): array
    {
        $messages = [
            // Personal information — same wording as the founder side
            // (App\Http\Requests\Startup\UpdateInformationSheetRequest), so a
            // rejected value reads the same regardless of who is editing.
            'surname.required' => 'Please enter the surname.',
            'surname.regex' => 'Surname can only contain letters, spaces, hyphens and periods.',
            'first_name.required' => 'Please enter the first name.',
            'first_name.regex' => 'First name can only contain letters, spaces, hyphens and periods.',
            'middle_name.required' => 'Please enter the middle name or N/A.',
            'middle_name.regex' => 'Middle name can only contain letters, spaces, hyphens and periods.',
            'name_extension.required' => 'Please enter the name extension or N/A.',
            'name_extension.regex' => 'Name extension can only contain letters and periods.',

            'height_input.required' => 'Please enter the height.',
            'height_input.regex' => 'Please enter a valid height.',
            'height_unit.required' => 'Choose cm, in, m or ft for the height.',
            'height_unit.in' => 'Choose cm, in, m or ft for the height.',
            'height_m.required' => 'Please enter the height.',
            'height_m.numeric' => 'Please enter a valid height.',
            'height_m.between' => 'Please enter a valid height.',

            'weight_input.required' => 'Please enter the weight.',
            'weight_input.regex' => 'Please enter a valid weight.',
            'weight_unit.required' => 'Choose kg or lb for the weight.',
            'weight_unit.in' => 'Choose kg or lb for the weight.',
            'weight_kg.required' => 'Please enter the weight.',
            'weight_kg.numeric' => 'Please enter a valid weight.',
            'weight_kg.between' => 'Please enter a valid weight.',
            'blood_type.required' => 'Please enter the blood type or N/A.',

            'gsis_no.required' => 'Please enter the GSIS ID number or N/A.',
            'gsis_no.regex' => 'Please enter a valid GSIS ID number, or N/A.',
            'pagibig_no.required' => 'Please enter the PAG-IBIG number or N/A.',
            'pagibig_no.regex' => 'Please enter a valid PAG-IBIG number, or N/A.',
            'philhealth_no.required' => 'Please enter the PhilHealth number or N/A.',
            'philhealth_no.regex' => 'Please enter a valid PhilHealth number, or N/A.',
            'sss_no.required' => 'Please enter the SSS number or N/A.',
            'sss_no.regex' => 'Please enter a valid SSS number, or N/A.',
            'tin.required' => 'Please enter the TIN or N/A.',
            'tin.regex' => 'Please enter a valid TIN, or N/A.',

            'residential_address.required' => 'Please enter the residential address.',
            'residential_address.regex' => 'Letters, numbers and . , - # / & only. N/A not accepted.',
            'residential_address.min' => 'Please enter the complete residential address.',
            'permanent_address.regex' => 'Letters, numbers and . , - # / & only. N/A not accepted.',
            'permanent_address.min' => 'Please enter the complete permanent address.',
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
            'startup_overview.regex' => 'Please enter a valid startup overview.',
            'startup_overview.min' => 'The startup overview must be at least 50 characters.',
            'permanent_address.required' => 'Please enter the permanent address.',
            'sex.required' => 'Choose Male or Female.',
            'civil_status.required' => 'Choose a civil status.',
            'citizenship_by_birth.required' => 'Please enter the citizenship.',
            'citizenship_dual.required' => 'Please enter the dual citizenship or N/A.',
            'place_of_birth.required' => 'Please enter the place of birth.',
            'date_of_birth.required' => 'Select the date of birth.',
            'date_of_birth.date' => 'Please enter a valid date of birth.',
            'date_of_birth.before' => 'Date of birth must be 2009 or earlier.',
            'date_of_birth.after' => 'Please enter a valid date of birth.',

            'mobile_no.required' => 'Please enter a mobile number.',
            'founder_email.required' => 'Please enter an email address.',
            'founder_email.email' => 'Please enter a valid email address.',

            // Business registration
            'sec_registration.required' => 'Enter the SEC registration number, or N/A if not registered.',
            'business_id_number.required' => 'Enter the business ID number, or N/A if there is none.',
            'dti_registration_number.required' => 'Enter the DTI registration number, or N/A if not registered.',
            'business_tin.required' => 'Enter the business TIN, or N/A if there is none.',
            'business_tin.regex' => 'Digits and hyphens only, or N/A.',
            'sec_registration.regex' => 'Please enter a valid SEC registration number.',
            'business_id_number.regex' => 'Please enter a valid business ID number.',
            'dti_registration_number.regex' => 'Digits only, or N/A.',

            // Long-form entries
            'startup_overview.required' => 'Describe what the startup does.',

            // Declaration & Endorsement — TBIDO-only, no founder-side counterpart.
            'cohort_no.regex' => 'Cohort no. can only contain letters, numbers and spaces, for example Cohort 3.',
            'portfolio_manager.regex' => 'Use letters only.',
            'endorsed_by.regex' => 'Use letters only.',
        ];

        // Education table — four levels, four columns each, all worded the same
        // way (and identically to the founder side) so whoever is filling in
        // the sheet is told exactly which row is missing.
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

        $messages['portfolio_manager.required'] = 'Enter the assigned portfolio manager.';
        $messages['cohort_no.required'] = 'Enter the cohort number, for example Cohort 3.';
        $messages['endorsed_by.required'] = 'Enter who endorsed this startup.';
        $messages['endorsement_date.required'] = 'Select the endorsement date.';
        $messages['endorsement_date.date'] = 'Enter the endorsement date as a valid date.';

        // Fallback for anything not named above.
        $messages['required'] = 'This field is required. Enter N/A if it does not apply.';

        return $messages;
    }

    public function attributes(): array
    {
        return [
            'height_m' => 'height',
            'weight_kg' => 'weight',
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
            'cohort_no' => 'cohort no.',
        ];
    }
}
