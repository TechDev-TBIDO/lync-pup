@props(['mode', 'action', 'roadblock', 'mentors', 'coordinators' => null, 'formSuffix' => null])
@php $coordinators = $coordinators ?? collect(); @endphp
@php
// A "Scheduled" roadblock whose meeting is today renders in BOTH the
// "Upcoming Mentorship" and "Scheduled Today" tables on the same page, each
// including this component for the very same $roadblock — without a
// distinguishing suffix that produces two elements sharing the exact same
// id. document.getElementById()/form="..." always resolve to the FIRST
// match in the DOM, so Clear Form / Save Changes clicked from the SECOND
// instance silently acted on the FIRST (possibly still showing stale,
// un-cleared values) instead of the modal actually on screen.
$formId = 'roadblock-assign-form-'.$roadblock->roadblock_id.($formSuffix ? '-'.$formSuffix : '');


$isErroredRoadblock = $errors->any() && (int) old('roadblock_id') === $roadblock->roadblock_id;
$oldFor = fn (string $field, $default) => $isErroredRoadblock ? old($field, $default) : $default;

$originalAssignee = $roadblock->coordinator_id
? 'coordinator-'.$roadblock->coordinator_id
: ($roadblock->mentor_id ? 'mentor-'.$roadblock->mentor_id : '');

