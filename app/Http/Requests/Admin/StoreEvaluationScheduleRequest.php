<?php

namespace App\Http\Requests\Admin;

use App\Models\EvaluationSchedule;
use App\Support\MeetingPlatform;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEvaluationScheduleRequest extends FormRequest
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
            'startup_id' => [
                'required',
                'exists:startups,startup_id',
                function ($attribute, $value, $fail) {
                    $alreadyScheduled = EvaluationSchedule::where('startup_id', $value)
                        ->where('status', 'Scheduled')
                        ->exists();

                    if ($alreadyScheduled) {
                        $fail('This startup already has an evaluation scheduled. Edit that schedule instead of adding a new one.');
                    }
                },
            ],
            'evaluation_date' => [
                'required',
                'date',
                'after_or_equal:today',
                function ($attribute, $value, $fail) {
                    if (\Illuminate\Support\Carbon::parse($value)->isWeekend()) {
                        $fail('Evaluations cannot be scheduled on a Saturday or Sunday.');
                    }
                },
            ],
            'start_time' => [
                'required',
                Rule::in(array_column(EvaluationSchedule::TIME_SLOTS, 0)),
                function ($attribute, $value, $fail) {
                    $evaluationDate = \Illuminate\Support\Carbon::parse($this->input('evaluation_date'));

                    if ($evaluationDate->isToday() && $value <= now()->format('H:i')) {
                        $fail('That time slot has already passed today. Please choose a later time.');

                        return;
                    }

                    $taken = EvaluationSchedule::where('status', 'Scheduled')
                        ->whereDate('evaluation_date', $this->input('evaluation_date'))
                        ->where('start_time', $value)
                        ->exists();

                    if ($taken) {
                        $fail('That time slot is already booked on the selected date.');
                    }
                },
            ],
            // How the evaluation itself will happen, same fixed platform list
            // and per-platform link validation as Roadblock's mentor
            // assignment (see App\Support\MeetingPlatform).
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
            'startup_id.required' => 'Please select a startup to evaluate.',
            'evaluation_date.after_or_equal' => 'You cannot schedule an evaluation in the past.',
        ];
    }

    public function attributes(): array
    {
        return [
            'link' => 'meeting link / location',
        ];
    }
}
