<?php

namespace App\Http\Requests\Startup;

use Illuminate\Foundation\Http\FormRequest;

class StoreStartupReferenceRequest extends FormRequest
{
    use SheetRowRules;

    public function authorize(): bool
    {
        return $this->user()->isStartup();
    }

    /**
     * Item 35 of the Information Sheet. Every column is required. A
     * reference is a real, named person, so - like Core Team - name and
     * address use the dedicated shapes below rather than the
     * rowName/rowText shared with rows that may genuinely be N/A.
     */
    public function rules(): array
    {
        return [
            'name' => $this->rowPersonName(150),
            'contact' => $this->rowPhone(),
            'email' => ['required', 'email', 'max:150'],
            'address' => $this->rowAddress(255),
        ];
    }

    public function messages(): array
    {
        return $this->rowMessages([
            'name.required' => 'Enter the reference\'s full name.',
            'contact.required' => 'Enter the reference\'s mobile number.',
            'email.required' => 'Enter the reference\'s email address.',
            'email.email' => 'Please enter a valid email address.',
            'address.required' => 'Enter the reference\'s address.',
            'address.regex' => 'Please enter a valid address.',
            'address.min' => 'Please enter the complete address.',
        ]);
    }
}
