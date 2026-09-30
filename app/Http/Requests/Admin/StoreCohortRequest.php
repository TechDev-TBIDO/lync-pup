<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreCohortRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->isAdmin();
    }

    public function rules(): array
    {
        // Also used (unmodified) by UpdateCohortRequest, which extends this
        // class. Cohorts no longer take a user-entered name — free-text
        // labels caused mismatches across the app (Startup::cohort_number,
        // Information Sheets, etc. all key off the real sequential number).
        // The number itself is auto-assigned by the controller and is
        // never submitted or editable here.
        return [
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'description' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'start_date.required' => 'Please enter a start date.',
            'end_date.required' => 'Please enter an end date.',
            'end_date.after_or_equal' => 'End date must be on or after the start date.',
        ];
    }
}
