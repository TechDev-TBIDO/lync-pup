@php
$gradient = 'bg-gradient-to-r from-[#6D0D23] to-[#11386A]';

$avatar = function ($startup) {
$url = $startup->startup_photo_url ?? null;
$name = $startup->company_name ?? '?';
return $url
? '<img src="'.e($url).'" alt="" class="h-full w-full object-cover">'
: '<span class="text-[10px] font-bold text-gray-500">'.e(mb_strtoupper(mb_substr($name, 0, 1))).'</span>';
};

$nameLen = $rejectedStartups->map(fn ($s) => mb_strlen($s->company_name ?? ''))->max() ?: 12;
$nameCell = 'width: calc(1.5rem + 0.5rem + '.min(max($nameLen, 8), 28).'ch)';
@endphp

@if (session('startup_deleted'))
<div class="mb-4 flex items-center gap-2 rounded-lg border border-green-200 bg-green-50 p-4 text-sm text-green-800">
    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
    </svg>
    <strong>{{ session('startup_deleted') }}</strong>&nbsp;has been removed.
</div>
@endif

<div class="mb-3 flex items-center gap-2">
    <img src="{{ asset('images/icons/warning-circle.svg') }}" alt="" class="h-6 w-6" aria-hidden="true">
    <h2 class="text-md font-bold text-gray-900">Rejected</h2>
</div>

