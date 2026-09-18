{{--
    Reports tab.

    With a specific startup selected: that startup's "Save to Reports"-d
    exports ($savedReports, passed by AssessmentHubController, already
    scoped to $selectedStartup and ordered newest first).

    With "All Startup" selected: a summary table ($allSavedReportsSummary)
    of every startup that has ever saved an export, one row each, with a
    pill per document type showing whether it's included in that startup's
    saved reports — mirrors the Overview tab's pill styling.
--}}
<div x-data="{ confirmingId: null }">
    @unless ($selectedStartup)
    {{-- ============ All Startup: saved-reports summary ============ --}}
    <div class="mb-4 rounded-lg bg-gradient-to-r from-[#6D0D23] to-[#11386A] px-4 py-3">
        <span class="text-sm font-bold text-white">Saved Reports</span>
    </div>

    @if ($allSavedReportsSummary->isEmpty())
    <div class="rounded-xl border border-dashed p-12 text-center text-gray-400">
        No startup has saved an export to Reports yet. Use "Export Document" above, then
        "Save to Reports" once a file's generated.
    </div>
    @else
    <div class="overflow-hidden rounded-xl border border-gray-200">
        <div class="overflow-x-auto">
            <table class="w-full min-w-[820px] table-fixed text-sm">
                <thead>
                    <tr class="bg-gradient-to-r from-[#6D0D23] to-[#11386A] text-center text-white">
                        <th class="w-14 px-4 py-3 font-semibold whitespace-nowrap">#</th>
                        <th class="w-64 px-4 py-3 font-semibold whitespace-nowrap">Startup</th>
                        <th class="w-36 px-4 py-3 font-semibold whitespace-nowrap">Exported Files</th>
                        <th class="px-4 py-3 font-semibold whitespace-nowrap">Documents</th>
                    </tr>
                </thead>
                <tbody>
                    @php
                    // Same trick as the Overview table: one fixed-width block
                    // per column so the logos align, sized to the longest name.
                    $reportsNameLen = collect($allSavedReportsSummary)
                    ->map(fn ($r) => mb_strlen($r['startup']->company_name ?? ''))->max() ?: 12;
                    $reportsCell = 'width: calc(1.5rem + 0.5rem + '.min(max($reportsNameLen, 8), 24).'ch)';
                    @endphp
                    @foreach ($allSavedReportsSummary as $i => $row)
                    <tr class="border-b border-gray-100 last:border-0 align-middle">
                        <td class="px-4 py-4 text-center">{{ $i + 1 }}</td>
                        <td class="px-4 py-4">
                            <a href="{{ route('admin.assessment-hub.index', ['main' => 'assessment', 'stage' => 'Reports', 'assessment_startup' => $row['startup']->startup_id]) }}"
                                class="flex justify-center font-medium text-gray-900 hover:text-rose-900 hover:underline">
                                <span class="inline-flex max-w-full items-center gap-2 text-left" style="{{ $reportsCell }}">
                                    @if ($row['startup']->startup_photo_url)
                                    <img src="{{ $row['startup']->startup_photo_url }}" alt="" class="h-6 w-6 shrink-0 rounded-full object-cover">
                                    @else
                                    <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-rose-900 text-xs font-semibold text-white">
                                        {{ strtoupper(substr($row['startup']->company_name, 0, 1)) }}
                                    </span>
                                    @endif
                                    <span class="min-w-0 flex-1 truncate" title="{{ $row['startup']->company_name }}">{{ $row['startup']->company_name }}</span>
                                </span>
                            </a>
                        </td>
                        <td class="px-4 py-4 text-center font-semibold text-gray-700">{{ $row['exported_files_count'] }}</td>
                        <td class="px-4 py-4">
                            <div class="flex flex-wrap justify-center gap-2">
                                @foreach ($row['documents'] as $doc)
                                <span class="whitespace-nowrap rounded-full border px-3 py-1 text-xs font-semibold
                                        {{ $doc['included'] ? 'border-green-400 text-green-700 bg-green-50' : 'border-rose-200 text-rose-500 bg-rose-50' }}">
                                    {{ $doc['label'] }}
                                </span>
                                @endforeach
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif
    @else
    {{-- ============ Single startup: saved report files ============ --}}
    @if ($savedReports->isEmpty())
    <div class="rounded-xl border border-dashed p-12 text-center text-gray-400">
        No saved reports yet for {{ $selectedStartup->company_name }}. Use "Export Document" above, then
        "Save to Reports" once it's generated.
    </div>
    @else
    <div class="overflow-hidden rounded-xl border border-gray-200">
        <div class="overflow-x-auto overflow-y-hidden">
            <table class="w-full min-w-[640px] table-fixed text-sm">
                <thead>
                    <tr class="bg-gradient-to-r from-[#6D0D23] to-[#11386A] text-left text-white">
                        <th class="px-4 py-3 font-semibold">File Name</th>
                        <th class="px-4 py-3 font-semibold">Format</th>
                        <th class="px-4 py-3 font-semibold">Pages</th>
                        <th class="px-4 py-3 font-semibold">Size</th>
                        <th class="px-4 py-3 font-semibold">Generated</th>
                        <th class="px-4 py-3 text-center font-semibold">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($savedReports as $report)
                    <tr class="border-b border-gray-100 last:border-0">
                        <td class="max-w-0 px-4 py-3 font-medium text-gray-800">
                            <span
                                class="block max-w-full break-words"
                                title="{{ $report->file_name }}">
                                {{ $report->file_name }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-gray-600">{{ $report->format }}</td>
                        <td class="px-4 py-3 text-gray-600">{{ $report->page_count ?: '—' }}</td>
                        <td class="px-4 py-3 text-gray-600">{{ $report->file_size_label }}</td>
                        <td class="px-4 py-3 text-gray-600">{{ $report->created_at->format('M d, Y g:i A') }}</td>
                        <td class="px-4 py-3">
                            <div class="flex items-center justify-center gap-3">
                                <a href="{{ route('admin.exports.download', $report) }}"
                                    class="text-xs font-semibold text-rose-900 hover:underline">Download File</a>

                                <button type="button" @click="confirmingId = {{ $report->saved_report_id }}"
                                    class="text-gray-400 transition hover:text-red-600" aria-label="Delete report">
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M9 7V4a1 1 0 011-1h4a1 1 0 011 1v3M4 7h16" />
                                    </svg>
                                </button>

                                {{-- Delete confirmation modal for this row --}}
                                <template x-teleport="body">
                                    <div x-show="confirmingId === {{ $report->saved_report_id }}" x-cloak x-transition.opacity
                                        @keydown.escape.window="confirmingId = null"
                                        class="fixed inset-0 z-[200] flex items-center justify-center bg-black/50 p-4" style="display:none;">
                                        <div @click.outside="confirmingId = null"
                                            class="relative w-full max-w-md rounded-2xl bg-white px-8 pb-8 pt-10 text-center shadow-2xl">
                                            <button type="button" @click="confirmingId = null"
                                                class="absolute right-4 top-4 flex h-6 w-6 items-center justify-center rounded-full border border-[#6D0D23] text-[#6D0D23] transition hover:bg-[#6D0D23] hover:text-white"
                                                aria-label="Close">
                                                <svg class="h-3 w-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M18 6L6 18M6 6l12 12" />
                                                </svg>
                                            </button>

                                            <h3 class="bg-gradient-to-r from-[#6D0D23] to-[#11386A] bg-clip-text text-xl font-bold text-transparent">
                                                Delete Report File
                                            </h3>

                                            <p class="mt-2 text-sm text-gray-600">
                                                Are you sure you want to delete this file? This action is permanent
                                                and cannot be undone.
                                            </p>

                                            <div class="mt-6 grid grid-cols-2 gap-4">
                                                <button type="button" @click="confirmingId = null"
                                                    class="h-11 rounded-lg border border-[#6D0D23] bg-white text-sm font-bold text-[#6D0D23] transition hover:bg-rose-50">
                                                    Cancel
                                                </button>

                                                <form method="POST" action="{{ route('admin.exports.destroy', $report) }}">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit"
                                                        class="h-11 w-full rounded-lg bg-gradient-to-r from-[#6D0D23] to-[#11386A] text-sm font-bold text-white transition hover:opacity-90">
                                                        Delete
                                                    </button>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                </template>
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif
    @endunless
</div>