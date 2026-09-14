<x-layouts.admin title="Assessment Hub">
    @php
        $initialMainTab = in_array(request('main'), ['information-sheet', 'assessment']) ? request('main') : 'information-sheet';
        // $selectedStage is already passed into this view by
        // AssessmentHubController::index() — same value _assessment.blade.php
        // uses to decide between its Documents/Meetings nav.
        $onMeetings = $selectedStage === 'Meetings';
    @endphp
    <div x-data="{ mainTab: @js($initialMainTab) }"
        x-init="$watch('mainTab', value => setQueryParam('main', value))">
        <div class="mb-6 flex items-start justify-between gap-3">
            <div>
                <h1 class="text-2xl font-bold text-gray-900 sm:text-3xl">Assessment Hub</h1>
                <p class="mt-1 text-sm text-gray-500 sm:text-base">Manage Startup Information Sheets from submission to approval and the Assessment Documents</p>
            </div>

            <div class="ml-auto flex shrink-0 items-center gap-3">
                {{-- The assessment form(s) further down (TRL/MRL/TMRL/SRL, Document
                     6/7/8, Venture Exit) all mirror their own dirty state into
                     $store.navigation.hasUnsavedChanges. Exporting doesn't touch
                     or discard that draft — it's just another modal on the same
                     page — but jumping into it with an unsaved draft sitting
                     around is exactly the kind of thing that's easy to forget
                     about, so it gets the same Stay/Leave confirmation as
                     actually navigating away. --}}
                <button type="button" x-show="mainTab === 'assessment' && {{ $onMeetings ? 'false' : 'true' }}" x-cloak
                    @click="
                        if ($store.navigation.hasUnsavedChanges) {
                            $store.navigation.pendingAction = 'export';
                            $store.navigation.showLeaveModal = true;
                        } else {
                            $dispatch('open-export-modal');
                        }
                    "
                    class="inline-flex shrink-0 items-center gap-1.5 self-start whitespace-nowrap rounded-lg bg-gradient-to-r from-[#6D0D23] to-[#11386A] px-2.5 py-1.5 text-xs font-semibold text-white transition hover:opacity-90 sm:gap-2 sm:px-3.5 sm:py-2 lg:px-5 lg:py-2.5 lg:text-sm">
                    <x-icon name="exportdoc.svg" class="h-3.5 w-3.5 shrink-0 lg:h-4 lg:w-4" />
                    <span>Export Document</span>
                </button>

                {{-- On the Meetings nav there's nothing to export — the
                     equivalent primary action there is scheduling a meeting,
                     which lives inside _meetings.blade.php's own Alpine
                     scope. Dispatching a window event (same pattern as
                     open-export-modal above and open-delete-version-{id} in
                     version-history-panel.blade.php) reaches across that
                     scope boundary without hoisting settingMeeting state up
                     here. --}}
                <button type="button" x-show="mainTab === 'assessment' && {{ $onMeetings ? 'true' : 'false' }}" x-cloak
                    @click="
                        if ($store.navigation.hasUnsavedChanges) {
                            $store.navigation.pendingAction = 'set-meeting';
                            $store.navigation.showLeaveModal = true;
                        } else {
                            $dispatch('open-set-meeting-modal');
                        }
                    "
                    class="inline-flex shrink-0 items-center gap-1.5 self-start whitespace-nowrap rounded-lg bg-gradient-to-r from-[#6D0D23] to-[#11386A] px-2.5 py-1.5 text-xs font-semibold text-white transition hover:opacity-90 sm:gap-2 sm:px-3.5 sm:py-2 lg:px-5 lg:py-2.5 lg:text-sm">
                    <img src="{{ asset('images/icons/cal.svg') }}" alt="" class="h-3.5 w-3.5 shrink-0 brightness-0 invert lg:h-4 lg:w-4" aria-hidden="true">
                    <span>Set a Meeting</span>
                </button>
            </div>
        </div>

        @include('admin.assessment-hub._export-modal')

        <div class="mb-6 flex gap-6 overflow-x-auto overflow-y-hidden border-b sm:gap-8">
            <button type="button" @click="mainTab = 'information-sheet'"
                :class="mainTab === 'information-sheet' ? 'border-rose-900 text-rose-900 font-bold' : 'border-transparent text-gray-400 font-medium'"
                class="-mb-px shrink-0 whitespace-nowrap border-b-2 pb-3 text-base sm:text-lg">
                Information Sheet
            </button>

            <button type="button" @click="mainTab = 'assessment'"
                :class="mainTab === 'assessment' ? 'border-rose-900 text-rose-900 font-bold' : 'border-transparent text-gray-400 font-medium'"
                class="-mb-px shrink-0 whitespace-nowrap border-b-2 pb-3 text-base sm:text-lg">
                Assessment
            </button>
        </div>

        @php
        $initialTab = in_array(request('tab'), ['schedule', 'evaluation', 'approved', 'rejected']) ? request('tab') : 'schedule';
        $subTabs = ['schedule' => 'Schedule', 'evaluation' => 'Evaluation', 'approved' => 'Approved', 'rejected' => 'Rejected'];
        @endphp
        <div x-show="mainTab === 'information-sheet'" x-cloak x-data="{ subTab: @js($initialTab) }"
            x-init="$watch('subTab', value => setQueryParam('tab', value))">

            {{-- replaces the old border-b sub-tab row --}}
            <div class="mb-6 flex w-full gap-1 overflow-x-auto overflow-y-hidden rounded-lg bg-gray-100 p-1 sm:inline-flex sm:w-auto sm:gap-0">
                @foreach ($subTabs as $key => $label)
                <button type="button" @click="subTab = '{{ $key }}'"
                    class="flex-1 whitespace-nowrap rounded-md px-3 py-1.5 text-sm font-medium transition sm:flex-none sm:px-4"
                    :class="subTab === '{{ $key }}'
                    ? 'bg-white text-rose-900 shadow-sm'
                    : 'text-gray-500 hover:text-gray-700'">
                    {{ $label }}
                </button>
                @endforeach
            </div>

            <div x-show="subTab === 'schedule'">
                @include('admin.assessment-hub._schedule')
            </div>
            <div x-show="subTab === 'evaluation'" x-cloak>
                @include('admin.assessment-hub._evaluation')
            </div>
            <div x-show="subTab === 'approved'" x-cloak>
                @include('admin.assessment-hub._approved')
            </div>
            <div x-show="subTab === 'rejected'" x-cloak>
                @include('admin.assessment-hub._rejected')
            </div>
        </div>

        <div x-show="mainTab === 'assessment'" x-cloak>
            @include('admin.assessment-hub._assessment')
        </div>
    </div>
</x-layouts.admin>