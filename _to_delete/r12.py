import sys
p=sys.argv[1]; s=open(p,encoding='utf-8').read()
def rep(a,b):
    global s
    c=s.count(a); assert c==1,(a[:70],c); s=s.replace(a,b)
# markup: mark extra cards + add mobile View All
rep('''                                <div class="lp-slide" data-card>''',
'''                                <div class="lp-slide {{ $sIndex >= 4 ? 'lp-extra' : '' }}" data-card
                                    :class="{ 'is-shown': isExpanded({{ $group['cohort']->cohort_id }}) }">''')
rep('''                        <p class="lp-carousel-hint reveal">''',
'''                        @if ($group['startups']->count() > 4)
                            {{-- Phones/tablets only (the carousel shows every card on laptops). --}}
                            <div class="lp-viewall mt-8 justify-center" x-show="!isExpanded({{ $group['cohort']->cohort_id }})">
                                <button type="button" @click="expandCohort({{ $group['cohort']->cohort_id }})" class="lp-outline view-all">
                                    View All Startups
                                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14m-6-6 6 6-6 6" />
                                    </svg>
                                </button>
                            </div>
                        @endif
                        <p class="lp-carousel-hint reveal">''')
# css
rep('''        @media (hover: none) { .lp-carousel { scroll-snap-type: x mandatory; } .lp-slide { scroll-snap-align: center; } }
''','')
rep('''        @media (hover: none) { .lp-carousel-hint .hint-desk { display: none; } .lp-carousel-hint .hint-touch { display: inline; } }
''','''        .lp-viewall { display: none; }
        /* Phones & tablets (< 1024px): no carousel - the plain card grid (2 columns, 3 from 768px),
           first 4 cards plus a "View All Startups" button. */
        @media (max-width: 1023px) {
            .lp-carousel { margin-left: 0; margin-right: 0; overflow: visible; padding: 0; }
            .lp-track { display: grid; width: auto; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 0.65rem; padding: 0; }
            .lp-slide { width: auto; transform: none !important; opacity: 1 !important; }
            .lp-slide.lp-extra:not(.is-shown) { display: none; }
            .lp-carousel-hint { display: none; }
            .lp-viewall { display: flex; }
            .lp-viewall[style*="display: none"] { display: none; }
        }
        @media (min-width: 640px) and (max-width: 1023px) { .lp-track { gap: 1.25rem; } }
        @media (min-width: 768px) and (max-width: 1023px) { .lp-track { grid-template-columns: repeat(3, minmax(0, 1fr)); } }
''')
# js: only run on laptop widths
rep('''            const finePointer = window.matchMedia && window.matchMedia('(hover: hover) and (pointer: fine)').matches;
''','''            const desk = window.matchMedia('(min-width: 1024px)');
''')
rep('''                function paint() {
                    const mid''','''                function paint() {
                    if (!desk.matches) {
                        slides.forEach(function (s) { s.style.transform = ''; s.style.opacity = ''; s.style.zIndex = ''; });
                        return;
                    }
                    const mid''')
rep('''                if (finePointer) {
                    el.addEventListener('mousemove', function (e) {
                        const r''','''                {
                    el.addEventListener('mousemove', function (e) {
                        if (!desk.matches) return;
                        const r''')
rep('''                window.addEventListener('resize', paint);''','''                window.addEventListener('resize', paint);
                if (desk.addEventListener) desk.addEventListener('change', function () { el.scrollLeft = 0; target = 0; paint(); });''')
open(p,'w',encoding='utf-8',newline='\n').write(s); print('ok')
