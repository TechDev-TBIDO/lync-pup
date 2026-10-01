import sys
p=sys.argv[1]; s=open(p,encoding='utf-8').read()
def rep(a,b,n=1):
    global s
    c=s.count(a); assert c==n,(a[:60],c); s=s.replace(a,b)

# ---------- markup ----------
rep('''                        <div class="lp-grid">
                            @foreach ($group['startups'] as $sIndex => $startup)
                                <div x-show="{{ $sIndex }} < 4 || isExpanded({{ $group['cohort']->cohort_id }})" class="startup-card reveal">
                                    <div class="startup-banner" style="background: {{ $paletteBg[$startup['palette_index']] }};">
                                        <span class="stage-badge''',
'''                        {{-- Carousel (like the bymacy.vercel.app Projects section): on desktop, moving the mouse
                             left/right across it pans the row; on touch it swipes. The card nearest the
                             centre is full size, the others shrink, drop and fade with distance. --}}
                        <div class="lp-carousel reveal" data-carousel>
                            <div class="lp-track">
                            @foreach ($group['startups'] as $sIndex => $startup)
                                <div class="lp-slide" data-card>
                                <div class="startup-card">
                                    <div class="startup-banner" style="background: {{ $paletteBg[$startup['palette_index']] }};">
                                        <span class="banner-shade" aria-hidden="true"></span>
                                        <span class="banner-num" aria-hidden="true">{{ str_pad($sIndex + 1, 2, '0', STR_PAD_LEFT) }}</span>
                                        <svg class="banner-arrow" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M7 7h10v10M7 17 17 7"/></svg>
                                        <span class="stage-badge''')
rep('''                                        </button>
                                    </div>
                                </div>
                            @endforeach
                        </div>
''','''                                        </button>
                                    </div>
                                </div>
                                </div>
                            @endforeach
                            </div>
                        </div>
                        <p class="lp-carousel-hint reveal">
                            {{ $group['startups']->count() }} {{ \\Illuminate\\Support\\Str::plural('startup', $group['startups']->count()) }}
                            <span aria-hidden="true">/</span>
                            <span class="hint-desk">Move left or right to browse</span><span class="hint-touch">Swipe to browse</span>
                        </p>
''')
# drop the View All button (every startup is in the carousel now)
import re
m=re.search(r"\n                        @if \(\$group\['startups'\]->count\(\) > 4\)\n.*?                        @endif\n",s,flags=re.S)
assert m; s=s.replace(m.group(0),"\n",1)

# ---------- css ----------
rep('''        /* Startup card */''','''        /* Startup carousel */
        .lp-carousel { position: relative; margin-left: calc(50% - 50vw); margin-right: calc(50% - 50vw); overflow-x: auto; overflow-y: hidden;
            scrollbar-width: none; -webkit-overflow-scrolling: touch; padding: 1.5rem 0 2.25rem; }
        .lp-carousel::-webkit-scrollbar { display: none; }
        .lp-track { display: flex; width: max-content; align-items: center; gap: clamp(1rem, 2.4vw, 2.5rem);
            padding-left: calc(50vw - var(--slide-w) / 2); padding-right: calc(50vw - var(--slide-w) / 2); }
        .lp-carousel { --slide-w: clamp(13.5rem, 62vw, 19rem); }
        @media (min-width: 768px) { .lp-carousel { --slide-w: clamp(16rem, 24vw, 21rem); } }
        .lp-slide { flex: 0 0 auto; width: var(--slide-w); transform-origin: center; will-change: transform, opacity; }
        .lp-slide .startup-card { height: 100%; }
        @media (hover: none) { .lp-carousel { scroll-snap-type: x mandatory; } .lp-slide { scroll-snap-align: center; } }
        .lp-carousel-hint { margin-top: 0.25rem; text-align: center; font-size: 0.72rem; letter-spacing: 0.14em; text-transform: uppercase; color: #9a8d93; }
        .lp-carousel-hint span[aria-hidden] { margin: 0 0.6rem; }
        .lp-carousel-hint .hint-touch { display: none; }
        @media (hover: none) { .lp-carousel-hint .hint-desk { display: none; } .lp-carousel-hint .hint-touch { display: inline; } }

        /* Banner hover layers: a shade that deepens, a running number, and an arrow that slides in. */
        .banner-shade { position: absolute; inset: 0; z-index: 2; background: rgba(42, 8, 20, 0.08); transition: background-color .7s cubic-bezier(.2,.7,.2,1); pointer-events: none; }
        .banner-num { position: absolute; left: 0.8rem; top: 0.6rem; z-index: 3; font-size: 0.7rem; font-weight: 600; letter-spacing: 0.12em; color: rgba(255,255,255,.9); text-shadow: 0 1px 4px rgba(0,0,0,.35); }
        .banner-arrow { position: absolute; left: 50%; top: 50%; z-index: 3; width: 2rem; height: 2rem; color: #fff; opacity: 0; transform: translate(-50%, -30%);
            transition: opacity .5s cubic-bezier(.2,.7,.2,1), transform .5s cubic-bezier(.2,.7,.2,1); pointer-events: none; }
        .startup-banner > img { transition: transform .9s cubic-bezier(.2,.7,.2,1); }

        /* Startup card */''')
