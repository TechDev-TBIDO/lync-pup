<x-layouts.admin title="Cohort Management">
    <div class="flex items-center justify-between mb-6">
        <div>
            <a href="{{ route('admin.founder-applications.index') }}" class="inline-flex items-center gap-1 text-sm text-gray-500 hover:text-[#6D0D23] mb-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                </svg>
                Back to Founder Registrations
            </a>
            <h1 class="text-3xl font-bold text-gray-900">Cohort Management</h1>
            <p class="text-gray-500 mt-1">Manage the list of cohorts founders can be assigned to when approved.</p>
        </div>

        <div x-data="{ open: {{ $errors->any() && old('_method') !== 'PATCH' ? 'true' : 'false' }} }">
            <button
                @click="open = true"
                class="flex items-center gap-2 bg-gradient-to-r from-[#6D0D23] to-[#11386A]
           hover:opacity-90 transition-all duration-200
           text-white text-sm font-medium rounded-lg px-4 py-2.5 shadow-md">
                <span class="text-lg leading-none">+</span>
                Create Cohort
            </button>

            <div x-show="open" x-cloak class="fixed inset-0 bg-black/50 flex items-center justify-center z-50" style="display: none;">
                <div class="relative bg-white rounded-xl w-full max-w-md overflow-hidden">
                    <x-cohort-form-modal mode="add" :action="route('admin.cohorts.store')" />
                </div>
            </div>
        </div>
    </div>

    <div class="bg-white border border-gray-200 rounded-xl overflow-hidden">
        <table class="w-full text-sm">
            <thead>
                <tr class="bg-gradient-to-r from-[#6D0D23] to-[#11386A] text-white text-left">
                    <th class="px-4 py-3 font-semibold">Cohort Name</th>
                    <th class="px-4 py-3 font-semibold">Start Date</th>
                    <th class="px-4 py-3 font-semibold">End Date</th>
                    <th class="px-4 py-3 font-semibold">Status</th>
                    <th class="px-4 py-3 font-semibold">Startups</th>
                    <th class="px-4 py-3 font-semibold">Action</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($cohorts as $cohort)
                <tr x-data="{
                        editOpen: {{ $errors->any() && old('_method') === 'PATCH' && (int) old('_cohort_id') === $cohort->cohort_id ? 'true' : 'false' }},
                        archiveOpen: false,
                        deleteOpen: false,
                    }">
                    <td class="px-4 py-3 font-medium text-gray-900">{{ $cohort->display_label }}</td>
                    <td class="px-4 py-3 text-gray-600">{{ optional($cohort->start_date)->format('M d, Y') ?? '—' }}</td>
                    <td class="px-4 py-3 text-gray-600">{{ optional($cohort->end_date)->format('M d, Y') ?? '—' }}</td>
                    <td class="px-4 py-3">
                        <span class="rounded-full border px-2.5 py-1 text-[11px] font-semibold
                            {{ $cohort->status === 'Active' ? 'border-blue-300 text-blue-800' : 'border-gray-300 text-gray-600' }}">
                            {{ $cohort->status_label }}
                        </span>
                    </td>
                    <td class="px-4 py-3 text-gray-600">{{ $cohort->startups_count }}</td>
                    <td class="px-4 py-3">
                        <div class="flex items-center gap-2">
                            <button @click="editOpen = true" class="border rounded-lg px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50">Edit</button>
                            @if (! $cohort->isArchived())
                                <button @click="archiveOpen = true" class="border rounded-lg px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50">Archive</button>
                            @endif
                            <button @click="deleteOpen = true" class="border border-red-200 rounded-lg px-3 py-1.5 text-xs font-medium text-red-600 hover:bg-red-50">Delete</button>
                        </div>

                        <div x-show="editOpen" x-cloak class="fixed inset-0 bg-black/50 flex items-center justify-center z-50" style="display: none;">
                            <div class="relative bg-white rounded-xl w-full max-w-md overflow-hidden">
                                <x-cohort-form-modal mode="edit" :cohort="$cohort" :action="route('admin.cohorts.update', $cohort)" />
                            </div>
                        </div>

                        <div x-show="archiveOpen" x-cloak x-transition.opacity class="fixed inset-0 z-50 flex items-center justify-center bg-black/50" style="display: none;">
                            <div class="w-full max-w-md rounded-2xl bg-white shadow-2xl">
                                <div class="px-8 py-8 text-center">
                                    <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-amber-100">
                                        <svg class="h-7 w-7 text-amber-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 8h14M5 8a2 2 0 01-2-2V5a1 1 0 011-1h16a1 1 0 011 1v1a2 2 0 01-2 2M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8M10 12h4" />
                                        </svg>
                                    </div>
                                    <h2 class="mt-5 text-xl font-semibold text-gray-900">Archive {{ $cohort->display_label }}</h2>
                                    <p class="mt-3 text-sm leading-6 text-gray-600">
                                        This marks the cohort as Archived and removes it from the Active list. Startup records and history are kept intact.
                                    </p>
                                    <div class="mt-8 flex justify-center gap-3">
                                        <button type="button" @click="archiveOpen = false" class="rounded-lg border border-gray-300 px-5 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-100 transition">Cancel</button>
                                        <form method="POST" action="{{ route('admin.cohorts.archive', $cohort) }}">
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit" class="rounded-lg bg-gradient-to-r from-[#6D0D23] to-[#11386A] px-5 py-2.5 text-sm font-medium text-white shadow-md hover:opacity-90 transition">Archive</button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div x-show="deleteOpen" x-cloak x-transition.opacity class="fixed inset-0 z-50 flex items-center justify-center bg-black/50" style="display: none;">
                            <div class="w-full max-w-md rounded-2xl bg-white shadow-2xl">
                                <div class="px-8 py-8 text-center">
                                    <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-red-100">
                                        <svg class="h-7 w-7 text-red-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01M5.07 19h13.86c1.54 0 2.5-1.67 1.73-3L13.73 4c-.77-1.33-2.69-1.33-3.46 0L3.34 16c-.77 1.33.19 3 1.73 3z" />
                                        </svg>
                                    </div>
                                    <h2 class="mt-5 text-xl font-semibold text-gray-900">Delete {{ $cohort->display_label }}</h2>
                                    <p class="mt-3 text-sm leading-6 text-gray-600">
                                        This won't affect founders already assigned to it — they'll simply lose their cohort link. This action cannot be undone.
                                    </p>
                                    <div class="mt-8 flex justify-center gap-3">
                                        <button type="button" @click="deleteOpen = false" class="rounded-lg border border-gray-300 px-5 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-100 transition">Cancel</button>
                                        <form method="POST" action="{{ route('admin.cohorts.destroy', $cohort) }}">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="rounded-lg bg-gradient-to-r from-[#6D0D23] to-[#11386A] px-5 py-2.5 text-sm font-medium text-white shadow-md hover:opacity-90 transition">Delete</button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="6" class="px-4 py-8 text-center text-gray-500">No cohorts yet.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-layouts.admin>
