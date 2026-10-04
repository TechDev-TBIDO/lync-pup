<?php

namespace App\Http\Requests\Admin;

use App\Support\MeetingPlatform;
use App\Support\ReadinessRubric;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAssessmentMeetingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    /**
     * Keep only the meeting URL when a whole calendar invite was pasted
     * (see MeetingPlatform::normalizeLink()).
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('link')) {
            $this->merge(['link' => MeetingPlatform::normalizeLink($this->input('modality'), $this->input('link'))]);
        }
    }

    public function rules(): array
    {
        return [
            'startup_id' => ['required', 'exists:startups,startup_id'],
            'stage' => ['required', Rule::in(ReadinessRubric::STAGES)],
            'meeting_date' => ['required', 'date', 'after_or_equal:today'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
            // Same fixed platform list and per-platform link validation as
            // the Set Evaluation modal (see App\Support\MeetingPlatform).
            'modality' => ['required', Rule::in(MeetingPlatform::OPTIONS)],
            'link' => [
                'required',
                'string',
                'max:255',
                function ($attribute, $value, $fail) {
                    if (blank($value)) {
                        return;
                    }

                    if (! MeetingPlatform::isValidLink($this->input('modality'), $value)) {
                        $fail(MeetingPlatform::linkErrorMessage($this->input('modality')));
                    }
                },
            ],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'startup_id.required' => 'Please select a startup.',
            'stage.required' => 'Please select which assessment stage this meeting is for.',
            'meeting_date.after_or_equal' => 'You cannot schedule a meeting in the past.',
            'end_time.after' => 'End time must be after the start time.',
        ];
    }

    public function attributes(): array
    {
        return [
            'link' => 'meeting link / location',
            'stage' => 'document',
        ];
    }
}
