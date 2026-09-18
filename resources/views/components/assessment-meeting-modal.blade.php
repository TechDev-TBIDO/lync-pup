@props([
'mode', // add | edit
'close',
'action',
'meeting' => null, // existing AssessmentMeeting, present in edit/reschedule mode
'startups' => null, // Approved startups to choose from (add mode's own picker)
'stages' => [], // ReadinessRubric::STAGES
])

@php
$title = $mode === 'add' ? 'Set Meeting' : 'Reschedule Meeting';

// old() isn't scoped per row — every "Set Meeting"/"Reschedule" modal on
// this page calls old(...) with the same keys, so without this guard a
// validation failure on ONE row's submission would flash its half-typed
// values into every OTHER row's modal too (see evaluation-schedule-modal's
// identical concern).
$rowKey = $meeting?->assessment_meeting_id ?? 'new';
$oldMatchesThisRow = old('meeting_row_key') !== null && (string) old('meeting_row_key') === (string) $rowKey;

$initialStartupId = $oldMatchesThisRow ? old('startup_id') : $meeting?->startup_id;
$initialStage = $oldMatchesThisRow ? old('stage') : $meeting?->stage;
$initialDate = $oldMatchesThisRow ? old('meeting_date') : $meeting?->meeting_date?->format('Y-m-d');
$initialStartTime = $oldMatchesThisRow ? old('start_time') : (($meeting?->start_time) ? substr($meeting->start_time, 0, 5) : null);
$initialEndTime = $oldMatchesThisRow ? old('end_time') : (($meeting?->end_time) ? substr($meeting->end_time, 0, 5) : null);
$initialModality = $oldMatchesThisRow ? old('modality') : $meeting?->modality;
$initialLink = $oldMatchesThisRow ? old('link') : $meeting?->link;
$initialNotes = $oldMatchesThisRow ? old('notes') : $meeting?->notes;

$formId = 'assessment-meeting-form-'.$rowKey;
@endphp

