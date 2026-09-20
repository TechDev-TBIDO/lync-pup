<?php

namespace App\Http\Requests\Admin;

use App\Rules\PersonName;
use App\Rules\PhMobile;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;

class StoreMentorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->isAdmin();
    }

    public function rules(): array
    {
        return [
            // Ordered to match the form's top-to-bottom field layout: when
            // multiple required fields are blank, Laravel's validator (and
            // therefore $errors->first(), which the shared toast banner in
            // admin.blade.php uses) surfaces whichever field was validated
            // first — so this order must mirror the visual order, not just
            // be grouped by "identity fields first", or a lower field's
            // error (e.g. Honorifics) wins the toast even though First Name
            // is what the user actually sees blank at the top of the form.
            'first_name' => ['required', 'string', 'max:100', new PersonName],
            'last_name' => ['required', 'string', 'max:100', new PersonName],
            'honorific' => ['required', 'string', 'in:Mr.,Ms.,Mrs.,Dr.,Prof.,Atty.,Engr.'],
            'specialization' => ['required', 'string', 'in:Engineering,Business,Marketing,Legal,Finance,Technology,Others'],
            'specialization_other' => ['nullable', 'required_if:specialization,Others', 'string', 'max:150'],
            // 'email' alone (Laravel's default RFC validation) still lets
            // through addresses with no real domain/TLD at all, like
            // "name@host" — the regex enforces the actual baseline shape we
            // want: something@something.tld, matching the field's
            // "example@email.com" placeholder.
            'contact_email' => ['nullable', 'email', 'max:150', 'regex:/^[^\s@]+@[^\s@]+\.[a-zA-Z]{2,}$/'],
            // Strict PH mobile: exactly 09XXXXXXXXX or +639XXXXXXXXX (PhMobile rule).
            'contact_number' => ['nullable', new PhMobile],
            'mentor_photo' => [
                'nullable',
                'max:20480', // 20MB raw upload cap; gets compressed to ~2MB on save
                function ($attribute, $value, $fail) {
                    // A file that's present but !isValid() usually means the
                    // web server itself rejected it (e.g. it exceeded
                    // upload_max_filesize/post_max_size) before Laravel's
                    // other rules even got to look at it. Surface that
                    // clearly instead of silently dropping the photo.
                    if ($value instanceof UploadedFile && ! $value->isValid()) {
                        $fail('That photo could not be uploaded — it may be too large for the server to accept. Please try a smaller file.');
                    }
                },
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
                    if (! $value instanceof UploadedFile) {
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
            'specialization.required' => 'Please select an expertise.',
            'specialization_other.required_if' => 'Please type the specific expertise.',
            'contact_email.email' => 'Please enter a valid email address, e.g. example@email.com.',
            'contact_email.regex' => 'Please enter a valid email address, e.g. example@email.com.',
            'mentor_photo.max' => 'Photo must be 20MB or smaller.',
        ];
    }
}