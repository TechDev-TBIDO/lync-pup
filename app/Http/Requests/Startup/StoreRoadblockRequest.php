<?php

namespace App\Http\Requests\Startup;

use Illuminate\Foundation\Http\FormRequest;

class StoreRoadblockRequest extends FormRequest
{
    public const MAX_FILES = 5;

    public const MAX_KILOBYTES = 10240;

    // Kept in sync with the form's own client-side limits.accept list in
    // resources/views/startup/roadblocks/index.blade.php — that list also
    // allowed webp/csv (missing here) and this one also allowed mp4
    // (missing there), so a founder attaching one of those could see it
    // accepted by the browser's own picker and then silently dropped once
    // it reached prepareForValidation() above, with nothing on screen
    // explaining why.
    public const ALLOWED_EXTENSIONS = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'jpg', 'jpeg', 'png', 'webp', 'csv', 'mp4'];

    /**
     * Second-chance check, only for a file whose extension didn't match
     * ALLOWED_EXTENSIONS above — some real PDFs (and other supported types)
     * reach here with an unhelpful/missing extension on their original
     * filename (e.g. exported by a scanner or "print to PDF" tool with no
     * ".pdf" suffix at all), and were being silently rejected as "not a
     * supported file type" despite genuinely being one. This never REPLACES
     * the extension check — it only widens it — so it can't reintroduce the
     * false-reject-by-content-sniffing problem the extension-first approach
     * below was deliberately chosen to avoid (see the comment on $kept).
     */
    public const ALLOWED_MIME_FALLBACKS = [
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'image/jpeg',
        'image/png',
        'video/mp4',
    ];

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

                // The underlying PHP upload didn't complete cleanly. Distinguishing
                // WHY, rather than lumping every failure into one vague "connection
                // interrupted or file too large" guess: a founder attaching a real,
                // valid file that simply exceeds what the server currently accepts
                // (upload_max_filesize/post_max_size) deserves an answer that says so,
                // not one indistinguishable from an actual dropped connection.
                if (! $file->isValid()) {
                    $this->skippedFiles[] = "\"{$name}\" " . $this->uploadErrorReason($file);

                    return false;
                }

                // Trusting the file's own extension rather than Laravel's
                // "mimes" content-sniffing rule as the PRIMARY check — some
                // perfectly normal PNGs/JPEGs get sniffed as an alternate/
                // legacy MIME string depending on the phone/app/export tool
                // that produced them and would otherwise be falsely
                // rejected. A file whose extension doesn't match still gets
                // one more chance via its actual detected MIME type
                // (ALLOWED_MIME_FALLBACKS above) before being turned away —
                // this only WIDENS acceptance, so it can't bring back that
                // same false-reject problem for the PNG/JPEG case.
                $ext = strtolower($file->getClientOriginalExtension());
                if (! in_array($ext, self::ALLOWED_EXTENSIONS, true)
                    && ! in_array($file->getMimeType(), self::ALLOWED_MIME_FALLBACKS, true)) {
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

    /**
     * Translates a failed UploadedFile's own PHP upload error code into a
     * reason the founder can actually act on, instead of one guess covering
     * every possible cause. UPLOAD_ERR_INI_SIZE/FORM_SIZE in particular is
     * the server's own upload_max_filesize/post_max_size ceiling rejecting
     * the file outright — a real, valid file (a several-MB scanned PDF is a
     * completely ordinary size) can trip this well under this form's own
     * documented 10MB/file limit, and that previously surfaced as the same
     * "connection interrupted or file too large" message as an actually
     * dropped connection, giving no way to tell the two apart.
     */
    private function uploadErrorReason(\Illuminate\Http\UploadedFile $file): string
    {
        return match ($file->getError()) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => "is larger than the server currently accepts for a single upload — try a smaller file or contact support if this keeps happening.",
            UPLOAD_ERR_PARTIAL => "didn't finish uploading (connection interrupted) — please try attaching it again.",
            UPLOAD_ERR_NO_FILE => "wasn't actually received by the server — please try attaching it again.",
            default => "couldn't be uploaded — please try again.",
        };
    }
}
