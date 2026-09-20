<?php

namespace App\Http\Requests\Admin;

use App\Rules\PersonName;
use App\Rules\PhMobile;
use Illuminate\Foundation\Http\FormRequest;

class StoreCoordinatorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->isAdmin();
    }

    protected function prepareForValidation(): void
    {
        if ($this->hasFile('coordinator_photo') && ! $this->file('coordinator_photo')->isValid()) {
            $this->files->remove('coordinator_photo');
        }
    }

    public function rules(): array
    {
        return [
            // Ordered to match the form's top-to-bottom field layout — see
            // the identical comment in StoreMentorRequest::rules() for why
            // this order (not just grouping) is what the shared toast
            // banner's $errors->first() actually surfaces.
            'first_name' => ['required', 'string', 'max:100', new PersonName],
            'last_name' => ['required', 'string', 'max:100', new PersonName],
            'honorific' => ['required', 'string', 'in:Sir,Ma\'am,Mr.,Ms.,Mrs.,Dr.,Prof.,Atty.,Engr.'],
            // 'email' alone (Laravel's default RFC validation) still lets
            // through addresses with no real domain/TLD at all, like
            // "name@host" — the regex enforces the actual baseline shape we
            // want: something@something.tld, matching the field's
            // "example@email.com" placeholder.
            'email' => ['nullable', 'email', 'max:150', 'regex:/^[^\s@]+@[^\s@]+\.[a-zA-Z]{2,}$/'],
            // Strict PH mobile: exactly 09XXXXXXXXX or +639XXXXXXXXX (PhMobile rule).
            'phone' => ['nullable', new PhMobile],
            'coordinator_photo' => [
                'nullable',
                'max:20480',
                function ($attribute, $value, $fail) {
                    // Laravel's built-in "image" rule content-sniffs the MIME
                    // type (via Symfony's finfo-based getMimeType()) and
                    // rejects anything that doesn't map cleanly to a known
                    // image type. Some perfectly normal PNGs/JPEGs — depending
                    // on the phone, app, or export tool that produced them —
                    // get sniffed as an alternate/legacy MIME string, so
                    // "image" silently rejected them even though nothing was
                    // actually wrong with the file. Trusting the file's own
                    // extension instead avoids that false rejection;
                    // CompressesImages separately guards against a genuinely
                    // unreadable image at the processing step.
                    if (! $value instanceof \Illuminate\Http\UploadedFile) {
                        return;
                    }
                    $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
                    $ext = strtolower($value->getClientOriginalExtension());

                    if (! in_array($ext, $allowed, true)) {
                        $fail('That file is not a supported image format (JPG, PNG, GIF, or WEBP).');
                    }
                },
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'honorific.required' => 'Please select an honorific.',
            'email.email' => 'Please enter a valid email address, e.g. example@email.com.',
            'email.regex' => 'Please enter a valid email address, e.g. example@email.com.',
            'coordinator_photo.max' => 'Photo must be 20MB or smaller.',
        ];
    }
}