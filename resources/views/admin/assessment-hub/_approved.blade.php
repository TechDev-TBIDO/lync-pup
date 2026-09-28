@php
$approvedRows = $approvedStartups->map(function ($startup) {
$updatedAt = $startup->informationSheet?->updated_at;

return [
'id' => $startup->startup_id,
'name' => $startup->company_name,
'category' => $startup->industry_sector ?? '—',
'approved_date' => $updatedAt?->format('M d, Y') ?? '—',
'month_key' => $updatedAt?->format('Y-m') ?? 'unknown',
'month_label' => $updatedAt?->format('F, Y') ?? 'Unknown',
'photo_url' => $startup->startup_photo_path
? \Illuminate\Support\Facades\Storage::url($startup->startup_photo_path)
: null,
'initial' => strtoupper(substr($startup->company_name, 0, 1)),
'view_url' => route('admin.information-sheet.show', ['startup' => $startup, 'from' => 'assessment-hub', 'tab' => 'approved']),
];
})->sortByDesc('month_key')->values();
@endphp

@if (session('just_approved'))
<div class="bg-green-50 border border-green-200 text-green-800 text-sm rounded-lg p-4 mb-4 flex items-center gap-2">
    <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor"
        stroke-width="2" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
    </svg>
    These startups have been approved and added to Startup Directory.
</div>
@endif