<div class="border border-gray-200 rounded-xl overflow-hidden bg-white">
    <div class="overflow-x-auto">
        <table class="w-full min-w-[760px] table-fixed text-sm">
            <thead>
                <tr class="{{ $gradient }} text-white text-center">
                    <th class="px-3 py-2 text-left text-[11px] font-semibold tracking-wider">Startup</th>
                    <th class="px-3 py-2 text-[11px] font-semibold tracking-wider">Rejected On</th>
                    <th class="px-3 py-2 text-[11px] font-semibold tracking-wider">Resubmit By</th>
                    <th class="px-3 py-2 text-[11px] font-semibold tracking-wider">Countdown</th>
                    <th class="px-3 py-2 text-left text-[11px] font-semibold tracking-wider">Evaluator Remarks</th>
                    <th class="px-3 py-2 text-[11px] font-semibold tracking-wider">Action</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($rejectedStartups as $startup)
                @php
                    $rejectedAt = $startup->informationSheet?->rejected_at;
                    $deadline = $startup->rejectionDeadline();
                    $daysLeft = $deadline ? now()->startOfDay()->diffInDays($deadline->copy()->startOfDay(), false) : null;
                    $overdue = $daysLeft !== null && $daysLeft < 0;
                @endphp
                <tr x-data="{ confirmingDelete: false, deleting: false, reason: '', confirmText: '' }" class="border-b border-gray-100 last:border-0">
                    <td class="px-3 py-2 text-left">
                        <div class="flex justify-start">
                            <div class="inline-flex max-w-full items-center gap-2 text-left text-xs" style="{{ $nameCell }}">
                                <span class="flex h-6 w-6 shrink-0 items-center justify-center overflow-hidden rounded-full bg-gray-100 ring-1 ring-gray-200">
                                    {!! $avatar($startup) !!}
                                </span>
                                <span class="min-w-0 flex-1 truncate text-xs font-medium text-gray-900" title="{{ $startup->company_name }}">{{ $startup->company_name }}</span>
                            </div>
                        </div>
                    </td>
                    <td class="px-3 py-2 whitespace-nowrap text-center text-xs text-gray-600">
                        {{ $rejectedAt?->format('M d, Y') ?? '—' }}
                    </td>
                    <td class="px-3 py-2 whitespace-nowrap text-center text-xs text-gray-600">
                        {{ $deadline?->format('M d, Y') ?? '—' }}
                    </td>
                    <td class="px-3 py-2 text-center">
                        @if ($daysLeft === null)
                        <span class="text-xs text-gray-400">—</span>
                        @elseif ($overdue)
                        <span class="rounded-full border border-red-300 px-2.5 py-1 text-[11px] font-semibold text-red-700">Overdue</span>
                        @else
                        <span class="rounded-full border border-amber-300 px-2.5 py-1 text-[11px] font-semibold text-amber-700">{{ $daysLeft }} day{{ $daysLeft === 1 ? '' : 's' }} left</span>
                        @endif
                    </td>
                    <td class="px-3 py-2 text-left text-xs text-gray-600">
                        <span class="line-clamp-2" title="{{ $startup->informationSheet?->evaluator_remarks }}">
                            {{ $startup->informationSheet?->evaluator_remarks ?: '—' }}
                        </span>
                    </td>
                    <td class="px-3 py-2 text-center">
                        <div class="flex items-center justify-center gap-2">
                            <a href="{{ route('admin.information-sheet.show', ['startup' => $startup, 'from' => 'assessment-hub', 'tab' => 'rejected']) }}"
                                class="inline-flex h-8 items-center justify-center whitespace-nowrap rounded-md border border-[#6D0D23] px-3 text-[11px] font-semibold text-[#6D0D23] transition hover:bg-[#6D0D23]/5">
                                View
                            </a>
                            <button type="button" @click="confirmingDelete = true"
                                class="inline-flex h-8 items-center justify-center gap-1.5 whitespace-nowrap rounded-md bg-red-700 px-3 text-[11px] font-semibold text-white transition hover:opacity-90">
                                Delete
                            </button>
                        </div>

                        {{-- ============ DELETE STARTUP MODAL ============ --}}
                        <div x-show="confirmingDelete" x-cloak
                            class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" style="display:none;"
                            >
                            <div class="w-full max-w-md overflow-hidden rounded-xl bg-white text-left shadow-xl">
                                <div class="flex items-center justify-between bg-gradient-to-r from-[#6D0D23] to-[#11386A] px-6 py-5 text-white">
                                    <div class="flex items-center gap-3">
                                        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M17 20H4a2 2 0 01-2-2V6a2 2 0 012-2h5l2 2h9a2 2 0 012 2v10a2 2 0 01-2 2h-1" />
                                        </svg>
                                        <h3 class="text-base font-bold">Delete Startup</h3>
                                    </div>
                                    <button type="button" @click="confirmingDelete = false"
                                        class="flex h-6 w-6 items-center justify-center rounded-full border border-white text-white transition hover:border-transparent hover:bg-white hover:text-[#6D0D23]"
                                        aria-label="Close">
                                        <svg class="h-3 w-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M18 6L6 18M6 6l12 12" />
                                        </svg>
                                    </button>
                                </div>

                                <form method="POST" action="{{ route('admin.assessment-hub.rejected.destroy', $startup) }}" class="px-6 pb-6 pt-5"
                                    @submit="deleting = true">
                                    @csrf
                                    @method('DELETE')

                                    <div class="mb-4 flex justify-center">
                                        <div class="flex h-14 w-14 items-center justify-center rounded-full bg-rose-50">
                                            <svg class="h-7 w-7 text-rose-700" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m0 3.75h.007M10.29 3.86 1.82 18a1.5 1.5 0 001.28 2.25h17.8a1.5 1.5 0 001.28-2.25L13.71 3.86a1.5 1.5 0 00-2.42 0Z" />
                                            </svg>
                                        </div>
                                    </div>

                                    <p class="text-center text-lg font-bold text-gray-900">Delete Startup Account</p>
                                    <p class="mt-1 text-center text-sm text-gray-500">
                                        Are you sure you want to delete this rejected startup?<br>This action is permanent and cannot be undone. The founder will be notified by email.
                                    </p>

                                    <p class="mt-4 text-sm font-semibold text-gray-700">Startup:</p>
                                    <p class="text-base font-bold text-gray-900">{{ $startup->company_name }}</p>

                                    <label class="mt-4 mb-1 block text-sm font-medium text-gray-700">
                                        Reason for Deletion <span class="text-red-600">*</span>
                                    </label>
                                    <input type="text" name="reason" x-model="reason" required placeholder="e.g. Did not resubmit within the 10-day window"
                                        class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
                                    @error('reason') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror

                                    <label class="mt-4 mb-1 block text-sm font-medium text-gray-700">
                                        Type <span class="font-bold text-rose-800">DELETE</span> to confirm
                                    </label>
                                    <input type="text" name="confirm" x-model="confirmText" required placeholder="DELETE"
                                        class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
                                    @error('confirm') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror

                                    <div class="mt-5 flex gap-3">
                                        <button type="button" @click="confirmingDelete = false" :disabled="deleting"
                                            class="flex-1 rounded-lg border border-gray-300 bg-white py-2.5 text-sm font-semibold text-gray-700 transition hover:bg-gray-50 disabled:opacity-50">
                                            Cancel
                                        </button>
                                        <button type="submit" :disabled="deleting || confirmText !== 'DELETE'"
                                            class="flex-1 rounded-lg bg-gradient-to-r from-[#6D0D23] to-[#11386A] py-2.5 text-sm font-semibold text-white transition hover:opacity-95 disabled:cursor-not-allowed disabled:opacity-40">
                                            <span x-show="!deleting">Confirm Deletion</span>
                                            <span x-show="deleting">Processing…</span>
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="6" class="px-4 py-8 text-center text-sm text-gray-400">No rejected startups right now.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
