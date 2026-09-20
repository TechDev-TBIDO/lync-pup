<?php

namespace App\Http\Requests\Startup;

use App\Support\SheetOptions;
use Illuminate\Foundation\Http\FormRequest;

class StoreTeamMemberRequest extends FormRequest
{
    use SheetRowRules;

    public function authorize(): bool
    {
        return $this->user()->isStartup();
    }

    /**
     * Section II, item 24 of the Information Sheet. Every column is required —
     * see SheetRowRules for why, and for the shared column shapes.
     */
    public function rules(): array
    {
        return [
            // These use their own dedicated shapes (rowFullName,
            // rowDesignation, rowAddress, rowCitizenship) rather than the
            // rowName/rowText/rowWords shared with References, Incubation
            // and L&D - a Core Team member is a real, named person, so
            // (unlike those other rows) none of these accept N/A here.
            // full_name specifically uses rowFullName, not the looser
            // rowPersonName: the column header itself asks for "Surname,
            // Firstname, Middle Name, Ext", so a bare "Juan Dela Cruz"
            // with no comma is rejected here, the same as it already was
            // on UpdateTeamMemberDetailsRequest (editing a saved row) —
            // this is the endpoint the Information Sheet's own "+ Add
            // Entry" button posts to, so both create and edit now agree.
            'full_name' => $this->rowFullName(150),
            'designation' => $this->rowDesignation(100),
            'phone' => $this->rowPhone(),
            'address' => $this->rowAddress(255),
            'date_of_birth' => ['required', 'date', 'before:2010-01-01', 'after:1900-01-01'],
            // Laravel's own 'email' rule is deliberately permissive about
            // RFC-legal-but-unusual addresses (no TLD, quoted local parts,
            // IP-literal domains...). The regex on top pins it down to the
            // ordinary "name@domain.tld" shape a founder actually expects
            // to be accepted here — a proper @ and a dotted domain ending
            // in at least two letters (gmail.com, outlook.com, .edu.ph,
            // etc. — not hardcoded to .com only, since legitimate school
            // and government addresses rarely end in exactly that).
            'email' => ['required', 'string', 'max:150', 'email', 'regex:/^[^\s@]+@[^\s@]+\.[A-Za-z]{2,}$/'],
            'citizenship' => $this->rowCitizenship(100),
            // Both are dropdowns on the sheet, so the list is the rule.
            'sex' => ['required', 'string', 'in:'.implode(',', SheetOptions::sexes())],
            'civil_status' => ['required', 'string', 'in:'.implode(',', SheetOptions::civilStatuses())],
        ];
    }

    public function messages(): array
    {
        return $this->rowMessages([
            'full_name.required' => 'Please enter the name.',
            'full_name.regex' => 'Format: Surname, Firstname — letters only, no numbers.',
            'designation.required' => 'Please enter the designation.',
            'phone.required' => 'Please enter the phone number.',
            'address.required' => 'Please enter the address.',
            'address.regex' => 'Please enter a valid address.',
            'address.min' => 'Please enter the complete address.',
            'date_of_birth.required' => 'Select a date of birth.',
            'date_of_birth.before' => 'Date of birth must be 2009 or earlier.',
            'email.required' => 'Please enter an email address.',
            'email.email' => 'Please enter a valid email, e.g. name@email.com.',
            'email.regex' => 'Please enter a valid email, e.g. name@email.com.',
            'citizenship.required' => 'Please enter the citizenship.',
            'citizenship.regex' => 'Please enter a valid citizenship.',
            'citizenship.min' => 'Please enter a valid citizenship.',
            'sex.required' => 'Please select a sex.',
            'sex.in' => 'Choose Male or Female.',
            'civil_status.required' => 'Please select a civil status.',
            'civil_status.in' => 'Choose one of the listed civil statuses.',
        ]);
    }
}
