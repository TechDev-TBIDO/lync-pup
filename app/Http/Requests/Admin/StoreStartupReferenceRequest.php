<?php

namespace App\Http\Requests\Admin;

use App\Rules\PersonName;
use App\Rules\PhMobile;
use Illuminate\Foundation\Http\FormRequest;

class StoreStartupReferenceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150', new PersonName],
            'contact' => ['nullable', 'string', 'max:13', new PhMobile],
            'email' => ['nullable', 'email', 'max:150'],
            'address' => ['nullable', 'string', 'max:255'],
        ];
    }
}