<div class="flex max-h-[90vh] flex-col"
    x-data="{
        startupId: @js($initialStartupId ? (string) $initialStartupId : ''),
        stage: @js($initialStage ?? ''),
        date: @js($initialDate),
        startTime: @js($initialStartTime),
        endTime: @js($initialEndTime),
        modality: @js($initialModality ?? ''),
        link: @js($initialLink),
        notes: @js($initialNotes),
        initialStartupId: @js($initialStartupId ? (string) $initialStartupId : ''),
        initialStage: @js($initialStage ?? ''),
        initialDate: @js($initialDate),
        initialStartTime: @js($initialStartTime),
        initialEndTime: @js($initialEndTime),
        initialModality: @js($initialModality ?? ''),
        initialLink: @js($initialLink),
        initialNotes: @js($initialNotes),
        linkPlaceholders: {
            'Google Meet': 'e.g., https://google.com',
            'Zoom': 'e.g., https://zoom.us',
            'Microsoft Teams': 'e.g., Paste Microsoft Teams invitation link here',
            'Location': 'e.g., 123 Main Street, Suite 400, New York, NY',
            'Custom Link': 'e.g., https://your-conferencing-app.com',
        },
        isDirty() {
            return this.startupId !== this.initialStartupId
                || this.stage !== this.initialStage
                || this.date !== this.initialDate
                || this.startTime !== this.initialStartTime
                || this.endTime !== this.initialEndTime
                || (this.modality || '') !== (this.initialModality || '')
                || (this.link || '') !== (this.initialLink || '')
                || (this.notes || '') !== (this.initialNotes || '');
        },
        reset() {
            this.startupId = this.initialStartupId;
            this.stage = this.initialStage;
            this.date = this.initialDate;
            this.startTime = this.initialStartTime;
            this.endTime = this.initialEndTime;
            this.modality = this.initialModality;
            this.link = this.initialLink;
            this.notes = this.initialNotes;
        },
    }">
    <div class="shrink-0 bg-gradient-to-r from-[#6D0D23] to-[#11386A] text-white px-6 py-4 flex items-center justify-between">
        <h3 class="text-sm font-bold flex items-center gap-3">
            <img src="{{ asset('images/icons/cal.svg') }}" alt="" class="h-6 w-6 brightness-0 invert" aria-hidden="true">
            <span>{{ $title }}</span>
        </h3>
        <button type="button" @click="reset(); {{ $close }}"
            class="flex h-6 w-6 flex-shrink-0 items-center justify-center rounded-full border border-white text-white transition hover:border-transparent hover:bg-white hover:text-[#6D0D23] focus:outline-none"
            aria-label="Close">
            <svg class="h-3 w-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M18 6L6 18M6 6l12 12" />
            </svg>
        </button>
    </div>

    <div class="min-h-0 flex-1 overflow-y-auto p-6">
        <form method="POST" action="{{ $action }}" id="{{ $formId }}">
            @csrf
            @if ($mode !== 'add')
            @method('PUT')
            @endif
            <input type="hidden" name="meeting_row_key" value="{{ $rowKey }}">
            <input type="hidden" name="startup_id" x-bind:value="startupId">
            <input type="hidden" name="stage" x-bind:value="stage">
            <input type="hidden" name="meeting_date" x-bind:value="date">
            <input type="hidden" name="start_time" x-bind:value="startTime">
            <input type="hidden" name="end_time" x-bind:value="endTime">

            @if ($startups !== null)
            <div class="mb-5">
                <p class="font-medium mb-2 text-sm">1. Select Startup</p>
                <select x-model="startupId" @if ($mode !== 'add') disabled @endif
                    class="w-full border rounded-lg px-3 py-2 text-sm text-gray-700 disabled:bg-gray-50 disabled:text-gray-500">
                    <option value="" disabled>Select a startup&hellip;</option>
                    @forelse ($startups as $option)
                    <option value="{{ $option->startup_id }}">{{ $option->company_name }}</option>
                    @empty
                    <option value="" disabled>No approved startups yet</option>
                    @endforelse
                </select>
                @if ($oldMatchesThisRow) @error('startup_id') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror @endif
            </div>
            @endif

            <div class="mb-5">
                <p class="font-medium mb-2 text-sm">2. Select Document</p>
                <select x-model="stage"
                    class="w-full border rounded-lg px-3 py-2 text-sm text-gray-700">
                    <option value="" disabled>Select a stage&hellip;</option>
                    @foreach ($stages as $stageOption)
                    <option value="{{ $stageOption }}">{{ $stageOption }}</option>
                    @endforeach
                </select>
                @if ($oldMatchesThisRow) @error('stage') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror @endif
            </div>

            <div class="mb-5 grid grid-cols-1 gap-4 sm:grid-cols-3">
                <div>
                    <p class="font-medium mb-2 text-sm">3. Select Day</p>
                    <input type="date" x-model="date" min="{{ now()->format('Y-m-d') }}"
                        class="w-full border rounded-lg px-3 py-2 text-sm text-gray-700">
                    @if ($oldMatchesThisRow) @error('meeting_date') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror @endif
                </div>
                <div>
                    <p class="font-medium mb-2 text-sm">Start Time</p>
                    <input type="time" x-model="startTime"
                        class="w-full border rounded-lg px-3 py-2 text-sm text-gray-700">
                    @if ($oldMatchesThisRow) @error('start_time') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror @endif
                </div>
                <div>
                    <p class="font-medium mb-2 text-sm">End Time</p>
                    <input type="time" x-model="endTime"
                        class="w-full border rounded-lg px-3 py-2 text-sm text-gray-700">
                    @if ($oldMatchesThisRow) @error('end_time') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror @endif
                </div>
            </div>

            <div class="mb-5">
                <p class="font-medium mb-2 text-sm">4. Choose a Modality</p>
                <select name="modality" x-model="modality"
                    class="w-full border rounded-lg px-3 py-2 text-sm text-gray-700">
                    <option value="" disabled>Select Platform</option>
                    @foreach (\App\Support\MeetingPlatform::OPTIONS as $platformOption)
                    <option value="{{ $platformOption }}">{{ $platformOption }}</option>
                    @endforeach
                </select>
                @if ($oldMatchesThisRow) @error('modality') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror @endif
            </div>

            <div class="mb-5">
                <p class="font-medium mb-2 text-sm">Meeting Link / Location</p>
                <textarea name="link" rows="3" x-model="link"
                    :placeholder="linkPlaceholders[modality] || 'Input Meeting Link / Address'"
                    class="w-full border rounded-lg px-3 py-2 text-sm"></textarea>
                @if ($oldMatchesThisRow) @error('link') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror @endif
            </div>

            <div class="mb-1">
                <p class="font-medium mb-2 text-sm">Notes (Optional)</p>
                <textarea name="notes" rows="3" placeholder="Enter any notes for this meeting..."
                    x-model="notes"
                    class="w-full border rounded-lg px-3 py-2 text-sm"></textarea>
                <x-notes-limit-counter />
                @if ($oldMatchesThisRow) @error('notes') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror @endif
            </div>
        </form>
    </div>

    <div class="shrink-0 border-t border-gray-200 bg-white px-6 py-4">
        <div class="flex gap-3">
            <button type="button" @click="reset(); {{ $close }}"
                class="flex-1 rounded-lg border py-2.5 text-sm font-medium transition hover:bg-gray-50">
                Cancel
            </button>
            <button type="submit" form="{{ $formId }}"
                :disabled="!startupId || !stage || !date || !startTime || !endTime || !modality || !link || (@js($mode !== 'add') && !isDirty())"
                class="flex-1 rounded-lg bg-gradient-to-r from-[#6D0D23] to-[#11386A] py-2.5 text-sm font-medium text-white transition hover:opacity-90 disabled:cursor-not-allowed disabled:opacity-40 disabled:hover:opacity-40">
                {{ $mode === 'add' ? 'Save Meeting' : 'Save Reschedule' }}
            </button>
        </div>
    </div>
</div>