rep('''            .startup-card:hover { transform: translateY(-6px); box-shadow: 0 16px 30px -12px rgba(109, 13, 35, 0.35); border-color: #e4c4cc; }
            .startup-card:hover .startup-banner > img { transform: scale(1.06); }''','''            .startup-card:hover { box-shadow: 0 30px 60px -30px rgba(109, 13, 35, 0.55); border-color: #e4c4cc; }
            .startup-card:hover .startup-banner > img { transform: scale(1.08); }
            .startup-card:hover .banner-shade { background: rgba(42, 8, 20, 0.6); }
            .startup-card:hover .banner-arrow { opacity: 1; transform: translate(-50%, -50%); }''')

# ---------- js ----------
rep('''        (function scrollReveal() {''','''        // Startup carousels: the card nearest the middle of the screen is full size; the further a card
        // is from it, the smaller, lower and fainter it gets. On a mouse/trackpad, the pointer's
        // left/right position over the row picks where it pans to (eased); touch devices swipe natively.
        (function startupCarousels() {
            const reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
            const finePointer = window.matchMedia && window.matchMedia('(hover: hover) and (pointer: fine)').matches;

            document.querySelectorAll('[data-carousel]').forEach(function (el) {
                const slides = Array.prototype.slice.call(el.querySelectorAll('[data-card]'));
                let target = el.scrollLeft, raf = 0;

                function paint() {
                    const mid = el.getBoundingClientRect().left + el.clientWidth / 2;
                    slides.forEach(function (s) {
                        const b = s.getBoundingClientRect();
                        const d = Math.min(Math.abs(b.left + b.width / 2 - mid) / (b.width * 1.15), 1); // 0 centre -> 1 neighbour+
                        s.style.transform = 'translateY(' + (d * 26).toFixed(1) + 'px) scale(' + (1 - d * 0.3).toFixed(3) + ')';
                        s.style.opacity = (1 - d * 0.5).toFixed(3);
                        s.style.zIndex = String(100 - Math.round(d * 50));
                    });
                }

                function tick() {
                    const diff = target - el.scrollLeft;
                    if (Math.abs(diff) < 0.5) { el.scrollLeft = target; raf = 0; paint(); return; }
                    el.scrollLeft += reduce ? diff : diff * 0.09;
                    paint();
                    raf = requestAnimationFrame(tick);
                }

                if (finePointer) {
                    el.addEventListener('mousemove', function (e) {
                        const r = el.getBoundingClientRect();
                        const max = el.scrollWidth - el.clientWidth;
                        if (max <= 0) return;
                        // Middle 80% of the width maps to the full range, so the ends are easy to reach.
                        const f = Math.min(Math.max(((e.clientX - r.left) / r.width - 0.1) / 0.8, 0), 1);
                        target = f * max;
                        if (!raf) raf = requestAnimationFrame(tick);
                    });
                    el.addEventListener('wheel', function () { target = el.scrollLeft; }, { passive: true });
                }
                el.addEventListener('scroll', function () { if (!raf) { target = el.scrollLeft; paint(); } }, { passive: true });
                window.addEventListener('resize', paint);
                // Start on the first card, centred.
                el.scrollLeft = 0; target = 0;
                paint();
            });
        })();

        (function scrollReveal() {''')
open(p,'w',encoding='utf-8',newline='\n').write(s); print('ok')