<div
    x-data="{
        startups: @js($approvedRows),
        month: 'all',
        page: 1,
        perPage: 3,
        // Width of the logo + name block: sized to the longest name
        // *currently on screen* (not the whole, unfiltered dataset) so the
        // logos still align across the visible rows without the block
        // ballooning — and pulling the Startup column way wider than the
        // other columns — just because some other startup elsewhere in the
        // list happens to have a long name.
        get nameCh() {
            return Math.min(Math.max(8, ...this.paged.map(s => (s.name || '').length)) + 1, 32);
        },
        get months() {
            return [...new Map(this.startups.map(s => [s.month_key, s.month_label])).entries()];
        },
        get filtered() {
            return this.month === 'all' ? this.startups : this.startups.filter(s => s.month_key === this.month);
        },
        get totalPages() {
            return Math.max(1, Math.ceil(this.filtered.length / this.perPage));
        },
        get paged() {
            const start = (this.page - 1) * this.perPage;
            return this.filtered.slice(start, start + this.perPage);
        },
        prevPage() {
            if (this.page > 1) this.page--;
        },
        nextPage() {
            if (this.page < this.totalPages) this.page++;
        },
    }"
    x-init="$watch('month', () => page = 1); $watch('perPage', () => page = 1)">
    <div class="relative mb-4 inline-block w-full max-w-xs" x-data="{ open: false }"
        @click.outside="open = false" @keydown.escape="open = false">

        <button type="button" @click="open = !open"
            class="flex w-full items-center justify-between gap-2 rounded-lg border border-gray-300 bg-white py-2 pl-3 pr-2 text-sm text-gray-700 transition hover:border-gray-400">
            <span x-text="month === 'all' ? 'All Months' : (months.find(m => m[0] === month)?.[1] ?? 'All Months')"></span>
            <svg class="h-4 w-4 shrink-0 text-gray-400 transition" :class="open && 'rotate-180'"
                fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
            </svg>
        </button>

        <div x-show="open" x-cloak x-transition.origin.top
            class="absolute left-0 top-full z-30 mt-1 max-h-60 w-full overflow-y-auto rounded-lg border border-gray-200 bg-white shadow-lg"
            style="display:none;">

            <button type="button" x-show="month !== 'all'" @click="month = 'all'; open = false"
                class="w-full px-3 py-2 text-left text-sm text-gray-700 transition hover:bg-gradient-to-r hover:from-[#6D0D23] hover:to-[#11386A] hover:text-white">
                All Months
            </button>

            <template x-for="[key, label] in months" :key="key">
                <button type="button" x-show="month !== key" @click="month = key; open = false"
                    class="w-full px-3 py-2 text-left text-sm text-gray-700 transition hover:bg-gradient-to-r hover:from-[#6D0D23] hover:to-[#11386A] hover:text-white"
                    x-text="label">
                </button>
            </template>
        </div>
    </div>
    <div class="overflow-x-auto rounded-xl border">
    <table class="w-full min-w-[620px] table-fixed text-sm">
        <thead>
            <tr class="bg-gradient-to-r from-[#6D0D23] to-[#11386A] text-white text-center">
                <th class="whitespace-nowrap px-4 py-3 text-left text-sm font-semibold tracking-wider">Startup</th>
                <th class="whitespace-nowrap px-4 py-3 text-sm font-semibold tracking-wider">Approved Date</th>
                <th class="whitespace-nowrap px-4 py-3 text-sm font-semibold tracking-wider">Category</th>
                <th class="whitespace-nowrap px-4 py-3 text-sm font-semibold tracking-wider">Action</th>
            </tr>
        </thead>
        <tbody>
            <template x-for="s in paged" :key="s.id">
                <tr class="border-b">
                    <td class="px-4 py-3 text-left">
                        <div class="flex justify-start">
                            <div class="inline-flex max-w-full items-center gap-3 text-left"
                                :style="`width: calc(2.25rem + 0.75rem + ${nameCh}ch)`">
                                <div class="h-9 w-9 rounded-full overflow-hidden bg-gradient-to-br from-rose-900 to-blue-950 text-white text-xs font-bold flex items-center justify-center flex-shrink-0">
                                    <template x-if="s.photo_url">
                                        <img :src="s.photo_url" class="h-full w-full object-cover">
                                    </template>
                                    <template x-if="!s.photo_url">
                                        <span x-text="s.initial"></span>
                                    </template>
                                </div>
                                <span class="min-w-0 flex-1 truncate" :title="s.name" x-text="s.name"></span>
                            </div>
                        </div>
                    </td>
                    <td class="px-4 py-3 text-center" x-text="s.approved_date"></td>
                    <td class="px-4 py-3 text-center" x-text="s.category"></td>
                    <td class="px-4 py-3 text-center">
                        <a :href="s.view_url"
                            class="inline-flex items-center justify-center whitespace-nowrap rounded-md border border-[#6D0D23] px-4 py-1 text-sm font-semibold text-[#6D0D23] transition hover:bg-[#6D0D23]/5">
                            View
                        </a>
                    </td>
                </tr>
            </template>
            <template x-if="paged.length === 0">
                <tr>
                    <td colspan="4" class="px-4">
                        <x-empty-state variant="documents" size="md" title="No Approved Information Sheets Yet." highlight="Information Sheets" message="Approved sheets will appear here once startups pass review." />
                    </td>
                </tr>
            </template>
        </tbody>
    </table>
    </div>

    <div class="flex items-center justify-between mt-4 text-sm text-gray-600" x-show="filtered.length > 0">
        <div class="flex items-center gap-2">
            <span>Items per page:</span>
            <select x-model.number="perPage" class="border rounded-lg pl-3 pr-8 py-1 text-sm">
                <option value="3">3</option>
                <option value="5">5</option>
                <option value="10">10</option>
                <option value="25">25</option>
            </select>
        </div>

        <div class="flex items-center gap-3">
            <button type="button" @click="prevPage()" :disabled="page === 1"
                class="h-8 w-8 flex items-center justify-center rounded-lg border disabled:opacity-40 disabled:cursor-not-allowed hover:bg-gray-50">
                &lsaquo;
            </button>
            <span>Page <span x-text="page"></span> of <span x-text="totalPages"></span></span>
            <button type="button" @click="nextPage()" :disabled="page === totalPages"
                class="h-8 w-8 flex items-center justify-center rounded-lg border disabled:opacity-40 disabled:cursor-not-allowed hover:bg-gray-50">
                &rsaquo;
            </button>
        </div>
    </div>
</div>