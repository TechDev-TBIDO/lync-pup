<x-layouts.admin title="Archive">
    {{--
        Archive: every archived cohort, roadblock and assessment meeting in one
        place (see Admin\ArchiveController). Tabs pick the type; the filter bar
        (search, status, sort) is a plain GET form so the URL can be shared or
        bookmarked. Un-archiving reuses each record's existing action.
    --}}
    @php
    $btn = 'inline-flex h-8 items-center justify-center whitespace-nowrap rounded-lg px-3 text-xs font-semibold transition';
    $statusTone = fn ($status) => match ($status) {
        'Resolved' => 'border-green-300 bg-green-50 text-green-700',
        'Failed' => 'border-rose-300 bg-rose-50 text-rose-700',
        default => 'border-gray-300 bg-gray-50 text-gray-600',
    };
    $dateLabel = ['cohorts' => 'Last Updated', 'roadblocks' => 'Closed On', 'meetings' => 'Closed On'][$type];
    $nameLabel = ['cohorts' => 'Cohort', 'roadblocks' => 'Startup', 'meetings' => 'Startup'][$type];
    $detailLabel = ['cohorts' => 'Dates · Startups', 'roadblocks' => 'Category', 'meetings' => 'Stage · Meeting Date'][$type];
    @endphp

    <div class="mb-6">
        <h1 class="text-3xl font-bold text-gray-900">Archive</h1>
        <p class="mt-1 text-gray-500">Everything that has been archived, in one place. Search, filter and bring a record back when you need it.</p>
    </div>

    {{-- Type tabs, each with its count. --}}
    <div class="mb-6 border-b border-gray-300">
        <nav class="flex overflow-x-auto overflow-y-hidden whitespace-nowrap">
            @foreach ($types as $key => $label)
            <a href="{{ route('admin.archive.index', ['type' => $key]) }}"
                class="flex items-center gap-2 px-6 py-3 text-sm font-medium border-b-2 -mb-px transition-colors duration-200 sm:px-10
                {{ $type === $key ? 'border-[#6D0D23] text-[#6D0D23]' : 'border-transparent text-gray-700 hover:text-[#6D0D23]' }}">
                {{ $label }}
                <span class="rounded-full px-2 py-0.5 text-xs font-semibold {{ $type === $key ? 'bg-[#6D0D23] text-white' : 'bg-gray-100 text-gray-600' }}">{{ $counts[$key] ?? 0 }}</span>
            </a>
            @endforeach
        </nav>
    </div>

    {{-- Filters --}}
    <form method="GET" action="{{ route('admin.archive.index') }}" class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-end">
        <input type="hidden" name="type" value="{{ $type }}">

        <div class="flex-1">
            <label for="archive-q" class="mb-1 block text-sm font-medium">Search</label>
            <input id="archive-q" type="search" name="q" value="{{ $search }}"
                placeholder="{{ $type === 'cohorts' ? 'e.g. Cohort 2' : 'Startup name or category' }}"
                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-[#6D0D23] focus:outline-none focus:ring-2 focus:ring-[#6D0D23]/20">
        </div>

        @if (count($statuses) > 1)
        <div class="sm:w-44">
            <label for="archive-status" class="mb-1 block text-sm font-medium">Status</label>
            <select id="archive-status" name="status" onchange="this.form.submit()"
                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-[#6D0D23] focus:outline-none">
                <option value="all" @selected($status === 'all')>All</option>
                @foreach ($statuses as $option)
                <option value="{{ $option }}" @selected($status === $option)>{{ $option }}</option>
                @endforeach
            </select>
        </div>
        @endif

        <div class="sm:w-44">
            <label for="archive-sort" class="mb-1 block text-sm font-medium">Sort</label>
            <select id="archive-sort" name="sort" onchange="this.form.submit()"
                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-[#6D0D23] focus:outline-none">
                <option value="newest" @selected($sort === 'newest')>Newest first</option>
                <option value="oldest" @selected($sort === 'oldest')>Oldest first</option>
            </select>
        </div>

        <div class="flex gap-2">
            <button type="submit" class="h-[38px] rounded-lg bg-gradient-to-r from-[#6D0D23] to-[#11386A] px-5 text-sm font-semibold text-white transition hover:opacity-95">Apply</button>
            @if ($search !== '' || $status !== 'all' || $sort !== 'newest')
            <a href="{{ route('admin.archive.index', ['type' => $type]) }}" class="flex h-[38px] items-center rounded-lg border border-gray-300 bg-white px-4 text-sm font-medium text-gray-700 transition hover:bg-gray-50">Clear</a>
            @endif
        </div>
    </form>

    @if ($type !== 'cohorts')
    <p class="mb-3 text-xs text-gray-500">Follows the cohort selected in the sidebar.</p>
    @endif

    <div class="overflow-hidden rounded-xl border border-gray-200 bg-white">
        <div class="overflow-x-auto">
            <table class="w-full min-w-[720px] text-sm">
                <thead>
                    <tr class="bg-gradient-to-r from-[#6D0D23] to-[#11386A] text-white">
                        <th class="px-4 py-3 text-left font-semibold" style="padding-left: 24px;">{{ $nameLabel }}</th>
                        <th class="px-4 py-3 text-left font-semibold">{{ $detailLabel }}</th>
                        <th class="px-4 py-3 text-center font-semibold">Status</th>
                        <th class="px-4 py-3 text-center font-semibold">{{ $dateLabel }}</th>
                        <th class="px-4 py-3 text-center font-semibold">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($rows as $row)
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3 font-medium text-gray-900" style="padding-left: 24px;">{{ $row['name'] }}</td>
                        <td class="px-4 py-3 text-gray-600">{{ $row['detail'] }}</td>
                        <td class="px-4 py-3 text-center">
                            <span class="inline-block rounded-full border px-2.5 py-0.5 text-xs font-semibold {{ $statusTone($row['status']) }}">{{ $row['status'] }}</span>
                        </td>
                        <td class="px-4 py-3 text-center text-gray-600 whitespace-nowrap">{{ $row['date']?->format('M j, Y') ?? '—' }}</td>
                        <td class="px-4 py-3">
                            <div class="flex items-center justify-center gap-2" x-data="{ confirm: false }">
                                @php
                                $restore = match (true) {
                                    $type === 'cohorts' => ['route' => route('admin.cohorts.unarchive', $row['id']), 'method' => 'PATCH', 'label' => 'Unarchive', 'confirm' => 'Make '.$row['name'].' an active cohort again?'],
                                    $type === 'roadblocks' && $row['status'] === 'Resolved' => ['route' => route('admin.roadblocks.recover', $row['id']), 'method' => 'POST', 'label' => 'Recover', 'confirm' => 'Move this roadblock back to Pending Review?'],
                                    $type === 'meetings' && $row['status'] === 'Resolved' => ['route' => route('admin.assessment-hub.meetings.recover', $row['id']), 'method' => 'POST', 'label' => 'Recover', 'confirm' => 'Move this meeting back to Pending Review?'],
                                    default => null,
                                };
                                $viewUrl = match ($type) {
                                    'roadblocks' => route('admin.roadblocks.index', ['tab' => 'archive', 'stage' => strtolower($row['status']) === 'failed' ? 'failed' : 'resolved']),
                                    'meetings' => route('admin.assessment-hub.index', ['main' => 'assessment', 'stage' => 'Meetings', 'meeting_tab' => 'archive', 'meeting_stage' => strtolower($row['status'])]),
                                    default => null,
                                };
                                @endphp

                                @if ($viewUrl)
                                <a href="{{ $viewUrl }}" class="{{ $btn }} border border-gray-300 bg-white text-gray-700 hover:bg-gray-50">View</a>
                                @endif

                                @if ($restore)
                                <button type="button" @click="confirm = true"
                                    class="{{ $btn }} bg-gradient-to-r from-[#6D0D23] to-[#11386A] text-white hover:opacity-95">
                                    {{ $restore['label'] }}
                                </button>

                                <template x-teleport="body">
                                    <div x-show="confirm" x-cloak x-transition.opacity
                                        class="fixed inset-0 z-[200] flex items-center justify-center bg-black/50 p-4" style="display:none;"
                                        @keydown.escape.window="confirm = false">
                                        <div class="w-full max-w-md rounded-2xl bg-white p-6 text-center shadow-2xl" @click.outside="confirm = false">
                                            <h3 class="bg-gradient-to-r from-[#6D0D23] to-[#11386A] bg-clip-text text-lg font-bold text-transparent">{{ $restore['label'] }}</h3>
                                            <p class="mt-2 text-sm text-gray-600">{{ $restore['confirm'] }}</p>
                                            <form method="POST" action="{{ $restore['route'] }}" class="mt-5 grid grid-cols-2 gap-3">
                                                @csrf
                                                @if ($restore['method'] !== 'POST') @method($restore['method']) @endif
                                                <input type="hidden" name="from" value="archive">
                                                <button type="button" @click="confirm = false" class="h-10 rounded-md border border-gray-300 bg-white text-sm font-bold text-gray-800 hover:bg-gray-50">Cancel</button>
                                                <button type="submit" class="h-10 rounded-md bg-gradient-to-r from-[#6D0D23] to-[#11386A] text-sm font-bold text-white hover:opacity-95">{{ $restore['label'] }}</button>
                                            </form>
                                        </div>
                                    </div>
                                </template>
                                @elseif (! $viewUrl)
                                <span class="text-xs text-gray-400">—</span>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5" class="px-4">
                            <x-empty-state variant="all-clear" size="md" title="Nothing Archived Here." highlight="Archived" message="{{ $search !== '' || $status !== 'all' ? 'No archived records match these filters.' : 'Archived '.strtolower($types[$type]).' will appear here.' }}" />
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-layouts.admin>
