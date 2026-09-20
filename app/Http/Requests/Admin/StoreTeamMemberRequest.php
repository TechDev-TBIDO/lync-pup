<?php

namespace App\Http\Requests\Admin;

use App\Rules\PersonName;
use App\Rules\PhMobile;
use Illuminate\Foundation\Http\FormRequest;

class StoreTeamMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    public function rules(): array
    {
        return [
            'full_name' => ['required', 'string', 'max:150', new PersonName],
            'designation' => ['nullable', 'string', 'max:100', new PersonName],
            'phone' => ['nullable', 'string', 'max:13', new PhMobile],
            'address' => ['nullable', 'string', 'max:255'],
            'date_of_birth' => ['nullable', 'date'],
            'email' => ['nullable', 'email', 'max:150'],
            'citizenship' => ['nullable', 'string', 'max:100'],
            'sex' => ['nullable', 'string', 'max:20'],
            'civil_status' => ['nullable', 'string', 'max:30'],
        ];
    }
}
