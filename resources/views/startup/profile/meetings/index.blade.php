<x-layouts.founder title="Meeting">

    @php
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
            @endphp

            <div class="mb-6">
                <h1 class="text-3xl font-bold text-gray-900">Meeting</h1>
                <p class="text-gray-500 mt-1">View your meetings.</p>
            </div>

            <div class="flex items-center gap-2 mb-6">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6 text-[#6D0D23]" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.094 9.094 0 003.741-.479 3 3 0 00-4.682-2.72m.94 3.198l.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0112 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 016 18.719m12 0a5.971 5.971 0 00-.941-3.197m0 0A5.995 5.995 0 0012 12.75a5.995 5.995 0 00-5.058 2.772m0 0a3 3 0 00-4.681 2.72 8.986 8.986 0 003.74.477m.94-3.197a5.971 5.971 0 00-.94 3.197M15 6.75a3 3 0 11-6 0 3 3 0 016 0zm6 3a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0zm-13.5 0a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0z" />
                </svg>
                <h2 class="font-bold text-gray-900">Meetings</h2>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                @forelse ($meetings as $meeting)

                @php
                // Evaluations don't carry this key, hence the null coalesce
                $canJoin = $meeting['can_join'] ?? false;
                @endphp

                <div class="flex bg-white border rounded-2xl overflow-hidden">
                    {{-- Left band --}}
                    <div class="w-40 flex-shrink-0 bg-gradient-to-b from-[#6D0D23] to-[#11386A] text-white flex flex-col items-center justify-center text-center px-4 py-6">
                        @if ($meeting['type'] === 'mentorship')
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-9 h-9 mb-3" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.094 9.094 0 003.741-.479 3 3 0 00-4.682-2.72m.94 3.198l.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0112 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 016 18.719m12 0a5.971 5.971 0 00-.941-3.197m0 0A5.995 5.995 0 0012 12.75a5.995 5.995 0 00-5.058 2.772m0 0a3 3 0 00-4.681 2.72 8.986 8.986 0 003.74.477m.94-3.197a5.971 5.971 0 00-.94 3.197M15 6.75a3 3 0 11-6 0 3 3 0 016 0zm6 3a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0zm-13.5 0a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0z" />
                        </svg>
                        <p class="font-bold text-lg leading-tight">Mentorship</p>
                        <p class="text-xs text-white/70 mt-3">Roadblock:</p>
                        <p class="text-xs text-white/90">{{ $meeting['roadblock_category'] }}</p>
                        @else
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-9 h-9 mb-3" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 0H6.108c-1.135 0-2.098.847-2.192 1.98a48.424 48.424 0 000 7.86c.094 1.133 1.057 1.98 2.192 1.98h7.284c1.135 0 2.098-.847 2.192-1.98.075-.907.093-1.827.05-2.734M8.25 3v6a.75.75 0 00.75.75h6" />
                        </svg>
                        <p class="font-bold text-lg leading-tight">Evaluation</p>
                        @endif
                    </div>

                    {{-- Info rows --}}
                    <div class="flex-1 px-5 py-5 space-y-3 divide-y">
                        <div class="flex items-start gap-3">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-[#6D0D23] flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5" />
                            </svg>
                            <div>
                                <p class="font-semibold text-sm text-gray-900">Date</p>
                                <p class="text-sm text-gray-600">{{ $meeting['date_label'] }}</p>
                            </div>
                        </div>

                        <div class="flex items-start gap-3 pt-3">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-[#6D0D23] flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <div>
                                <p class="font-semibold text-sm text-gray-900">Time</p>
                                <p class="text-sm text-gray-600">{{ $meeting['time_label'] }}</p>
                            </div>
                        </div>

                        <div class="flex items-start gap-3 pt-3">
                            <span class="mt-0.5 flex-shrink-0 text-[#6D0D23]">
                                {!! $icon('status.svg', 'w-5 h-5') !!}
                            </span>
                            <div>
                                <p class="font-semibold text-sm text-gray-900">Status</p>
                                <p class="text-sm text-gray-600">{{ $meeting['status_label'] }}</p>
                            </div>
                        </div>

                        @if ($meeting['type'] === 'mentorship')
                        <div class="flex items-start gap-3 pt-3">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-[#6D0D23] flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z" />
                            </svg>
                            <div>
                                <p class="font-semibold text-sm text-gray-900">Mentor</p>
                                <p class="text-sm text-gray-600">{{ $meeting['mentor_name'] }}</p>
                            </div>
                        </div>

                        <div class="flex items-start gap-3 pt-3">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-[#6D0D23] flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 10.5l4.72-4.72a.75.75 0 011.28.53v11.38a.75.75 0 01-1.28.53l-4.72-4.72M4.5 18.75h9a2.25 2.25 0 002.25-2.25v-9a2.25 2.25 0 00-2.25-2.25h-9A2.25 2.25 0 002.25 7.5v9a2.25 2.25 0 002.25 2.25z" />
                            </svg>
                            <div>
                                <p class="font-semibold text-sm text-gray-900">Platform</p>
                                <p class="text-sm text-gray-600">{{ $meeting['platform'] ?? '—' }}</p>
                            </div>
                        </div>
                        @else
                        <div class="flex items-start gap-3 pt-3">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-[#6D0D23] flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z" />
                            </svg>
                            <div>
                                <p class="font-semibold text-sm text-gray-900">Evaluator</p>
                                <p class="text-sm text-gray-600">TBIDO</p>
                            </div>
                        </div>

                        <div class="flex items-start gap-3 pt-3">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-[#6D0D23] flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 21h16.5M4.5 3h15M5.25 3v18m13.5-18v18M9 6.75h1.5m-1.5 3h1.5m-1.5 3h1.5m3-6H15m-1.5 3H15m-1.5 3H15M9 21v-3.375c0-.621.504-1.125 1.125-1.125h3.75c.621 0 1.125.504 1.125 1.125V21" />
                            </svg>
                            <div>
                                <p class="font-semibold text-sm text-gray-900">Location</p>
                                <p class="text-sm text-gray-600">TBIDO Office</p>
                            </div>
                        </div>
                        @endif
                    </div>

                    {{-- Divider --}}
                    <div class="w-px bg-gray-200 my-5"></div>

                    {{-- Action panel --}}
                    <div class="w-44 flex-shrink-0 flex items-end justify-center px-4 py-5">
                        @if ($meeting['type'] === 'mentorship')
                        @if ($canJoin && $meeting['meeting_link'])
                        <a href="{{ $meeting['meeting_link'] }}" target="_blank" rel="noopener"
                            class="w-full text-center bg-gradient-to-r from-[#6D0D23] to-[#11386A] text-white text-sm font-medium rounded-lg py-2.5 transition hover:opacity-95">
                            Join Meeting
                        </a>
                        @else
                        <button type="button" disabled
                            class="w-full text-center bg-gray-300 text-gray-500 text-sm font-medium rounded-lg py-2.5 cursor-not-allowed">
                            Join Meeting
                        </button>
                        @endif
                        @else
                        <div class="text-xs text-gray-600">
                            <p class="font-semibold text-gray-800 mb-1">Note:</p>
                            <p class="italic">Bring 1 valid ID each startup member.</p>
                        </div>
                        @endif
                    </div>
                </div>
                @empty
                <div class="lg:col-span-2 rounded-xl border border-dashed border-gray-200">
                    <x-empty-state variant="meetings" size="lg" title="No Upcoming Meetings Scheduled." highlight="Meetings Scheduled" message="Check back later for your scheduled meetings." />
                </div>
                @endforelse
            </div>
</x-layouts.founder>