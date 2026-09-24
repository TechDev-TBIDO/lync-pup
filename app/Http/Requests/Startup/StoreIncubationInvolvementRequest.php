<?php

namespace App\Http\Requests\Startup;

use Illuminate\Foundation\Http\FormRequest;

class StoreIncubationInvolvementRequest extends FormRequest
{
    use SheetRowRules;

    public function authorize(): bool
    {
        return $this->user()->isStartup();
    }

    /**
     * Section III of the Information Sheet. A row here is optional at the
     * "should this row exist" level - the founder simply doesn't add one
     * when there's no incubation involvement to report (a blank "add new"
     * row is dropped before it ever reaches here, see
     * submitInfoSheetForms()) - but once a row is added, every cell in it
     * is a real answer, so unlike SheetRowRules' shared column shapes, N/A
     * is not accepted anywhere in this row.
     */
    public function rules(): array
    {
        return [
            // Letters, numbers, spaces, and . , - / & ( ) - and letters
            // have to actually outnumber digits (meaningfulText()), so
            // neither "1234" nor "1234567890a" can pass as an organization
            // name. min:10 on top of that: a combined name-and-address is
            // never genuinely this short.
            'organization_name_address' => $this->optionalText([
                'string', 'max:255', 'min:10',
                'regex:/^[\p{L}\p{N}][\p{L}\p{N}\s\.\,\-\/\&\(\)]*$/iu',
                $this->meaningfulText('Please enter a valid organization name and address.'),
            ]),
            // Item 25 is optional as a whole table - see SheetRowRules::optionalText().
            'date_from' => ['nullable', 'date', 'after:1900-01-01'],
            'date_to' => $this->optionalDateTo(),
            'number_of_hours' => $this->optionalHours(),
            // Free-form description - only markup characters are blocked,
            // same as the sheet's other prose fields - but letters still
            // have to outnumber digits (meaningfulText()), and min:5 rules
            // out a short junk answer like "h1" on its own.
            // Required once an organization is entered (not N/A) - the table
            // itself stays optional, so no asterisk on the page.
            'incubation_program_focus' => [\Illuminate\Validation\Rule::requiredIf(fn () => ($org = trim((string) $this->input('organization_name_address'))) !== '' && strcasecmp($org, 'N/A') !== 0), ...$this->optionalText([
                'string', 'max:255', 'min:5',
                'regex:/^[^<>{}|\\^~]*$/u',
                $this->meaningfulText('Please enter a valid incubation program or focus.'),
            ])],
        ];
    }

    public function messages(): array
    {
        return $this->rowMessages([
            'organization_name_address.required' => 'Please enter the organization name and address.',
            'organization_name_address.regex' => 'Please enter a valid organization name and address.',
            'organization_name_address.min' => 'Please enter the complete organization name and address.',
            'date_from.required' => 'Please enter the start date.',
            'date_from.date' => 'Please enter a valid start date.',
            'date_from.after' => 'Please enter a valid start date.',
            'date_to.required' => 'Please enter the end date.',
            'date_to.date' => 'Please enter a valid end date.',
            'date_to.after_or_equal' => 'End date must be on or after the start date.',
            'number_of_hours.required' => 'Please enter the number of hours.',
            'number_of_hours.regex' => 'Enter a whole number of hours, N/A, or leave it blank.',
            'incubation_program_focus.required' => 'Please enter the incubation program or focus.',
            'incubation_program_focus.regex' => 'Please enter a valid incubation program or focus.',
            'incubation_program_focus.min' => 'Please enter a valid incubation program or focus.',
            // Catch-all for this row, replacing SheetRowRules' generic
            // "Type N/A if it does not apply" fallback - nothing in this
            // row accepts N/A, so that wording never applies here.
            'required' => 'Please complete all required information for this organization.',
        ]);
    }
}
