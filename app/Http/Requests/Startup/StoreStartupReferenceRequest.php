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
     * Item 35 of the Information Sheet - optional as a whole table: every
     * column may be blank (or N/A for name/address). Anything typed must still
     * be a real name, mobile number, email or address.
     */
    public function rules(): array
    {
        return [
            // Item 35 is optional as a whole table - see SheetRowRules::optionalText().
            'name' => $this->optionalText(['string', 'max:150', new \App\Rules\PersonName]),
            'contact' => ['nullable', 'string', 'max:13', new \App\Rules\PhMobile],
            'email' => ['nullable', 'email', 'max:150'],
            'address' => $this->optionalText(array_values(array_filter(
                $this->rowAddress(255),
                fn ($rule) => $rule !== 'required'
            ))),
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