// Reschedule is opened after a meeting already FAILED — its old date/time/
// platform/link/assignee are the details that didn't work out, not a
// still-valid starting point, so the modal should come up blank rather
// than quietly re-showing them as if nothing needs to change. Assign
// (brand-new) already has no roadblock data to show; only Edit should
// still pre-fill the record's real current values.
$prefillExisting = $mode !== 'reschedule';
$assigneeDefault = $prefillExisting ? $originalAssignee : '';
// '' rather than null for the x-model-bound fields below — Alpine's
// x-model on a <select> (meeting_platform) doesn't reliably land on the
    // placeholder option when handed a null initial value, it can end up
    // selecting whatever the first real <option> happens to be instead. '' is
        // the placeholder option's own value, so it always matches correctly.
        $meetingDateDefault = $prefillExisting ? $roadblock->meeting_date?->format('Y-m-d') : '';
        $platformDefault = $prefillExisting ? $roadblock->meeting_platform : '';
        $linkDefault = $prefillExisting ? $roadblock->meeting_link : '';
        $notesDefault = $prefillExisting ? $roadblock->notes : '';

        $selectedAssignee = $oldFor('assignee', $assigneeDefault);

        // Same helper as the layout — component scope doesn't inherit it.
        $icon = function (string $name, string $class = 'w-4 h-4') {
        $path = public_path('images/icons/' . $name);

        if (! file_exists($path)) {
        return '<span class="' . $class . ' inline-block"></span>';
        }

        $svg = file_get_contents($path);

        $svg = preg_replace('/<svg([^>]*)>/', '<svg$1 class="' . $class . ' block">', $svg, 1);
                $svg = preg_replace('/fill="(?!none)[^"]*"/i', 'fill="currentColor"', $svg);
                $svg = preg_replace('/stroke="(?!none)[^"]*"/i', 'stroke="currentColor"', $svg);

                return $svg;
                };

                // <input type="time"> only accepts H:i or H:i:s. If the model casts these
                // columns to Carbon, {{ }} renders "Y-m-d H:i:s", which the browser silently
                // discards — the field comes up blank and submits "", failing date_format:H:i
                // even though the stored value was fine.
                $asTime = function ($value): ?string {
                if (blank($value)) {
                return null;
                }

                if ($value instanceof \DateTimeInterface) {
                return $value->format('H:i');
                }

                return substr((string) $value, 0, 5); // "14:30:00" -> "14:30"
                };

                $meetingStartDefault = $prefillExisting ? $asTime($roadblock->meeting_start_time) : null;
                $meetingEndDefault = $prefillExisting ? $asTime($roadblock->meeting_end_time) : null;

                // One class string so every preview detail row stays identical
                $previewRow = 'flex items-center gap-2';
                $previewDisc = 'flex h-5 w-5 flex-shrink-0 items-center justify-center rounded-full bg-white/15';

                // Field/label styles, repeated across both panels
                $fieldCls = 'w-full border rounded-lg px-3 py-2 text-sm';
                $lblCls = 'block text-xs text-gray-500 mb-1';
                @endphp

                <div class="flex max-h-[90vh] flex-col overflow-hidden" x-data="{
            deleteAssignmentOpen: false,
            showFailedState: @js($isErroredRoadblock),

            // Reactive mirror of every field this form submits — every one of
            // them is x-model-bound below. Having them all live in ONE scope
            // (rather than split across separate nested x-data blocks per
            // panel, bridged by raw DOM writes + custom events) is what lets
            // Clear Form / Cancel just reassign these variables directly and
            // have every field update in lockstep, with nothing left able to
            // silently fall out of sync.
            assignee: @js($selectedAssignee),
            meetingDate: @js($oldFor('meeting_date', $meetingDateDefault)),
            meetingStartTime: @js($oldFor('meeting_start_time', $meetingStartDefault)),
            meetingEndTime: @js($oldFor('meeting_end_time', $meetingEndDefault)),
            platform: @js($oldFor('meeting_platform', $platformDefault)),
            meetingLink: @js($oldFor('meeting_link', $linkDefault)),
            notes: @js($oldFor('notes', $notesDefault)),

            // The record's actual current values — what Cancel/× reverts to,
            // and what Save Changes compares against to know whether anything
            // has actually changed.
            initialAssignee: @js($assigneeDefault),
            initialMeetingDate: @js($meetingDateDefault),
            initialMeetingStartTime: @js($meetingStartDefault),
            initialMeetingEndTime: @js($meetingEndDefault),
            initialPlatform: @js($platformDefault),
            initialMeetingLink: @js($linkDefault),
            initialNotes: @js($notesDefault),

            isDirty() {
                return this.assignee !== this.initialAssignee
                    || this.meetingDate !== this.initialMeetingDate
                    || this.meetingStartTime !== this.initialMeetingStartTime
                    || this.meetingEndTime !== this.initialMeetingEndTime
                    || this.platform !== this.initialPlatform
                    || (this.meetingLink || '') !== (this.initialMeetingLink || '')
                    || (this.notes || '') !== (this.initialNotes || '');
            },

            clearForm() {
                this.assignee = '';
                this.meetingDate = '';
                this.meetingStartTime = '';
                this.meetingEndTime = '';
                this.platform = '';
                this.meetingLink = '';
                this.notes = '';
                this.showFailedState = false;
            },

            revertForm() {
                this.assignee = this.initialAssignee;
                this.meetingDate = this.initialMeetingDate;
                this.meetingStartTime = this.initialMeetingStartTime;
                this.meetingEndTime = this.initialMeetingEndTime;
                this.platform = this.initialPlatform;
                this.meetingLink = this.initialMeetingLink;
                this.notes = this.initialNotes;
                this.showFailedState = false;
            },

            // The browser's native scroll-to-change behavior on a focused
            // <input type=time> moves by however many wheel 'ticks' the
            // trackpad/mouse happens to report for one scroll gesture — often
            // more than one, and inconsistently between scrolling up and
            // down — so it visibly skips over minutes instead of moving one
            // at a time. Taking over the wheel event and always stepping by
            // exactly one minute per gesture (regardless of the raw delta)
            // makes scrolling land on every number smoothly in both
            // directions.
            stepTime(value, direction) {
                const [h, m] = (value || '00:00').split(':').map(Number);
                const base = (Number.isFinite(h) ? h : 0) * 60 + (Number.isFinite(m) ? m : 0);
                const total = (base + direction + 1440) % 1440;

                return String(Math.floor(total / 60)).padStart(2, '0') + ':' + String(total % 60).padStart(2, '0');
            },

            todayStr: @js(now()->format('Y-m-d')),
            nowTime: @js(now()->format('H:i')),
            linkPlaceholders: {
                'Google Meet': 'e.g., https://google.com',
                'Zoom': 'e.g., https://zoom.us',
                'Microsoft Teams': 'e.g., Paste Microsoft Teams invitation link here',
                'Location': 'e.g., 123 Main Street, Suite 400, New York, NY',
                'Custom Link': 'e.g., https://your-conferencing-app.com',
            },
        }">

                    <div class="relative flex flex-shrink-0 items-center justify-between bg-gradient-to-r from-[#6D0D23] to-[#11386A] px-5 py-4 text-white sm:px-8 sm:py-5">
                        <div class="flex min-w-0 items-center gap-2.5 sm:gap-3">
                            <span class="flex-shrink-0 text-white">
                                {!! $icon('upcoming-mentorship.svg', 'w-5 h-5 sm:w-6 sm:h-6') !!}
                            </span>

                            <h3 class="truncate text-sm font-bold sm:text-base">
                                @if ($mode === 'edit') Edit Assigned Mentor
                                @elseif ($mode === 'reschedule') Reschedule
                                @else Assign Mentor
                                @endif
                            </h3>
                        </div>

                        <button type="button"
                            @click="{{ $mode === 'edit' ? 'revertForm(); editOpen = false;' : ($mode === 'reschedule' ? 'revertForm(); rescheduleOpen = false;' : 'clearForm(); assignOpen = false;') }}"
                            class="flex h-6 w-6 flex-shrink-0 items-center justify-center rounded-full border border-white text-white transition hover:border-transparent hover:bg-white hover:text-[#6D0D23] focus:outline-none focus-visible:ring-2 focus-visible:ring-white/60"
                            aria-label="Close">
                            <svg class="h-3 w-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M18 6L6 18M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    <div class="min-h-0 flex-1 overflow-y-auto p-4 sm:p-6">
                        <form method="POST" action="{{ $action }}" id="{{ $formId }}" autocomplete="off">
                            @csrf
                            @method('PUT')
                            <input type="hidden" name="roadblock_id" value="{{ $roadblock->roadblock_id }}">

                            {{-- Panels stack on phones, sit side by side from sm up --}}
                            <div class="grid grid-cols-1 gap-4 lg:grid-cols-2 lg:gap-6">
                                {{-- Close any open profile preview card whenever the surrounding modal
                                     closes (×, Cancel, Escape, backdrop...), so it isn't still
                                     sitting open the next time the modal is shown. --}}
                                <div class="relative rounded-xl border p-3 sm:p-4" x-data="{ previewId: null }"
                                    x-init="$watch('{{ $mode === 'edit' ? 'editOpen' : ($mode === 'reschedule' ? 'rescheduleOpen' : 'assignOpen') }}', open => { if (! open) previewId = null })">
                                    <p class="mb-3 text-sm font-medium sm:text-base">1. Assign Mentor or Coordinator <span class="text-red-600">*</span></p>
                                    <select name="assignee" autocomplete="off" x-model="assignee" data-original="{{ $assigneeDefault }}" class="{{ $fieldCls }} mb-4">
                                        <option value="" disabled hidden @selected($selectedAssignee==='' )>Select Mentor or Coordinator</option>
                                        @if ($mentors->isNotEmpty())
                                        <option disabled>── Mentors ──</option>
                                        @foreach ($mentors as $m)
                                        <option value="mentor-{{ $m->mentor_id }}" @selected($selectedAssignee==='mentor-' .$m->mentor_id)>
                                            {{ $m->display_name }}
                                        </option>
                                        @endforeach
                                        @endif
                                        @if ($coordinators->isNotEmpty())
                                        <option disabled>── Coordinators ──</option>
                                        @foreach ($coordinators as $c)
                                        <option value="coordinator-{{ $c->coordinator_id }}" @selected($selectedAssignee==='coordinator-' .$c->coordinator_id)>
                                            {{ $c->display_name }}
                                        </option>
                                        @endforeach
                                        @endif
                                    </select>
                                    @if ($isErroredRoadblock && ($errors->has('assignee') || $errors->has('mentor_id') || $errors->has('coordinator_id')))
                                    <p class="mb-3 text-xs text-red-600" x-show="showFailedState">
                                        {{ $errors->first('assignee') ?: ($errors->first('mentor_id') ?: $errors->first('coordinator_id')) }}
                                    </p>
                                    @endif

                                    <p class="mb-2 text-sm font-medium text-gray-700">Profile Preview</p>

                                    {{-- One name per row on phones: two columns truncates almost every name --}}
                                    <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                                        @foreach ($mentors as $m)
                                        <button type="button" @click="previewId = 'mentor-{{ $m->mentor_id }}'"
                                            class="flex items-center gap-2 rounded-lg border px-2.5 py-2 text-left text-sm transition hover:border-rose-900 hover:bg-gray-50 sm:px-3">
                                            <div class="h-6 w-6 shrink-0 overflow-hidden rounded-full bg-gray-200">
                                                @if ($m->mentor_photo_path)
                                                <img src="{{ Storage::url($m->mentor_photo_path) }}" class="h-full w-full object-cover">
                                                @endif
                                            </div>
                                            <span class="truncate">{{ $m->display_name }}</span>
                                        </button>
                                        @endforeach
                                        @foreach ($coordinators as $c)
                                        <button type="button" @click="previewId = 'coordinator-{{ $c->coordinator_id }}'"
                                            class="flex items-center gap-2 rounded-lg border px-2.5 py-2 text-left text-sm transition hover:border-rose-900 hover:bg-gray-50 sm:px-3">
                                            <div class="h-6 w-6 shrink-0 overflow-hidden rounded-full bg-gray-200">
                                                @if ($c->coordinator_photo_path)
                                                <img src="{{ Storage::url($c->coordinator_photo_path) }}" class="h-full w-full object-cover">
                                                @endif
                                            </div>
                                            <span class="truncate">{{ $c->display_name }}</span>
                                        </button>
                                        @endforeach
                                    </div>

                                    {{-- Overlay: backdrop + card, scoped to this panel --}}
                                    <div x-show="previewId !== null" x-cloak
                                        class="absolute inset-0 z-20 flex items-center justify-center rounded-xl bg-black/40 p-4"
 style="display: none;">

                                        @foreach ($mentors as $m)
                                        <div x-show="previewId === 'mentor-{{ $m->mentor_id }}'" x-cloak
                                            class="relative aspect-[3/4] w-full max-w-[13rem] overflow-hidden rounded-xl shadow-2xl sm:max-w-[15rem]"
                                            style="display: none;">

                                            {{-- Photo fills the entire card, positioned absolutely as the base layer --}}
                                            <div class="absolute inset-0 bg-gray-200">
                                                @if ($m->mentor_photo_path)
                                                <img src="{{ Storage::url($m->mentor_photo_path) }}" class="h-full w-full object-cover">
                                                @else
                                                <div class="flex h-full w-full items-center justify-center text-sm text-gray-400">No Photo</div>
                                                @endif
                                            </div>

                                            {{-- Text overlays ON TOP of the photo, anchored to the bottom --}}
                                            <div class="absolute bottom-0 left-0 right-0 bg-gradient-to-t from-black via-black/70 to-transparent p-3 pt-12 text-white sm:p-4 sm:pt-16">
                                                <p class="truncate text-sm font-bold sm:text-base">{{ $m->display_name }}</p>
                                                <p class="mb-1.5 truncate text-[10px] text-white/70 sm:mb-2 sm:text-xs">{{ $m->display_specialization }} Mentor</p>

                                                <div class="space-y-1 border-t border-white/20 pt-1.5 text-[10px] text-white/80 sm:space-y-1.5 sm:pt-2 sm:text-xs">
                                                    <p class="{{ $previewRow }}">
                                                        <span class="{{ $previewDisc }}">{!! $icon('call.svg', 'w-2.5 h-2.5') !!}</span>
                                                        <span class="truncate">{{ $m->contact_number ?? '—' }}</span>
                                                    </p>

                                                    <p class="{{ $previewRow }}">
                                                        <span class="{{ $previewDisc }}">{!! $icon('mail.svg', 'w-2.5 h-2.5') !!}</span>
                                                        <span class="truncate">{{ $m->contact_email ?? '—' }}</span>
                                                    </p>

                                                    <p class="{{ $previewRow }}">
                                                        <span class="{{ $previewDisc }}">{!! $icon('3person.svg', 'w-2.5 h-2.5') !!}</span>
                                                        <span class="truncate">
                                                            {{ $m->active_cases_count }} {{ Str::plural('Active Case', $m->active_cases_count) }}
                                                            &middot; {{ $m->completed_cases_count }} Completed
                                                        </span>
                                                    </p>
                                                </div>
                                            </div>

                                            <button type="button" @click="previewId = null"
                                                class="absolute right-2 top-2 flex h-7 w-7 items-center justify-center rounded-full bg-black/50 text-xl text-white/80 transition hover:bg-black/70 hover:text-white"
                                                aria-label="Close preview">
                                                <span class="-mt-1">&times;</span>
                                            </button>
                                        </div>
                                        @endforeach

                                        @foreach ($coordinators as $c)
                                        <div x-show="previewId === 'coordinator-{{ $c->coordinator_id }}'" x-cloak
                                            class="relative aspect-[3/4] w-full max-w-[13rem] overflow-hidden rounded-xl shadow-2xl sm:max-w-[15rem]"
                                            style="display: none;">

                                            <div class="absolute inset-0 bg-gray-200">
                                                @if ($c->coordinator_photo_path)
                                                <img src="{{ Storage::url($c->coordinator_photo_path) }}" class="h-full w-full object-cover">
                                                @else
                                                <div class="flex h-full w-full items-center justify-center text-sm text-gray-400">No Photo</div>
                                                @endif
                                            </div>

                                            <div class="absolute bottom-0 left-0 right-0 bg-gradient-to-t from-black via-black/70 to-transparent p-3 pt-12 text-white sm:p-4 sm:pt-16">
                                                <p class="truncate text-sm font-bold sm:text-base">{{ $c->display_name }}</p>
                                                <p class="mb-1.5 truncate text-[10px] text-white/70 sm:mb-2 sm:text-xs">{{ $c->role_title }} &bull; Coordinator</p>

                                                <div class="space-y-1 border-t border-white/20 pt-1.5 text-[10px] text-white/80 sm:space-y-1.5 sm:pt-2 sm:text-xs">
                                                    <p class="{{ $previewRow }}">
                                                        <span class="{{ $previewDisc }}">{!! $icon('call.svg', 'w-2.5 h-2.5') !!}</span>
                                                        <span class="truncate">{{ $c->phone ?? '—' }}</span>
                                                    </p>

                                                    <p class="{{ $previewRow }}">
                                                        <span class="{{ $previewDisc }}">{!! $icon('mail.svg', 'w-2.5 h-2.5') !!}</span>
                                                        <span class="truncate">{{ $c->email ?? '—' }}</span>
                                                    </p>

                                                    <p class="{{ $previewRow }}">
                                                        <span class="{{ $previewDisc }}">{!! $icon('3person.svg', 'w-2.5 h-2.5') !!}</span>
                                                        <span class="truncate">{{ $c->active_startups_count }} {{ Str::plural('Startup', $c->active_startups_count) }}</span>
                                                    </p>
                                                </div>
                                            </div>

                                            <button type="button" @click="previewId = null"
                                                class="absolute right-2 top-2 flex h-7 w-7 items-center justify-center rounded-full bg-black/50 text-xl text-white/80 transition hover:bg-black/70 hover:text-white"
                                                aria-label="Close preview">
                                                <span class="-mt-1">&times;</span>
                                            </button>
                                        </div>
                                        @endforeach
                                    </div>
                                </div>

                                @php
                                // A brand-new assignment has no date to clamp against
                                // yet, so it's floored at "today"/"now". Editing an
                                // existing (still-upcoming) roadblock pre-fills its
                                // own already-valid date/time, and clamping those with
                                // the *current* clock was rejecting them as "in the
                                // past" the moment real time moved past the original
                                // value. Reschedule now starts blank too (see
                                // $prefillExisting above) — its old date already
                                // failed, so it needs the same future-only floor as a
                                // fresh assignment, not the edit-mode exemption.
                                $enforceFutureOnly = $mode === 'assign' || $mode === 'reschedule';
                                @endphp
                                <div class="rounded-xl border p-3 sm:p-4">
                                    <p class="mb-3 text-sm font-medium sm:text-base">2. Set a Meeting</p>

                                    <label class="{{ $lblCls }}">Date <span class="text-red-600">*</span></label>
                                    <input type="date" name="meeting_date" autocomplete="off" x-model="meetingDate"
                                        @if ($enforceFutureOnly) :min="todayStr" @endif
                                        value="{{ $oldFor('meeting_date', $meetingDateDefault) }}"
                                        data-original="{{ $meetingDateDefault }}"
                                        class="{{ $fieldCls }} mb-3">
                                    @if ($isErroredRoadblock) @error('meeting_date') <p class="mb-3 text-xs text-red-600" x-show="showFailedState">{{ $message }}</p> @enderror @endif

                                    <div class="mb-3 grid grid-cols-2 gap-3">
                                        <div>
                                            <label class="{{ $lblCls }}">Start Time <span class="text-red-600">*</span></label>
                                            <input type="time" name="meeting_start_time" autocomplete="off" x-model="meetingStartTime"
                                                @wheel.prevent="meetingStartTime = stepTime(meetingStartTime, $event.deltaY < 0 ? 1 : -1)"
                                                @if ($enforceFutureOnly) :min="meetingDate === todayStr ? nowTime : null" @endif
                                                value="{{ $oldFor('meeting_start_time', $meetingStartDefault) }}"
                                                data-original="{{ $meetingStartDefault }}"
                                                class="{{ $fieldCls }}">
                                            @if ($isErroredRoadblock) @error('meeting_start_time') <p class="mt-1 text-xs text-red-600" x-show="showFailedState">{{ $message }}</p> @enderror @endif
                                        </div>
                                        <div>
                                            <label class="{{ $lblCls }}">End Time <span class="text-red-600">*</span></label>
                                            <input type="time" name="meeting_end_time" autocomplete="off" x-model="meetingEndTime"
                                                @wheel.prevent="meetingEndTime = stepTime(meetingEndTime, $event.deltaY < 0 ? 1 : -1)"
                                                value="{{ $oldFor('meeting_end_time', $meetingEndDefault) }}"
                                                data-original="{{ $meetingEndDefault }}"
                                                class="{{ $fieldCls }}">
                                            @if ($isErroredRoadblock) @error('meeting_end_time') <p class="mt-1 text-xs text-red-600" x-show="showFailedState">{{ $message }}</p> @enderror @endif
                                        </div>
                                    </div>

                                    <label class="{{ $lblCls }}">Platform <span class="text-red-600">*</span></label>
                                    <select name="meeting_platform" autocomplete="off" x-model="platform" data-original="{{ $platformDefault }}" class="{{ $fieldCls }} mb-3">
                                        <option value="" disabled hidden @selected(! $oldFor('meeting_platform', $platformDefault))>Select Platform</option>
                                        @foreach (['Google Meet', 'Zoom', 'Microsoft Teams', 'Location', 'Custom Link'] as $platformOption)
                                        <option value="{{ $platformOption }}" @selected($oldFor('meeting_platform', $platformDefault)===$platformOption)>{{ $platformOption }}</option>
                                        @endforeach
                                    </select>
                                    @if ($isErroredRoadblock) @error('meeting_platform') <p class="mb-3 text-xs text-red-600" x-show="showFailedState">{{ $message }}</p> @enderror @endif

                                    <label class="{{ $lblCls }}">Meeting Link / Location <span class="text-red-600">*</span></label>
                                    <textarea name="meeting_link" autocomplete="off" rows="3" x-model="meetingLink"
                                        :placeholder="linkPlaceholders[platform] || 'Input Meeting Link / Address'"
                                        data-original="{{ $linkDefault }}"
                                        class="{{ $fieldCls }}">{{ $oldFor('meeting_link', $linkDefault) }}</textarea>
                                    @if ($isErroredRoadblock) @error('meeting_link') <p class="mt-1 text-xs text-red-600" x-show="showFailedState">{{ $message }}</p> @enderror @endif

                                    <label class="{{ $lblCls }} mt-3">Notes</label>
                                    <textarea name="notes" autocomplete="off" rows="3" x-model="notes"
                                        placeholder="Optional note for the founder (e.g. what to prepare, what to expect)"
                                        data-original="{{ $notesDefault }}"
                                        class="{{ $fieldCls }}">{{ $oldFor('notes', $notesDefault) }}</textarea>
                                    <x-notes-limit-counter />
                                    @if ($isErroredRoadblock) @error('notes') <p class="mt-1 text-xs text-red-600" x-show="showFailedState">{{ $message }}</p> @enderror @endif
                                </div>
                            </div>
                        </form>

                        {{-- Actions stack on phones. The submit button is ordered first there so
                     the primary action isn't buried under two secondary ones. --}}
                        <div class="flex flex-col gap-3 pt-5 sm:flex-row sm:pt-6">
                            @if ($mode === 'reschedule')
                            <button type="button" @click="revertForm(); rescheduleOpen = false;"
                                class="order-2 h-10 w-full rounded-md border border-gray-300 bg-white text-sm font-bold text-gray-800 transition hover:bg-gray-50 sm:order-none sm:flex-1">
                                Cancel
                            </button>
                            @else
                            <button type="button" @click="clearForm()" :disabled="!isDirty()"
                                class="order-2 h-10 w-full rounded-md border border-gray-300 bg-white text-sm font-bold text-gray-800 transition hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-40 disabled:hover:bg-white sm:order-none sm:flex-1">
                                Clear Form
                            </button>
                            @endif

                            @if ($mode === 'edit')
                            <form method="POST" action="{{ route('admin.roadblocks.unassign', $roadblock) }}" id="{{ $formId }}-unassign">
                                @csrf
                                @method('DELETE')
                            </form>

                            <button type="button" @click="deleteAssignmentOpen = true"
                                class="order-3 h-10 w-full rounded-md bg-gradient-to-r from-rose-900 to-rose-950 text-sm font-bold text-white transition hover:opacity-95 sm:order-none sm:flex-1">
                                Delete Roadblock
                            </button>
                            @endif

                            <button type="submit" form="{{ $formId }}"
                                @if ($mode==='edit' ) :disabled="!isDirty()" @endif
                                class="order-1 h-10 w-full rounded-md bg-gradient-to-r from-[#6D0D23] to-[#11386A] text-sm font-bold text-white transition hover:opacity-95 sm:order-none sm:flex-1 disabled:cursor-not-allowed disabled:opacity-40 disabled:hover:opacity-40">
                                @if ($mode === 'edit') Save Changes
                                @elseif ($mode === 'reschedule') Save
                                @else Assign Mentor
                                @endif
                            </button>
                        </div>

                        @if ($mode === 'edit')
                        {{-- Delete assignment confirmation --}}
                        <div
                            x-show="deleteAssignmentOpen"
                            x-cloak
                            x-transition.opacity
                            class="fixed inset-0 z-[100] flex items-center justify-center bg-black/50 p-4"
                            style="display:none;">

                            <div
                                class="relative w-full max-w-lg rounded-2xl bg-white px-5 pb-5 pt-8 text-center shadow-2xl sm:px-6">

                                <button type="button" @click="deleteAssignmentOpen = false"
                                    class="absolute right-3 top-3 flex h-6 w-6 items-center justify-center rounded-full border border-gray-900 text-gray-900 transition hover:border-transparent hover:bg-gradient-to-r hover:from-[#6D0D23] hover:to-[#11386A] hover:text-white"
                                    aria-label="Close">
                                    <svg class="h-3 w-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M18 6L6 18M6 6l12 12" />
                                    </svg>
                                </button>

                                <div class="mx-auto flex h-11 w-11 items-center justify-center rounded-full bg-gradient-to-r from-[#6D0D23] to-[#11386A]">
                                    <img src="{{ asset('images/icons/trash.svg') }}" alt="" class="h-5 w-5">
                                </div>

                                <h2 class="mt-2.5 bg-gradient-to-r from-[#6D0D23] to-[#11386A] bg-clip-text text-base font-bold text-transparent sm:text-lg">
                                    Delete Roadblock
                                </h2>

                                <p class="mt-1.5 text-xs leading-5 text-gray-600">
                                    Are you sure you want to delete this roadblock? This also removes its
                                    assigned Mentor/Coordinator and meeting schedule.<br>
                                    This action is permanent and cannot be undone.
                                </p>

                                <div class="mt-4 grid grid-cols-2 gap-3 sm:gap-4">
                                    <button type="button" @click="deleteAssignmentOpen = false"
                                        class="h-10 w-full rounded-md border border-gray-300 bg-white text-sm font-bold text-gray-800 transition hover:bg-gray-50">
                                        Cancel
                                    </button>

                                    <button type="submit" form="{{ $formId }}-unassign"
                                        class="h-10 w-full rounded-md bg-gradient-to-r from-[#6D0D23] to-[#11386A] text-sm font-bold text-white transition hover:opacity-95">
                                        Delete
                                    </button>
                                </div>
                            </div>
                        </div>
                        @endif
                    </div>

                </div>