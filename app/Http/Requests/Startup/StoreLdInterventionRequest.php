<?php

namespace App\Http\Requests\Startup;

use Illuminate\Foundation\Http\FormRequest;

class StoreLdInterventionRequest extends FormRequest
{
    use SheetRowRules;

    public function authorize(): bool
    {
        return $this->user()->isStartup();
    }

    /**
     * Section IV of the Information Sheet - same treatment as Section III
     * (StoreIncubationInvolvementRequest): a row here is optional at the
     * "should this row exist" level - the founder simply doesn't add one
     * when there's no training/program to report (a blank "add new" row is
     * dropped before it ever reaches here, see submitInfoSheetForms()) -
     * but once a row is added, every cell in it is a real answer, so unlike
     * SheetRowRules' shared column shapes, N/A is not accepted anywhere in
     * this row.
     */
    public function rules(): array
    {
        return [
            // Letters, numbers, spaces, and . , - / & ( ) - and letters
            // have to actually outnumber digits (meaningfulText()), so
            // neither "1234" nor "1234567890a" can pass as a title. min:5
            // rules out a short junk answer clearing the letter check on
            // a technicality.
            'title' => $this->optionalText([
                'string', 'max:255', 'min:5',
                'regex:/^[\p{L}\p{N}][\p{L}\p{N}\s\.\,\-\/\&\(\)]*$/iu',
                $this->meaningfulText('Please enter a valid title.'),
            ]),
            // Item 26 is optional as a whole table - see SheetRowRules::optionalText().
            'date_from' => ['nullable', 'date', 'after:1900-01-01'],
            'date_to' => $this->optionalDateTo(),
            'number_of_hours' => $this->optionalHours(),
            // Free-form text - only markup characters are blocked, same as
            // the sheet's other prose fields - but letters still have to
            // outnumber digits (meaningfulText()), and min:5 rules out a
            // short junk answer on its own.
            'conducted_sponsored_by' => $this->optionalText([
                'string', 'max:255', 'min:5',
                'regex:/^[^<>{}|\\^~]*$/u',
                $this->meaningfulText('Please enter a valid entry for who conducted or sponsored the program.'),
            ]),
        ];
    }

    public function messages(): array
    {
        return $this->rowMessages([
            'title.required' => 'Please enter the title of the training or program.',
            'title.regex' => 'Please enter a valid title.',
            'title.min' => 'Please enter a valid title.',
            'date_from.required' => 'Please enter the start date.',
            'date_from.date' => 'Please enter a valid start date.',
            'date_from.after' => 'Please enter a valid start date.',
            'date_to.required' => 'Please enter the end date.',
            'date_to.date' => 'Please enter a valid end date.',
            'date_to.after_or_equal' => 'End date must be on or after the start date.',
            'number_of_hours.required' => 'Please enter the number of hours.',
            'number_of_hours.regex' => 'Enter a whole number of hours, N/A, or leave it blank.',
            'conducted_sponsored_by.required' => 'Please enter who conducted or sponsored the program.',
            'conducted_sponsored_by.regex' => 'Please enter a valid entry for who conducted or sponsored the program.',
            'conducted_sponsored_by.min' => 'Please enter a valid entry for who conducted or sponsored the program.',
            // Catch-all for this row, replacing SheetRowRules' generic
            // "Type N/A if it does not apply" fallback - nothing in this
            // row accepts N/A, so that wording never applies here.
            'required' => 'Please complete all required information for this training or program.',
        ]);
    }
}
