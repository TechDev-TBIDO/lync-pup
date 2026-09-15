<?php

namespace App\Http\Requests\Startup;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates the Information Sheet's "Add Supporting Documents" upload —
 * same limits as Roadblock submission's own "Supporting Files" (see
 * StoreRoadblockRequest): up to 5 files total, 10MB each, the same allowed
 * types.
 *
 * MAX_FILES is a running total across every upload, not a per-request cap —
 * a founder who already has 3 files saved can only add 2 more, not 5 more.
 * See remainingSlots() below.
 */
class StoreInformationSheetFilesRequest extends FormRequest
{
    public const MAX_FILES = 5;

    public const MAX_KILOBYTES = 10240;

    public const ALLOWED_EXTENSIONS = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'jpg', 'jpeg', 'png', 'mp4'];

    public function authorize(): bool
    {
        return $this->user()->isStartup();
    }

    /**
     * How many supporting documents this sheet already has saved. The 'files'
     * array rule below is checked against what's LEFT of the 5-file cap, not
     * against 5 itself — otherwise a founder with, say, 4 already saved could
     * still submit 5 more in a single batch and end up with 9 on the sheet.
     */
    protected function existingFileCount(): int
    {
        return $this->user()->startup?->informationSheet?->files()->count() ?? 0;
    }

    protected function remainingSlots(): int
    {
        return max(0, self::MAX_FILES - $this->existingFileCount());
    }

    public function rules(): array
    {
        return [
            'files' => ['nullable', 'array', 'max:'.$this->remainingSlots()],
            'files.*' => [
                'file',
                'mimes:'.implode(',', self::ALLOWED_EXTENSIONS),
                'max:'.self::MAX_KILOBYTES,
            ],
        ];
    }

    /**
     * Swaps the meaningless "files.0" Laravel would otherwise put into the
     * messages below for the actual filename the founder picked, so a
     * rejected upload reads as "resume.pdf exceeds the 10MB size limit."
     * instead of "The files.0 must not be greater than 10240 kilobytes."
     */
    public function attributes(): array
    {
        return collect($this->file('files', []))
            ->mapWithKeys(fn ($file, $index) => ["files.{$index}" => $file?->getClientOriginalName() ?? 'file'])
            ->all();
    }

    public function messages(): array
    {
        return [
            'files.max' => 'Only '.self::MAX_FILES.' supporting documents can be attached in total'
                .($this->existingFileCount() > 0 ? ' — you already have '.$this->existingFileCount().' saved.' : '.'),
            'files.*.max' => 'The :attribute exceeds the 10MB size limit.',
            'files.*.mimes' => 'The :attribute is not a supported file type.',
            'files.*.file' => 'The :attribute failed to upload — please try again.',
        ];
    }
}
