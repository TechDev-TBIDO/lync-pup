<?php

namespace App\Http\Requests\Startup;

use Illuminate\Foundation\Http\FormRequest;

class StoreRoadblockRequest extends FormRequest
{
    public const MAX_FILES = 5;

    public const MAX_KILOBYTES = 10240;

    public const ALLOWED_EXTENSIONS = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'jpg', 'jpeg', 'png', 'mp4'];

    /**
     * Human-readable reasons for any supporting file that got dropped in
     * prepareForValidation(), keyed by nothing in particular — just a flat
     * list of "\"name.ext\": reason" strings the controller can flash back
     * to the founder. A rejected/failed file should never block the ones
     * that ARE fine (or the rest of the roadblock) from being submitted.
     */
    public array $skippedFiles = [];

    public function authorize(): bool
    {
        return $this->user()?->isStartup() ?? false;
    }

    protected function prepareForValidation(): void
    {
        if (! $this->hasFile('supporting_files')) {
            return;
        }

        $kept = collect($this->file('supporting_files'))
            ->filter(function ($file) {
                if (! $file) {
                    return false;
                }

                $name = $file->getClientOriginalName() ?: 'file';

                // The underlying PHP upload didn't complete cleanly (dropped
                // connection, exceeded a server limit, etc).
                if (! $file->isValid()) {
                    $this->skippedFiles[] = "\"{$name}\" didn't finish uploading (connection interrupted or file too large).";

                    return false;
                }

                // Trusting the file's own extension rather than Laravel's
                // "mimes" content-sniffing rule — some perfectly normal
                // PNGs/JPEGs get sniffed as an alternate/legacy MIME string
                // depending on the phone/app/export tool that produced them
                // and would otherwise be falsely rejected.
                $ext = strtolower($file->getClientOriginalExtension());
                if (! in_array($ext, self::ALLOWED_EXTENSIONS, true)) {
                    $this->skippedFiles[] = "\"{$name}\" isn't a supported file type.";

                    return false;
                }

                if ($file->getSize() > self::MAX_KILOBYTES * 1024) {
                    $this->skippedFiles[] = "\"{$name}\" is larger than 10MB.";

                    return false;
                }

                return true;
            })
            ->values();

        // Still respect the 5-file cap even if more than 5 valid files were
        // somehow submitted (the client already stops at 5, this is just the
        // server-side backstop) — keep the first 5, note the rest as skipped.
        if ($kept->count() > self::MAX_FILES) {
            $kept->slice(self::MAX_FILES)->each(function ($file) {
                $this->skippedFiles[] = "\"{$file->getClientOriginalName()}\" wasn't attached — only ".self::MAX_FILES.' files are allowed.';
            });

            $kept = $kept->take(self::MAX_FILES);
        }

        $this->files->set('supporting_files', $kept->values()->all());

        // Request::allFiles() memoizes its result in $convertedFiles the
        // first time it's called — and hasFile() above already triggered
        // that on the ORIGINAL, unfiltered file list before any of this
        // method's filtering ran. Without clearing it here, every later
        // call to file()/all() (the validator's own data, AND the
        // controller's $request->file('supporting_files') after this
        // request passes validation) silently sees the stale, unfiltered
        // list again — so a batch containing even one file PHP itself
        // marked invalid (isValid() === false, e.g. one that tripped
        // upload_max_filesize) still reaches the validator at its original
        // index, where Laravel's implicit 'uploaded' rule rejects it with
        // "The supporting_files.N failed to upload." and takes the whole
        // submission down, good files included, instead of only dropping
        // that one file as intended above.
        $this->convertedFiles = null;
    }

    public function rules(): array
    {
        return [
            'problem_category' => ['required', 'in:Business Development,Technical Support,Market Research,Strategy Consultant,Others'],
            'problem_category_other' => ['nullable', 'required_if:problem_category,Others', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:5000'],
            // Every file that reaches here already passed the type/size/
            // upload checks in prepareForValidation() above — anything that
            // failed those was quietly dropped (and recorded in
            // $skippedFiles) rather than rejecting the whole submission.
            'supporting_files' => ['nullable', 'array'],
            'supporting_files.*' => ['file'],
        ];
    }
}
