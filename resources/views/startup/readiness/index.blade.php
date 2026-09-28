<x-layouts.founder title="Readiness Result">

    <div class="mb-6 flex flex-wrap items-start justify-between gap-4">
        <div>
            <h1 class="text-3xl font-bold text-gray-900">Readiness Result</h1>
            <p class="text-gray-500 mt-1">Your latest Readiness Level Assessment encoded by TBIDO Admin</p>
        </div>

        {{-- Stage switcher (Pre-Assessment / Post-Assessment) --}}
        <div class="relative" x-data="{ open: false }">
            <button type="button" @click="open = !open"
                class="inline-flex items-center gap-2 rounded-lg border-2 border-[#6D0D23] px-4 py-2 text-sm font-semibold text-[#6D0D23] transition hover:bg-[#6D0D23]/5">
                {{ $stage }}
                {{-- The other stage has a new/updated result. --}}
                @if (array_diff($newStages ?? [], [$stage]) !== []) <x-new-dot /> @endif
                <svg class="h-4 w-4" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M5 7.5l5 5 5-5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
                </svg>
            </button>

            <div x-show="open" x-cloak @click.away="open = false"
                class="absolute right-0 z-20 mt-2 overflow-hidden rounded-lg border border-gray-200 bg-white p-1 shadow-xl"
                style="width: 200px;">
                @foreach ($stages as $s)
                    <a href="{{ route('startup.readiness.index', ['stage' => $s]) }}"
                        {{-- The gradient is the hover highlight, so the current stage is a tint
                             instead. Two filled rows at once read as one solid block with nothing
                             to tell them apart. --}}
                        class="block rounded-md px-3 py-2 text-sm transition-colors hover:bg-gradient-to-r hover:from-[#6C0E24] hover:to-[#11386A] hover:text-white
                            {{ $s === $stage ? 'bg-rose-50 font-semibold text-[#6D0D23]' : 'text-gray-700' }}">
                        <span class="inline-flex items-center gap-1.5">{{ $s }} @if (in_array($s, $newStages ?? [], true)) <x-new-dot /> @endif</span>
                    </a>
                @endforeach
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- Overall Readiness --}}
        <div class="rounded-2xl overflow-hidden border border-gray-100 bg-white shadow-sm">
            {{-- Same treatment as the dashboard's Overall Readiness card: the
                 #6C0E24 -> #AE0129 gradient left to right, p-5/sm:p-6, a
                 34/44px score and a text-xs pill. Kept identical so the founder
                 sees one card, not two that nearly match. --}}
            <div class="bg-gradient-to-r from-[#6C0E24] to-[#AE0129] p-5 text-white sm:p-6">
                <p class="flex items-center gap-1.5 text-xs font-semibold uppercase tracking-wide text-white/80">
                    Overall Readiness
                    {{-- New/updated result on Pre- OR Post-Assessment since the last visit. --}}
                    @if (! empty($newStages))
                        <x-new-dot style="background:#EF4444;" />
                    @endif
                </p>
                <p class="mt-2 text-[34px] font-bold leading-none sm:text-[44px]">
                    {{ $assessment && $assessment->overall_score !== null ? number_format($assessment->overall_score, 1) : '—' }}
                    <span class="text-base font-medium text-white/70 sm:text-lg">/9</span>
                </p>
                <span class="mt-3 inline-flex items-center gap-1.5 rounded-full bg-white/15 px-3 py-1.5 text-xs font-semibold sm:mt-4">
                    <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M3 13l5-5 3 3 6-6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
                        <path d="M13 5h4v4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
                    </svg>
                    {{ $overallLabel }}
                </span>
            </div>
            <div class="p-6">
                <x-readiness-radar
                    :trl="$assessment->trl_score ?? 0"
                    :mrl="$assessment->mrl_score ?? 0"
                    :tmrl="$assessment->tmrl_score ?? 0"
                    :srl="$assessment->srl_score ?? 0" />
            </div>
        </div>

        {{-- TRL / MRL / TMRL / SRL breakdown --}}
        <div class="lg:col-span-2 rounded-2xl border border-gray-100 bg-white shadow-sm p-6 md:p-8 space-y-8">
            @foreach (\App\Support\ReadinessRubric::TYPES as $type)
                @php
                    $score = $assessment?->scoreFor($type);
                    $pct = $score !== null ? max(0, min(100, ($score / 9) * 100)) : 0;
                    $typeMeta = $meta[$type] ?? ['label' => $type, 'description' => ''];
                @endphp
                <div>
                    <h2 class="text-2xl font-bold text-gray-900">{{ $type }}</h2>
                    <p class="text-gray-500 mt-1">
                        {{ $typeMeta['label'] }} &mdash; {{ lcfirst(rtrim($typeMeta['description'], '.')) }}
                    </p>

                    {{-- Score sits at the end of the bar, on the same line. It used to be a
                         flex-wrap sibling of the heading, which dropped it onto its own line
                         under the description at narrow widths - reading as a stray "/9". --}}
                    <div class="mt-3 flex items-center gap-4">
                        <div class="h-2.5 flex-1 rounded-full bg-rose-100">
                            <div class="h-2.5 rounded-full bg-[#6D0D23]" style="width: {{ $pct }}%"></div>
                        </div>
                        <p class="shrink-0 font-bold text-[#6D0D23] whitespace-nowrap" style="font-size: 24px; line-height: 1;">
                            {{ $score !== null ? number_format($score, 1) : '—' }}<span class="text-sm font-medium text-gray-400">/9</span>
                        </p>
                    </div>
                </div>
            @endforeach
        </div>
    </div>

</x-layouts.founder>
