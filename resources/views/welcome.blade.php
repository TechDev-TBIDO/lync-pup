<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>LYNC</title>
    <link rel="icon" type="image/png" sizes="192x192" href="{{ asset('favicon.png') }}">
    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
    <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    {{-- Landing page styles. Kept as plain scoped CSS (not new Tailwind arbitrary values) because
         the app's compiled CSS bundle won't contain classes that only appear here. --}}
    <style>
        :root {
            --lp-maroon: #6D0D23;
            --lp-navy: #11386A;
            --lp-gold: #E6AC3D;
            --lp-ink: #111111;
            --lp-gutter: clamp(1rem, 6.8vw, 7rem);
            /* Rise of the white arc under the hero (follows the page width). */
            --hero-arch: max(32px, 8vw);
        }
        body.lp { font-family: 'Poppins', ui-sans-serif, system-ui, sans-serif; color: var(--lp-ink); }

        /* ==================== HERO ==================== */
        #hero { position: relative; display: flex; flex-direction: column; overflow: hidden; background: #4d0406; color: #fff; }
        #hero-art { pointer-events: none; user-select: none; position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; object-position: 70% 40%; }
        #hero-content {
            position: relative; z-index: 10; flex: 1 1 auto;
            /* Stacked (< 1024px): the people sit under the text, so the bottom padding makes room for them. */
            --people-h: min(42.6vw, 386px);
            padding-bottom: calc(var(--hero-arch) + var(--people-h) + 1rem);
        }
        @media (min-width: 1024px) {
            /* Desktop: the hero height follows the width, like the mock (~64% of the page width), so the
               people keep the same size and position at every desktop width. */
            #hero { min-height: 50vw; }
            #hero-content { padding-bottom: calc(var(--hero-arch) + 2rem); }
        }
        #hero.hero-stacked #hero-art {
            -webkit-mask-image: linear-gradient(to bottom, transparent 0, #000 120px);
            mask-image: linear-gradient(to bottom, transparent 0, #000 120px);
        }
        .lp-wrap { max-width: 1440px; margin: 0 auto; padding-left: var(--lp-gutter); padding-right: var(--lp-gutter); }

        /* ---- Navbar ---- */
        .lp-nav { position: relative; display: flex; align-items: center; justify-content: space-between; gap: 1rem; padding-top: clamp(1rem, 2.4vw, 2.4rem); }
        .lp-brand { display: flex; align-items: center; gap: 0.6rem; flex-shrink: 0; color: #fff; }
        .lp-brand-mark { display: flex; align-items: center; justify-content: center; width: clamp(32px, 2.4vw, 40px); height: clamp(32px, 2.4vw, 40px); border-radius: 8px; background: #fff; box-shadow: 0 4px 14px rgba(0,0,0,.25); }
        .lp-brand-mark img { width: 84%; height: 84%; object-fit: contain; }
        .lp-brand-name { font-size: clamp(1.1rem, 1.25vw, 1.35rem); font-weight: 700; letter-spacing: 0.04em; }
        /* Partner logos beside LYNC: a thin divider, then the PUP seal and the PUP TBIDO mark. */
        .lp-brand-group { display: flex; align-items: center; gap: clamp(0.6rem, 1.1vw, 1.1rem); flex-shrink: 0; }
        .lp-brand-sep { width: 1px; height: clamp(26px, 2.2vw, 36px); background: rgba(255,255,255,.45); }
        .lp-partners { display: flex; align-items: center; gap: clamp(0.2rem, 0.4vw, 0.4rem); }
        .lp-partners img { height: clamp(36px, 3vw, 50px); width: auto; object-fit: contain; filter: drop-shadow(0 2px 6px rgba(0,0,0,.25)); }
        @media (max-width: 767px) { .lp-brand-sep, .lp-partners { display: none; } }
        .lp-links { display: none; align-items: center; gap: clamp(1.25rem, 3.2vw, 3.25rem); font-size: clamp(0.8125rem, 0.85vw, 0.9375rem); font-weight: 500; }
        .lp-links a { position: relative; padding: 0.4rem 0; color: rgba(255,255,255,.92); transition: color .2s ease; }
        .lp-links a:hover { color: #fff; }
        .lp-links a::after { content: ''; position: absolute; left: 50%; bottom: -0.15rem; width: 0; height: 2px; border-radius: 9999px; background: var(--lp-gold); transform: translateX(-50%); transition: width .25s ease; }
        .lp-links a:hover::after, .lp-links a.is-active::after { width: 100%; }
        .lp-actions { display: flex; align-items: center; gap: clamp(0.4rem, 1vw, 1rem); flex-shrink: 0; }
        .lp-btn { display: inline-flex; align-items: center; justify-content: center; border-radius: 9999px; font-weight: 500; white-space: nowrap; transition: background-color .2s ease, color .2s ease, transform .2s ease, box-shadow .2s ease;
            font-size: clamp(0.75rem, 0.85vw, 0.9375rem); height: clamp(32px, 2.5vw, 42px); padding: 0 clamp(0.9rem, 1.7vw, 1.85rem); }
        .lp-btn--ghost { border: 1.5px solid rgba(255,255,255,.9); color: #fff; }
        .lp-btn--ghost:hover { background: rgba(255,255,255,.12); }
        .lp-btn--solid { background: #fff; color: var(--lp-maroon); font-weight: 600; box-shadow: 0 0 18px rgba(255,255,255,.35); }
        .lp-btn--solid:hover { transform: translateY(-2px); box-shadow: 0 0 24px rgba(255,255,255,.55); }
        .lp-burger { display: flex; align-items: center; justify-content: center; width: 36px; height: 36px; border-radius: 9999px; color: #fff; }
        .lp-burger:hover { background: rgba(255,255,255,.12); }
        .lp-mobile-menu { position: absolute; left: 0; right: 0; top: calc(100% + 0.6rem); z-index: 30; display: flex; flex-direction: column; gap: 0.25rem; padding: 0.5rem; border-radius: 1rem; background: #fff; color: #374151; font-weight: 600; font-size: 0.875rem; box-shadow: 0 12px 30px rgba(0,0,0,.25); }
        .lp-mobile-menu a { padding: 0.65rem 1rem; border-radius: 0.75rem; }
        .lp-mobile-menu a:hover { background: #f9fafb; color: var(--lp-maroon); }
        @media (min-width: 1024px) {
            .lp-links { display: flex; }
            .lp-burger, .lp-mobile-menu { display: none !important; }
        }
        @media (max-width: 479px) {
            .lp-btn--ghost { display: none; } /* "Log in" moves into the menu on very narrow phones */
        }

        /* ---- Hero copy ---- */
        .hero-body { margin-top: clamp(2rem, 6vw, 6.5rem); padding-left: clamp(0rem, 1.7vw, 1.75rem); }
        .hero-title { font-size: clamp(1.75rem, 3.6vw, 3.75rem); font-weight: 800; line-height: 0.98; letter-spacing: -0.01em; }
        .hero-title span { color: var(--lp-gold); }
        .hero-para { margin-top: clamp(1rem, 2.2vw, 2.4rem); max-width: 31em; font-size: clamp(0.85rem, 1.1vw, 1.2rem); font-weight: 500; line-height: 1.2; color: #fff; }
        @media (min-width: 1024px) { .hero-para { max-width: 28em; } }
        .hero-stats { margin-top: clamp(1.25rem, 3vw, 3.25rem); display: flex; align-items: stretch; width: min(100%, clamp(18rem, 28vw, 30rem)); border-radius: clamp(14px, 1.3vw, 22px); background: #fff; text-align: center; color: var(--lp-maroon);
            box-shadow: 0 10px 30px -10px rgba(0,0,0,.45); padding: clamp(0.5rem, 0.7vw, 0.8rem) 0; }
        .hero-stats > div { flex: 1 1 0; padding: 0 0.5rem; }
        .hero-stats > div + div { border-left: 1px solid #d1d5db; }
        .hero-stats .num { font-size: clamp(1.35rem, 1.75vw, 1.9rem); font-weight: 700; line-height: 1.1; }
        .hero-stats .lbl { margin-top: 0.15rem; font-size: clamp(0.7rem, 0.75vw, 0.85rem); font-weight: 500; color: var(--lp-ink); white-space: nowrap; }

        /* Phones & tablets: hero heading, paragraph and stats card centred. */
        @media (max-width: 1023px) {
            .hero-body { padding-left: 0; text-align: center; }
            .hero-para { margin-left: auto; margin-right: auto; }
            .hero-stats { margin-left: auto; margin-right: auto; }
        }
        /* Tablets (768-1023px): bigger hero text. */
        @media (min-width: 768px) and (max-width: 1023px) {
            .hero-title { font-size: clamp(3rem, 6.2vw, 3.6rem); }
            .hero-para { max-width: 34em; font-size: clamp(1.1rem, 2vw, 1.3rem); }
            .hero-stats { width: min(100%, 34rem); }
            .hero-stats .num { font-size: 2.1rem; }
            .hero-stats .lbl { font-size: 0.95rem; }
        }

        /* ==================== MEET THE INNOVATORS ==================== */
        .hero-arch {
            margin-top: calc(-1 * var(--hero-arch));
            padding-top: clamp(2rem, 3vw, 3rem);
            clip-path: polygon(0% calc(var(--hero-arch) * 1.0000), 2.5% calc(var(--hero-arch) * 0.9002), 5% calc(var(--hero-arch) * 0.8060), 7.5% calc(var(--hero-arch) * 0.7173), 10% calc(var(--hero-arch) * 0.6341), 12.5% calc(var(--hero-arch) * 0.5562), 15% calc(var(--hero-arch) * 0.4836), 17.5% calc(var(--hero-arch) * 0.4163), 20% calc(var(--hero-arch) * 0.3541), 22.5% calc(var(--hero-arch) * 0.2972), 25% calc(var(--hero-arch) * 0.2453), 27.5% calc(var(--hero-arch) * 0.1984), 30% calc(var(--hero-arch) * 0.1566), 32.5% calc(var(--hero-arch) * 0.1198), 35% calc(var(--hero-arch) * 0.0879), 37.5% calc(var(--hero-arch) * 0.0610), 40% calc(var(--hero-arch) * 0.0390), 42.5% calc(var(--hero-arch) * 0.0220), 45% calc(var(--hero-arch) * 0.0098), 47.5% calc(var(--hero-arch) * 0.0024), 50% 0, 52.5% calc(var(--hero-arch) * 0.0024), 55% calc(var(--hero-arch) * 0.0098), 57.5% calc(var(--hero-arch) * 0.0220), 60% calc(var(--hero-arch) * 0.0390), 62.5% calc(var(--hero-arch) * 0.0610), 65% calc(var(--hero-arch) * 0.0879), 67.5% calc(var(--hero-arch) * 0.1198), 70% calc(var(--hero-arch) * 0.1566), 72.5% calc(var(--hero-arch) * 0.1984), 75% calc(var(--hero-arch) * 0.2453), 77.5% calc(var(--hero-arch) * 0.2972), 80% calc(var(--hero-arch) * 0.3541), 82.5% calc(var(--hero-arch) * 0.4163), 85% calc(var(--hero-arch) * 0.4836), 87.5% calc(var(--hero-arch) * 0.5562), 90% calc(var(--hero-arch) * 0.6341), 92.5% calc(var(--hero-arch) * 0.7173), 95% calc(var(--hero-arch) * 0.8060), 97.5% calc(var(--hero-arch) * 0.9002), 100% calc(var(--hero-arch) * 1.0000), 100% 100%, 0 100%);
        }
        .lp-section { max-width: 80rem; margin: 0 auto; padding-left: 1rem; padding-right: 1rem; }
        @media (min-width: 640px) { .lp-section { padding-left: 1.5rem; padding-right: 1.5rem; } }
        @media (min-width: 1024px) { .lp-section { padding-left: 2rem; padding-right: 2rem; } }
        .lp-heading { font-size: clamp(1.5rem, 1.85vw, 2rem); font-weight: 700; color: var(--lp-ink); }
        .lp-heading span { background: linear-gradient(90deg, #4a1d4f, #1c3a78); -webkit-background-clip: text; background-clip: text; color: transparent; }
        .lp-sub { margin-top: 0.25rem; font-size: clamp(0.8rem, 1vw, 1.05rem); color: var(--lp-ink); }

        /* Cohort tabs: full-width row, equal-width tabs, grey rule underneath, one sliding maroon bar. */
        .cohort-tabs { position: relative; margin-top: clamp(1.5rem, 3vw, 3rem); display: flex; flex-wrap: wrap; justify-content: center; gap: clamp(1rem, 3vw, 3rem); border-bottom: 1px solid #e5e7eb; }
        .cohort-tab { flex: 0 0 auto; padding: 0 0.75rem 0.6rem; font-size: clamp(1rem, 1.4vw, 1.5rem); font-weight: 700; line-height: 1.4; color: var(--lp-ink); transition: color .2s ease; }
        .cohort-tab:hover { color: #4b5563; }
        .cohort-tab.is-active { color: var(--lp-maroon); }
        .cohort-indicator { position: absolute; height: 2px; border-radius: 9999px; background: var(--lp-maroon); pointer-events: none; }
        .cohort-panels { margin-top: clamp(1.25rem, 1.8vw, 2rem); display: grid; }
        .cohort-panels > .cohort-panel { grid-area: 1 / 1; min-width: 0; }
        .lp-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 0.65rem; }
        @media (min-width: 640px) { .lp-grid { gap: 1.25rem; } }
        @media (min-width: 768px) { .lp-grid { grid-template-columns: repeat(3, minmax(0, 1fr)); } }
        @media (min-width: 1024px) { .lp-grid { grid-template-columns: repeat(4, minmax(0, 1fr)); } }

        /* Startup carousel */
        .lp-carousel { position: relative; margin-left: calc(50% - 50vw); margin-right: calc(50% - 50vw); overflow-x: auto; overflow-y: hidden;
            scrollbar-width: none; -webkit-overflow-scrolling: touch; padding: 1.5rem 0 2.25rem; }
        .lp-carousel::-webkit-scrollbar { display: none; }
        .lp-track { display: flex; width: max-content; align-items: center; gap: clamp(1rem, 2.4vw, 2.5rem);
            padding-left: calc(50vw - var(--slide-w) / 2); padding-right: calc(50vw - var(--slide-w) / 2); }
        .lp-carousel { --slide-w: clamp(13.5rem, 62vw, 19rem); }
        @media (min-width: 768px) { .lp-carousel { --slide-w: clamp(16rem, 24vw, 21rem); } }
        .lp-slide { flex: 0 0 auto; width: var(--slide-w); transform-origin: center; will-change: transform, opacity; }
        .lp-slide .startup-card { height: 100%; }
        .lp-carousel-hint { margin-top: 0.25rem; text-align: center; font-size: 0.72rem; letter-spacing: 0.14em; text-transform: uppercase; color: #9a8d93; }
        .lp-carousel-hint span[aria-hidden] { margin: 0 0.6rem; }
        .lp-carousel-hint .hint-touch { display: none; }
        .lp-viewall { display: none; }
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

        /* Banner hover layers: a shade that deepens, and a running number. */
        .banner-shade { position: absolute; inset: 0; z-index: 2; background: rgba(42, 8, 20, 0.08); transition: background-color .7s cubic-bezier(.2,.7,.2,1); pointer-events: none; }
        .banner-num { position: absolute; left: 0.8rem; top: 0.6rem; z-index: 3; font-size: 0.7rem; font-weight: 600; letter-spacing: 0.12em; color: rgba(255,255,255,.9); text-shadow: 0 1px 4px rgba(0,0,0,.35); }
        .startup-banner > img { transition: transform .9s cubic-bezier(.2,.7,.2,1); }

        /* Startup card */
        .startup-card { display: flex; flex-direction: column; min-width: 0; overflow: hidden; border: 1px solid #c9c9cf; border-radius: 12px; background: #f0f0f0;
            transition: transform .25s ease, box-shadow .25s ease, border-color .25s ease; }
        .startup-banner { position: relative; display: flex; align-items: center; justify-content: center; overflow: hidden; aspect-ratio: 7 / 4; }
        .startup-banner > img, .startup-banner > .initial { transition: transform .5s ease; }
        .startup-banner .initial { font-size: clamp(1.5rem, 3vw, 2.75rem); font-weight: 800; }
        .stage-badge { position: absolute; right: 0.5rem; top: 0.5rem; z-index: 10; border-radius: 9999px; border: 1px solid var(--lp-maroon); background: #fff; color: #3b0a16; font-size: clamp(9px, 0.75vw, 12px); line-height: 1; padding: 0.3em 0.9em; }
        @media (min-width: 640px) { .stage-badge { right: 0.75rem; top: 0.75rem; } }
        .stage-badge--completed { background: #fef3c7; color: #92400e; border-color: #d97706; }
        .stage-badge--graduated { background: #dcfce7; color: #166534; border-color: #16a34a; }
        .card-body { display: flex; flex: 1 1 auto; flex-direction: column; padding: clamp(0.65rem, 1.15vw, 1.25rem); }
        .card-name { font-size: clamp(0.8rem, 1.05vw, 1.1rem); font-weight: 700; color: var(--lp-ink); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .card-meta { margin-top: 0.1rem; font-size: clamp(0.65rem, 0.85vw, 0.9rem); color: #555; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .card-desc { margin-top: 0.7rem; flex: 1 1 auto; min-height: 2.6em; font-size: clamp(0.62rem, 0.72vw, 0.78rem); line-height: 1.75; color: #444; overflow-wrap: anywhere;
            display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
        .card-foot { margin-top: 0.6rem; display: flex; align-items: center; justify-content: space-between; gap: 0.5rem; font-size: clamp(0.6rem, 0.68vw, 0.75rem); color: #444; }
        .card-foot .loc { display: flex; align-items: center; gap: 0.3rem; min-width: 0; }
        .card-foot .loc span { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .card-foot .rls { display: flex; align-items: center; gap: 0.3rem; flex-shrink: 0; font-weight: 600; color: var(--lp-ink); }
        /* Gradient-outlined button (maroon -> navy), used by View and View All Startups. */
        .lp-outline { position: relative; display: inline-flex; align-items: center; justify-content: center; gap: 0.5rem; border-radius: 8px; color: #1f1640; font-weight: 500;
            background: linear-gradient(#fff, #fff) padding-box, linear-gradient(90deg, var(--lp-maroon), var(--lp-navy)) border-box; border: 1.5px solid transparent;
            transition: color .2s ease, background .2s ease; }
        .lp-outline:hover { color: #fff; background: linear-gradient(90deg, var(--lp-maroon), var(--lp-navy)) padding-box, linear-gradient(90deg, var(--lp-maroon), var(--lp-navy)) border-box; }
        .card-view { margin-top: clamp(0.65rem, 1.1vw, 1.1rem); width: 100%; padding: clamp(0.3rem, 0.5vw, 0.5rem) 0; font-size: clamp(0.7rem, 0.85vw, 0.9rem); }
        .view-all { padding: 0.75rem 2.25rem; border-radius: 10px; font-size: clamp(0.9rem, 1.05vw, 1.1rem); font-weight: 600; color: #1c2a5c; }
        .view-all svg { transition: transform .2s ease; }
        .view-all:hover svg { transform: translateX(4px); }
        @media (hover: hover) {
            .startup-card:hover { box-shadow: 0 30px 60px -30px rgba(109, 13, 35, 0.55); border-color: #e4c4cc; }
            .startup-card:hover .startup-banner > img { transform: scale(1.08); }
            .startup-card:hover .banner-shade { background: rgba(42, 8, 20, 0.6); }
            .startup-card:hover .startup-banner > .initial { transform: scale(1.15); }
        }

        /* Lync logo watermark at the left edge, never allowed to rise into the arc. (Image is 542x759.) */
        /* Whole logo shown (not cropped): smaller, tucked into the bottom-left, never rising into the arc. */
        .lync-watermark { --wm-w: clamp(240px, 27vw, 440px); width: var(--wm-w); left: calc(var(--wm-w) * -0.1808); bottom: auto; /* visible logo flush with the left edge (PNG has a 98px transparent margin) */
            top: max(calc(var(--hero-arch) + 2rem), calc(100% - var(--wm-w) * 1.4004 - 2rem)); }
        /* On laptops the script at the bottom (placeWatermark) sizes it so its tip touches the curve and
           its base sits on the footer. */

        /* ==================== FOOTER ==================== */
        .lp-footer { color: #fff; padding: clamp(1.5rem, 2.2vw, 2.25rem) 0;
            background:
                radial-gradient(ellipse 22% 55% at 100% 100%, rgba(10, 45, 140, .95), transparent 70%),
                radial-gradient(ellipse 35% 80% at 75% 40%, rgba(75, 25, 90, .75), transparent 70%),
                linear-gradient(100deg, #4a0610 0%, #250510 28%, #1b0a24 48%, #33103d 70%, #3a0d36 85%, #1c1d58 100%); }
        .lp-footer-grid { display: grid; grid-template-columns: 1fr; gap: 1.25rem; }
        @media (min-width: 640px) { .lp-footer-grid { grid-template-columns: 1fr 1fr; } .lp-footer-grid > :first-child { grid-column: span 2; } }
        @media (min-width: 900px) { .lp-footer-grid { grid-template-columns: 1.6fr 0.8fr 1fr; gap: 2rem; } .lp-footer-grid > :first-child { grid-column: auto; } }
        .lp-footer h3 { font-size: clamp(1rem, 1.1vw, 1.2rem); line-height: 1.3; font-weight: 700; }
        .lp-footer .lead { margin-top: 0.5rem; max-width: 30rem; text-wrap: pretty; font-size: clamp(0.78rem, 0.8vw, 0.875rem); line-height: 1.55; color: rgba(255,255,255,.9); }
        .lp-footer .copy { margin-top: 0.75rem; font-size: clamp(0.7rem, 0.7vw, 0.78rem); color: rgba(255,255,255,.85); }
        .lp-footer ul { margin-top: 0.5rem; display: flex; flex-direction: column; gap: 0.35rem; font-size: clamp(0.78rem, 0.8vw, 0.875rem); color: rgba(255,255,255,.9); overflow-wrap: anywhere; }
        .lp-footer ul a { transition: color .2s ease; }
        .lp-footer ul a:hover { color: var(--lp-gold); }

        /* ==================== MOTION ==================== */
        @media (prefers-reduced-motion: no-preference) {
            @keyframes hero-rise { from { opacity: 0; transform: translateY(22px); } }
            @keyframes hero-drop { from { opacity: 0; transform: translateY(-16px); } }
            @keyframes hero-fade { from { opacity: 0; } }
            #hero-art { animation: hero-fade 1.2s ease-out backwards; }
            #hero .lp-nav { animation: hero-drop 0.6s ease-out backwards; }
            #hero .hero-body > * { animation: hero-rise 0.7s cubic-bezier(0.2, 0.7, 0.2, 1) backwards; }
            #hero .hero-body > :nth-child(1) { animation-delay: 0.15s; }
            #hero .hero-body > :nth-child(2) { animation-delay: 0.30s; }
            #hero .hero-body > :nth-child(3) { animation-delay: 0.45s; }
            #hero.hero-idle #hero-art, #hero.hero-idle .lp-nav, #hero.hero-idle .hero-body > * { animation: none; }

            @keyframes reveal-up { from { opacity: 0; transform: translateY(28px); } }
            .reveal-on .reveal { opacity: 0; }
            .reveal-on .reveal.is-visible { opacity: 1; animation: reveal-up 0.4s cubic-bezier(0.2, 0.7, 0.2, 1) backwards; animation-delay: var(--reveal-delay, 0ms); }

            @keyframes panel-in { from { opacity: 0; transform: translateY(10px); } }
            .cohort-panel.is-active { animation: panel-in 0.35s ease-out; }
            .cohort-indicator.is-ready { transition: left 0.3s ease, width 0.3s ease, top 0.3s ease, opacity 0.2s ease; }
        }
        @media (prefers-reduced-motion: reduce) {
            .startup-card, .startup-banner > img, .startup-banner > .initial, .lp-btn { transition: none; }
            .startup-card:hover, .lp-btn--solid:hover { transform: none; }
        }
    
        /* The new hero (x-hero.section) ends in its own white curve, so <main> no longer
           tucks up under it with the arch. */
        main.hero-arch { margin-top: 0; clip-path: none; }
        /* Pull the section up into the white of the hero curve so the gap under it is smaller.
           Transparent so the curve (and the photo at the edges) still shows through; the body is white. */
        @media (min-width: 901px) {
            main.hero-arch { margin-top: calc(-120 * 100vw / 1728); background-color: transparent; }
        }
    </style>
</head>

<body x-data="landingPage()" class="lp overflow-x-hidden antialiased bg-white">

    {{-- ==================== HERO ==================== --}}
    {{-- New hero: resources/views/components/hero/ (styles public/css/lp-hero.css, script public/js/lp-hero.js). --}}
    <x-hero.section :stats="$stats" />

    {{-- ==================== MEET THE INNOVATORS ==================== --}}
    {{-- `isolate` gives <main> its own stacking context so the -z-10 watermark sits behind the
         content but in front of <main>'s white background. --}}
    <main class="hero-arch relative isolate overflow-hidden bg-white pb-16">
        <div id="cohorts" class="lp-section scroll-mt-8">
            <div class="reveal text-center">
                <h2 class="lp-heading">Meet the <span>Innovators</span></h2>
                <p class="lp-sub">Innovative Startups. Real Solutions. Growing Impact.</p>
            </div>

            @if ($cohortShowcase->isEmpty())
                <x-empty-state variant="startups" size="lg" title="No Startups to Show Yet." highlight="Startups" message="Our incubated startups will be featured here soon." class="mt-6" />
            @else
                <div class="cohort-tabs reveal"
                    x-effect="placeTabIndicator($el, activeCohortIndex)"
                    x-init="window.addEventListener('resize', () => placeTabIndicator($el, activeCohortIndex)); document.fonts && document.fonts.ready.then(() => placeTabIndicator($el, activeCohortIndex))">
                    <span class="cohort-indicator" :class="{ 'is-ready': tabIndicator.ready }" aria-hidden="true"
                        :style="`left:${tabIndicator.left}px;width:${tabIndicator.width}px;top:${tabIndicator.top}px;opacity:${tabIndicator.width ? 1 : 0}`"></span>
                    @foreach ($cohortShowcase as $index => $group)
                        <button type="button" @click="activeCohortIndex = {{ $index }}"
                            class="cohort-tab {{ $index === 0 ? 'is-active' : '' }}"
                            :class="{ 'is-active': activeCohortIndex === {{ $index }} }">
                            {{ $group['cohort']->display_label }}
                        </button>
                    @endforeach
                </div>

                {{-- All panels share one grid cell; inactive ones are only made invisible, so the
                     section is always as tall as the tallest cohort and the footer doesn't jump. --}}
                <div class="cohort-panels">
                @foreach ($cohortShowcase as $index => $group)
                    @php
                        $paletteBg = ['#7E57C2', '#F0261A', '#2563EB', '#FFFFFF'];
                        $paletteTone = ['#FFFFFF', '#FFFFFF', '#FFFFFF', '#2563EB'];
                    @endphp
                    <div class="cohort-panel {{ $index === 0 ? 'is-active' : 'invisible pointer-events-none' }}"
                        :class="{ 'is-active': activeCohortIndex === {{ $index }}, 'invisible': activeCohortIndex !== {{ $index }}, 'pointer-events-none': activeCohortIndex !== {{ $index }} }"
                        :aria-hidden="activeCohortIndex !== {{ $index }}">
                        {{-- Carousel (like the bymacy.vercel.app Projects section): on desktop, moving the mouse
                             left/right across it pans the row; on touch it swipes. The card nearest the
                             centre is full size, the others shrink, drop and fade with distance. --}}
                        <div class="lp-carousel reveal" data-carousel>
                            <div class="lp-track">
                            @foreach ($group['startups'] as $sIndex => $startup)
                                <div class="lp-slide {{ $sIndex >= 4 ? 'lp-extra' : '' }}" data-card
                                    :class="{ 'is-shown': isExpanded({{ $group['cohort']->cohort_id }}) }">
                                <div class="startup-card">
                                    <div class="startup-banner" style="background: {{ $paletteBg[$startup['palette_index']] }};">
                                        <span class="banner-shade" aria-hidden="true"></span>
                                        <span class="banner-num" aria-hidden="true">{{ str_pad($sIndex + 1, 2, '0', STR_PAD_LEFT) }}</span>
                                        <span class="stage-badge {{ $startup['stage_key'] === 'development' ? '' : 'stage-badge--'.$startup['stage_key'] }}">
                                            {{ $startup['stage_label'] }}
                                        </span>
                                        @if ($startup['photo_url'])
                                            <img src="{{ $startup['photo_url'] }}" alt="" class="absolute inset-0 h-full w-full object-cover">
                                        @else
                                            <span class="initial" style="color: {{ $paletteTone[$startup['palette_index']] }};">
                                                {{ strtoupper(substr($startup['name'], 0, 1)) }}
                                            </span>
                                        @endif
                                    </div>

                                    <div class="card-body">
                                        <p class="card-name">{{ $startup['name'] }}</p>
                                        <p class="card-meta">{{ $startup['sector'] ?? 'Uncategorized' }} &bull; {{ $startup['cohort_label'] }}</p>

                                        <p class="card-desc">{{ $startup['description'] ?? 'No description submitted yet.' }}</p>

                                        <div class="card-foot">
                                            <span class="loc">
                                                @if ($startup['location'])
                                                    <svg class="h-3.5 w-3.5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 21s-7-6.1-7-11.5a7 7 0 1 1 14 0C19 14.9 12 21 12 21Z" />
                                                        <circle cx="12" cy="9.5" r="2.5" />
                                                    </svg>
                                                    <span>{{ $startup['location'] }}</span>
                                                @endif
                                            </span>
                                            @if ($startup['overall_score'] !== null)
                                                <span class="rls">
                                                    <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 17l6-6 4 4 8-8M15 7h6v6" />
                                                    </svg>
                                                    RLS {{ number_format($startup['overall_score'], 1) }}
                                                </span>
                                            @endif
                                        </div>

                                        <button type="button" @click="openStartup(@js($startup))" class="lp-outline card-view">
                                            View
                                        </button>
                                    </div>
                                </div>
                                </div>
                            @endforeach
                            </div>
                        </div>
                        @if ($group['startups']->count() > 4)
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
                        <p class="lp-carousel-hint reveal">
                            {{ $group['startups']->count() }} {{ \Illuminate\Support\Str::plural('startup', $group['startups']->count()) }}
                            <span aria-hidden="true">/</span>
                            <span class="hint-desk">Move left or right to browse</span><span class="hint-touch">Swipe to browse</span>
                        </p>

                    </div>
                @endforeach
                </div>
            @endif
        </div>

        {{-- Faint Lync logo watermark bleeding off the left edge (decorative). --}}
        <img src="{{ asset('images/login-signup/lync-logo.png') }}" alt=""
            class="lync-watermark pointer-events-none absolute -z-10 max-w-none opacity-10">
    </main>

    {{-- ==================== FOOTER ==================== --}}
    <footer id="contact" class="lp-footer">
        <div class="lp-section">
            <div class="lp-footer-grid">
                <div>
                    <h3>Contact us</h3>
                    <p class="lead">
                        PYLON Hub fosters innovation and entrepreneurship by supporting technology-based startups and
                        empowering students and faculty.
                    </p>
                    <p class="copy">&copy; {{ date('Y') }} Technology Business Incubation and Development Office</p>
                </div>

                <div>
                    <h3>Quick Links</h3>
                    <ul>
                        <li><a href="https://www.facebook.com/DOSTPUPPYLONTBI" target="_blank" rel="noopener">Facebook</a></li>
                        <li><a href="https://www.puptbi.site/">Official Website</a></li>
                        <li><a href="#">TBIDO Address</a></li>
                        <li><a href="{{ route('register', ['from' => 'landing']) }}">Apply Now</a></li>
                    </ul>
                </div>

                <div>
                    <h3>Contacts</h3>
                    <ul>
                        <li>tbido@pup.edu.ph</li>
                        <li>fb.com/DOSTPUPPYLONTBI</li>
                        <li>PUP Sta. Mesa, Manila</li>
                    </ul>
                </div>
            </div>
        </div>
    </footer>

    {{-- ==================== STARTUP DETAIL MODAL ==================== --}}
    <div x-show="modalOpen" x-cloak
        x-effect="setScrollLock(modalOpen)"
        style="overscroll-behavior: contain;"
        class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-black/50 p-4 sm:items-center"
        >
        {{-- max-w-3xl -> max-w-4xl: was cramping the radar chart + score
             cards against the Contact & Links sidebar, clipping labels.
             max-h-[90vh] + flex flex-col caps the WHOLE card to the screen,
             so the outer backdrop never needs to scroll — only the content
             panel below (flex-1 overflow-y-auto) does, giving a single
             scrollbar instead of one on the backdrop and one inside. --}}
        <div class="my-auto flex max-h-[90vh] w-full max-w-4xl flex-col overflow-hidden rounded-2xl bg-white shadow-2xl">
            <template x-if="activeStartup">
                <div class="flex min-h-0 flex-1 flex-col">
                    {{-- Plain title bar — just identifies the panel and closes
                         it. The actual startup identity (icon, name, badge,
                         tagline, meta) moved into its own gradient banner
                         below, matching the "Meet Our Incubatees" card style. --}}
                    <div class="flex shrink-0 items-center gap-3 border-b border-gray-100 bg-white px-4 py-3 sm:px-6 sm:py-4">
                        <h3 class="text-sm font-bold text-rose-900" x-text="activeStartup.cohort_label || 'Startup'"></h3>
                        {{-- Standardized to the SAME close button used by
                             components/confirm-action-modal.blade.php (the admin-side
                             delete confirmation) — every "X" in the app should look like
                             this one: outlined circle that fills solid with the brand
                             gradient on hover, rather than a one-off rose-tinted style. --}}
                        <button type="button" @click="closeStartup()" aria-label="Close"
                            class="ml-auto flex h-6 w-6 items-center justify-center rounded-full border border-gray-900 text-gray-900 transition hover:border-transparent hover:bg-gradient-to-r hover:from-[#6D0D23] hover:to-[#11386A] hover:text-white">
                            <svg class="h-3 w-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M18 6L6 18M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    {{-- Identity banner --}}
                    <div class="shrink-0 bg-gradient-to-r from-[#6D0D23] to-[#11386A] px-4 py-4 text-white sm:px-6 sm:py-5">
                        <div class="flex flex-wrap items-start gap-4">
                            <span class="flex h-14 w-14 shrink-0 items-center justify-center overflow-hidden rounded-xl bg-white/15 text-lg font-extrabold">
                                <template x-if="activeStartup.photo_url">
                                    <img :src="activeStartup.photo_url" alt="" class="h-full w-full object-cover">
                                </template>
                                <template x-if="!activeStartup.photo_url">
                                    <span x-text="activeStartup.name.charAt(0).toUpperCase()"></span>
                                </template>
                            </span>
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <h2 class="text-lg font-extrabold" x-text="activeStartup.name"></h2>
                                    <span class="rounded-full px-2.5 py-0.5 text-[11px] font-semibold" :class="activeStartup.stage_key === 'development' ? 'bg-white text-gray-700' : 'stage-badge--' + activeStartup.stage_key" x-text="activeStartup.stage_label"></span>
                                </div>
                                {{-- Sector/cohort line moved here, right under the name — next to
                                     the profile photo instead of spanning the full banner width
                                     below it. Description stays out of the banner entirely; it's
                                     shown in full in the "About" section below instead. --}}
                                <p class="mt-1 text-xs font-medium text-white/70">
                                    <span x-text="activeStartup.sector || 'Uncategorized'"></span> &middot;
                                    <span x-text="activeStartup.cohort_label"></span>
                                </p>
                            </div>
                        </div>
                    </div>

                    {{-- flex-1 + min-h-0 (instead of a fixed max-h-[80vh]) lets this panel
                         fill whatever space is left under the title bar + banner within the
                         card's own max-h-[90vh], and be the only thing that scrolls. --}}
                    <div class="min-h-0 flex-1 overflow-y-auto overflow-x-hidden p-4 sm:p-6">
                        <div class="grid grid-cols-1 gap-6 lg:grid-cols-[1fr_260px]">
                            {{-- min-w-0: a grid item's default min-width is its content's
                                 min-content size, which the fixed-size radar chart below
                                 pushed past this column's fair share — forcing the whole
                                 track wider than the modal and triggering a horizontal
                                 scrollbar. min-w-0 lets it shrink to fit the track instead. --}}
                            <div class="min-w-0">
                                {{-- About — moved inside the left column instead of spanning
                                     the full width above the two-column grid. Boxed in the same
                                     rounded-xl bordered card as the Readiness Level one below. --}}
                                <div class="rounded-xl border border-gray-200 p-4">
                                    <h3 class="text-sm font-bold text-gray-900">About</h3>
                                    {{-- break-words so a long unbroken description wraps to new lines
                                         instead of overflowing past the panel's edge. --}}
                                    <p class="mt-2 break-words text-sm leading-relaxed text-gray-600" x-text="activeStartup.description || 'No description submitted yet.'"></p>
                                </div>

                                {{-- Readiness Level --}}
                                <div class="mt-6 flex items-center justify-between">
                                    <p class="flex items-center gap-1.5 text-sm font-bold text-[#11386A]">
                                        <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                                            <path fill-rule="evenodd" d="M12 5a.75.75 0 0 1 .75-.75h4.5a.75.75 0 0 1 .75.75v4.5a.75.75 0 0 1-1.5 0V6.81l-5.22 5.22a.75.75 0 0 1-1.06 0L7.5 9.06l-4.72 4.72a.75.75 0 0 1-1.06-1.06l5.25-5.25a.75.75 0 0 1 1.06 0l2.97 2.97L16.19 5.75h-3.44A.75.75 0 0 1 12 5Z" clip-rule="evenodd" />
                                        </svg>
                                        Readiness Level
                                    </p>

                                    {{-- Stage dropdown — same look as the admin Startup Profile's
                                         Readiness Level dropdown (grey pill button, rounded panel,
                                         brand-gradient hover). The panel only lists the OTHER
                                         stage(s): the current one is already shown on the button,
                                         so repeating it in the list was redundant. With just one
                                         stage there's nothing to switch to, so the chevron is
                                         hidden and the button doesn't open anything. --}}
                                    <template x-if="Object.keys(activeStartup.stages).length">
                                        <div class="relative" x-data="{ open: false }" @click.outside="open = false"
                                            x-effect="if (Object.keys(activeStartup.stages).length < 2) open = false">
                                            <button type="button" @click="if (Object.keys(activeStartup.stages).length > 1) open = !open"
                                                class="flex items-center gap-2 text-sm font-medium text-gray-800"
                                                :class="Object.keys(activeStartup.stages).length > 1 ? '' : 'cursor-default'"
                                                style="background-color: #F3F4F6; border-radius: 10px; padding: 6px 14px;">
                                                <span x-text="stageKey"></span>
                                                <svg x-show="Object.keys(activeStartup.stages).length > 1" class="h-4 w-4 text-gray-500" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.293l3.71-4.06a.75.75 0 111.08 1.04l-4.25 4.65a.75.75 0 01-1.08 0l-4.25-4.65a.75.75 0 01.02-1.06z" clip-rule="evenodd" /></svg>
                                            </button>
                                            <div x-show="open" x-cloak class="absolute right-0 z-20 mt-2 overflow-hidden rounded-lg border border-gray-100 bg-white shadow-xl" style="width: 170px;">
                                                <template x-for="key in Object.keys(activeStartup.stages).filter(k => k !== stageKey)" :key="key">
                                                    <button type="button" @click="stageKey = key; open = false"
                                                        class="block w-full px-4 py-2 text-left text-sm text-gray-700 transition-colors hover:bg-gradient-to-r hover:from-[#6D0D23] hover:to-[#11386A] hover:text-white" x-text="key"></button>
                                                </template>
                                            </div>
                                        </div>
                                    </template>
                                </div>

                                <template x-if="currentStage">
                                    {{-- Wrapper so the template still has a single root: the bordered
                                         card holds just the radar + score tiles, and the composite
                                         score sits below it, outside the card (same as the admin
                                         Startup Profile's Readiness Level). --}}
                                    <div class="mt-3">
                                        <div class="rounded-xl border border-gray-200 p-4">
                                            <div class="grid grid-cols-1 gap-4 sm:grid-cols-[auto_1fr]">
                                                {{-- Radar. viewBox is padded out to -25/-20/250/240 (instead of a
                                                     tight 0 0 200 200) so the TRL/MRL/TMRL/SRL labels have room on
                                                     all four sides — the old tight box clipped the MRL/SRL text
                                                     sideways and clipped the TMRL value below the bottom edge
                                                     (which is why it used a cramped dy="-2" that overlapped the
                                                     label instead). The diamond/polygon math is untouched, still
                                                     centered on 100,100 with radius up to 80. --}}
                                                <svg viewBox="-25 -20 250 240" class="mx-auto h-52 w-52">
                                                    <polygon points="100,20 180,100 100,180 20,100" fill="none" stroke="#E5E7EB" stroke-width="1" />
                                                    <polygon points="100,47 153,100 100,153 47,100" fill="none" stroke="#E5E7EB" stroke-width="1" />
                                                    <polygon points="100,73 127,100 100,127 73,100" fill="none" stroke="#E5E7EB" stroke-width="1" />
                                                    <line x1="100" y1="100" x2="100" y2="20" stroke="#E5E7EB" />
                                                    <line x1="100" y1="100" x2="180" y2="100" stroke="#E5E7EB" />
                                                    <line x1="100" y1="100" x2="100" y2="180" stroke="#E5E7EB" />
                                                    <line x1="100" y1="100" x2="20" y2="100" stroke="#E5E7EB" />

                                                    <polygon :points="radarPolygon" fill="#6D0D2333" stroke="#6D0D23" stroke-width="2" />

                                                    <text x="100" y="12" text-anchor="middle" class="fill-gray-500" style="font-size:9px">TRL <tspan x="100" dy="10" x-text="(scoreFor('TRL') ?? 0) + '/9'"></tspan></text>
                                                    <text x="188" y="103" text-anchor="start" class="fill-gray-500" style="font-size:9px">MRL <tspan x="188" dy="10" x-text="(scoreFor('MRL') ?? 0) + '/9'"></tspan></text>
                                                    <text x="100" y="196" text-anchor="middle" class="fill-gray-500" style="font-size:9px">TMRL <tspan x="100" dy="10" x-text="(scoreFor('TMRL') ?? 0) + '/9'"></tspan></text>
                                                    <text x="12" y="103" text-anchor="end" class="fill-gray-500" style="font-size:9px">SRL <tspan x="12" dy="10" x-text="(scoreFor('SRL') ?? 0) + '/9'"></tspan></text>
                                                </svg>

                                                {{-- Score cards --}}
                                                <div class="min-w-0 grid grid-cols-2 gap-2.5">
                                                    <template x-for="[type, cardLabel] in [['TRL','TECHNOLOGY'],['MRL','MANUFACTURING'],['TMRL','TEAM & MGMT'],['SRL','SYSTEM / MARKET']]" :key="type">
                                                        <div class="rounded-lg border border-gray-200 p-2.5">
                                                            <p class="text-[10px] font-semibold uppercase tracking-wide text-gray-500" x-text="cardLabel"></p>
                                                            <p class="text-sm font-extrabold text-gray-900">
                                                                <span x-text="type"></span>
                                                                <span x-text="(scoreFor(type) ?? 0) + '/9'"></span>
                                                            </p>
                                                            <div class="mt-1 h-1.5 w-full overflow-hidden rounded-full bg-gray-100">
                                                                <div class="h-full rounded-full bg-[#6D0D23]" :style="`width:${((scoreFor(type) ?? 0)/9)*100}%`"></div>
                                                            </div>
                                                        </div>
                                                    </template>
                                                </div>
                                            </div>
                                        </div>

                                        <p class="mt-2 text-xs text-gray-500">
                                            Composite RLS score:
                                            <span class="font-bold text-gray-800" x-text="(currentStage.overall_score ?? '—') + (currentStage.overall_score !== null ? '/9' : '')"></span>
                                        </p>
                                    </div>
                                </template>
                                <template x-if="!currentStage">
                                    <p class="mt-3 rounded-xl border border-gray-200 p-4 text-sm text-gray-400">Not assessed yet.</p>
                                </template>

                                {{-- Team — same markup as the admin Startup Profile "Team"
                                     card (admin/startups/show.blade.php): card + 3person icon
                                     heading, founder row in rose-50 with a "Founder" badge, then
                                     the Core Team as gray-100 rows. The roster itself is built by
                                     WelcomeController::presentTeam() from the same data. --}}
                                @php
                                    // Same treatment as the admin view's $icon() helper: recolor the
                                    // SVG to currentColor so it inherits the heading's text color.
                                    $teamIconPath = public_path('images/icons/3person.svg');
                                    $teamIcon = '';
                                    if (file_exists($teamIconPath)) {
                                        $teamIcon = file_get_contents($teamIconPath);
                                        $teamIcon = preg_replace('/<svg([^>]*)>/', '<svg$1 class="block h-4 w-4">', $teamIcon, 1);
                                        $teamIcon = preg_replace('/fill="(?!none)[^"]*"/i', 'fill="currentColor"', $teamIcon);
                                        $teamIcon = preg_replace('/stroke="(?!none)[^"]*"/i', 'stroke="currentColor"', $teamIcon);
                                    }
                                @endphp
                                <div class="mt-6 rounded-2xl border border-gray-200 bg-white p-6 shadow-sm">
                                    <h2 class="mb-4 flex items-center gap-2 font-bold text-gray-900">
                                        <span class="flex-shrink-0 text-gray-700">{!! $teamIcon !!}</span>
                                        Team
                                    </h2>
                                    <div class="grid grid-cols-2 gap-3">
                                        <template x-for="(member, i) in activeStartup.team" :key="i">
                                            <div :class="member.is_founder
                                                    ? 'flex items-center justify-between gap-2 rounded-lg bg-rose-50 px-4 py-2 text-sm'
                                                    : 'rounded-lg bg-gray-100 px-4 py-2 text-sm'">
                                                <span :class="member.is_founder ? 'font-medium text-gray-900' : ''" x-text="member.name"></span>
                                                <span x-show="member.is_founder" class="shrink-0 rounded-full bg-rose-900 px-2 py-0.5 text-[10px] font-semibold text-white">Founder</span>
                                            </div>
                                        </template>
                                        <x-empty-state variant="people" size="sm" title="No Team Members Yet." highlight="Team Members" x-show="!activeStartup.team.length" class="col-span-2" />
                                    </div>
                                </div>
                            </div>

                            {{-- Contact & Links --}}
                            <div class="h-fit rounded-xl border border-gray-200 p-4">
                                <p class="text-sm font-bold text-gray-900">Contact &amp; Links</p>
                                <div class="mt-3 space-y-2.5 text-sm text-gray-600">
                                    <p x-show="activeStartup.website" class="flex items-center gap-2 truncate">
                                        <svg class="h-4 w-4 shrink-0 text-gray-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="9" /><path stroke-linecap="round" d="M3 12h18M12 3a14 14 0 010 18 14 14 0 010-18Z" /></svg>
                                        <span class="truncate" x-text="activeStartup.website"></span>
                                    </p>
                                    <p x-show="activeStartup.email" class="flex items-center gap-2 truncate">
                                        <svg class="h-4 w-4 shrink-0 text-gray-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="5" width="18" height="14" rx="2" /><path stroke-linecap="round" d="m3 7 9 6 9-6" /></svg>
                                        <span class="truncate" x-text="activeStartup.email"></span>
                                    </p>
                                    <p x-show="activeStartup.phone" class="flex items-center gap-2 truncate">
                                        <svg class="h-4 w-4 shrink-0 text-gray-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M3 5c0 9 7 16 16 16l3-4-6-3-2 2c-3-1.5-5-3.5-6.5-6.5l2-2-3-6-4 .5Z" /></svg>
                                        <span x-text="activeStartup.phone"></span>
                                    </p>
                                </div>

                                {{-- py-1.5 instead of py-2.5 — shorter button per request. --}}
                                <a :href="`mailto:${activeStartup.email || ''}?subject=${encodeURIComponent('Pitch Deck Request — ' + activeStartup.name)}`"
                                    class="mt-4 block w-full rounded-lg bg-gradient-to-r from-[#6D0D23] to-[#11386A] py-1.5 text-center text-sm font-bold text-white transition hover:opacity-95">
                                    Request Pitch Deck
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </template>
        </div>
    </div>

    <script>
        function landingPage() {
            return {
                activeCohortIndex: 0,
                expandedCohorts: [],
                activeStartup: null,
                stageKey: null,
                modalOpen: false,
                tabIndicator: { left: 0, width: 0, top: 0, ready: false },

                // Slides the single underline under the active cohort tab. Measures the tab's box
                // (so wrapped tab rows work too); the transition is switched on only after the first
                // placement so the underline doesn't glide in from the corner on page load.
                placeTabIndicator(container, index) {
                    const tab = container.querySelectorAll('.cohort-tab')[index];
                    if (!tab) return;
                    this.tabIndicator.left = tab.offsetLeft;
                    this.tabIndicator.width = tab.offsetWidth;
                    this.tabIndicator.top = tab.offsetTop + tab.offsetHeight - 1;
                    if (!this.tabIndicator.ready) {
                        requestAnimationFrame(() => requestAnimationFrame(() => { this.tabIndicator.ready = true; }));
                    }
                },

                scrollToCohorts() {
                    document.getElementById('cohorts')?.scrollIntoView({ behavior: 'smooth' });
                },

                scrollToContact() {
                    document.getElementById('contact')?.scrollIntoView({ behavior: 'smooth' });
                },

                isExpanded(cohortId) {
                    return this.expandedCohorts.includes(cohortId);
                },

                expandCohort(cohortId) {
                    this.expandedCohorts.push(cohortId);
                },

                // While the detail modal is open only the modal scrolls - the page behind it is
                // frozen. The scrollbar's width is added back as padding so the page doesn't
                // jump sideways when the scrollbar disappears (and back again on close).
                setScrollLock(locked) {
                    const root = document.documentElement;
                    if (locked) {
                        const gap = window.innerWidth - root.clientWidth;
                        root.style.overflow = 'hidden';
                        document.body.style.overflow = 'hidden';
                        if (gap > 0) root.style.paddingRight = gap + 'px';
                    } else {
                        root.style.overflow = '';
                        document.body.style.overflow = '';
                        root.style.paddingRight = '';
                    }
                },

                openStartup(startup) {
                    this.activeStartup = startup;
                    this.stageKey = startup.default_stage;
                    this.modalOpen = true;
                },

                closeStartup() {
                    this.modalOpen = false;
                },

                get currentStage() {
                    return this.activeStartup?.stages?.[this.stageKey] ?? null;
                },

                scoreFor(type) {
                    const score = this.currentStage?.scores?.[type];
                    return score === null || score === undefined ? null : score;
                },

                // Angles: 0deg = top (TRL), 90 = right (MRL), 180 = bottom
                // (TMRL), 270 = left (SRL) — offset by -90 so 0deg plots
                // straight up instead of the trig default of straight right.
                radarPoint(type, angleDeg) {
                    const score = this.scoreFor(type) ?? 0;
                    const r = (score / 9) * 80;
                    const rad = (angleDeg - 90) * Math.PI / 180;
                    const x = 100 + r * Math.cos(rad);
                    const y = 100 + r * Math.sin(rad);
                    return `${x.toFixed(1)},${y.toFixed(1)}`;
                },

                get radarPolygon() {
                    return [
                        this.radarPoint('TRL', 0),
                        this.radarPoint('MRL', 90),
                        this.radarPoint('TMRL', 180),
                        this.radarPoint('SRL', 270),
                    ].join(' ');
                },
            };
        }
    </script>
    <script>
        // Hero artwork placement: keeps the people's waistline exactly on the white arc's cut at
        // every screen size. Image constants are pixels in public/images/landing/landing-hero.jpg —
        // update them if that artwork is ever swapped.
        (function fitHeroArt() {
            const header = document.getElementById('hero');
            const art = document.getElementById('hero-art');
            const section = document.querySelector('.hero-arch');
            if (!header || !art || !section) return;

            const IMG_W = 1728, IMG_H = 903;
            const BELT_Y = 730;     // image row that should sit on the arc's peak
            const FOCUS_X = 0.7;
            const GROUP_W = 1080;   // width of the four people (plus a little margin), in image px
            const STACK_MAX = 1023; // keep in step with the 1024px breakpoint in the <style>
            // Top-edge colours of the artwork (left -> right), painted above it in the stacked layout.
            const TOP_STOPS = [[0.02, '#530607'], [0.2, '#56050a'], [0.4, '#570408'], [0.7, '#3f0405'], [0.98, '#400807']];
            const stackedQuery = window.matchMedia('(max-width: ' + STACK_MAX + 'px)');

            function fit() {
                const stacked = stackedQuery.matches;
                header.classList.toggle('hero-stacked', stacked);

                const W = header.clientWidth;
                const H = header.clientHeight;
                const cutY = section.getBoundingClientRect().top - header.getBoundingClientRect().top;

                let scale, w, h, left, top;
                if (stacked) {
                    // People fill the width under the text, waistline on the cut, pushed to the right edge.
                    scale = Math.min(W / GROUP_W, 0.84);
                    w = IMG_W * scale;
                    h = IMG_H * scale;
                    left = W - w;
                    top = cutY - BELT_Y * scale;
                    header.style.background = 'linear-gradient(90deg, ' + TOP_STOPS.map(function (s) {
                        return s[1] + ' ' + (((left + s[0] * w) / W) * 100).toFixed(1) + '%';
                    }).join(', ') + ')';
                } else {
                    scale = Math.max(W / IMG_W, H / IMG_H, cutY / BELT_Y);
                    w = IMG_W * scale;
                    h = IMG_H * scale;
                    top = Math.min(0, Math.max(H - h, cutY - BELT_Y * scale));
                    left = (W - w) * FOCUS_X;
                    header.style.background = '';
                }

                art.style.cssText = 'position:absolute;right:auto;bottom:auto;max-width:none;object-fit:fill;'
                    + 'width:' + w + 'px;height:' + h + 'px;'
                    + 'left:' + left + 'px;top:' + top + 'px;';
            }

            fit();
            window.addEventListener('load', fit);
            if ('ResizeObserver' in window) {
                new ResizeObserver(fit).observe(header);
            } else {
                window.addEventListener('resize', fit);
            }
        })();
    </script>
    <script>
        // Scroll reveal: every time a .reveal element scrolls into view it gets .is-visible (the
        // CSS above then plays the rise-in), and it loses it again once it has fully left the
        // screen, so the effect plays again on the way back — up or down. The hero does the same
        // (.hero-idle switches its entrance animations off while it's out of view, so they
        // restart when it returns). Skipped entirely for reduced motion, and .reveal only hides
        // things once this has run, so nothing stays invisible without JS.
        // Startup carousels: the card nearest the middle of the screen is full size; the further a card
        // is from it, the smaller, lower and fainter it gets. On a mouse/trackpad, the pointer's
        // left/right position over the row picks where it pans to (eased); touch devices swipe natively.
        // Lync watermark (laptop widths): sized so the arrow tip touches the white curve and the base
        // sits exactly on the footer. The PNG (542x759) has transparent margins: 62px above the tip,
        // 17px under the base, and the tip is at 48.7% of its width.
        (function placeWatermark() {
            const img = document.querySelector('.lync-watermark');
            const main = document.querySelector('main.hero-arch');
            if (!img || !main) return;
            const desk = window.matchMedia('(min-width: 1024px)');
            // Drop of the curve (as a fraction of --hero-arch) every 2.5% across the page - same
            // numbers as the clip-path polygon on .hero-arch.
            const K = [1, .9002, .806, .7173, .6341, .5562, .4836, .4163, .3541, .2972, .2453, .1984, .1566, .1198, .0879, .061, .039, .022, .0098, .0024, 0];
            const TOP = 62 / 759, BOT = 17 / 759, TIP_X = 264 / 542, RATIO = 542 / 759, LEFT_PAD = 98 / 542;

            function curveY(f, arch) {
                f = Math.min(Math.max(f, 0), 1);
                if (f > 0.5) f = 1 - f;
                const i = Math.min(Math.floor(f / 0.025), K.length - 2), t = f / 0.025 - i;
                return arch * (K[i] + (K[i + 1] - K[i]) * t);
            }

            function place() {
                if (!desk.matches) { img.style.cssText = ''; return; }
                const W = main.clientWidth, H = main.clientHeight;
                const arch = Math.max(32, 0.08 * window.innerWidth);
                let h = H, y = 0;
                for (let n = 0; n < 4; n++) {
                    y = curveY(((TIP_X - LEFT_PAD) * h * RATIO) / W, arch);
                    h = (H - y) / (1 - TOP - BOT);
                }
                img.style.cssText = 'left:' + (-LEFT_PAD * h * RATIO) + 'px;top:' + (y - TOP * h) + 'px;bottom:auto;'
                    + 'height:' + h + 'px;width:' + (h * RATIO) + 'px;';
            }

            place();
            window.addEventListener('load', place);
            window.addEventListener('resize', place);
            if ('ResizeObserver' in window) new ResizeObserver(place).observe(main);
        })();

        (function startupCarousels() {
            const reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
            const desk = window.matchMedia('(min-width: 1024px)');

            document.querySelectorAll('[data-carousel]').forEach(function (el) {
                const slides = Array.prototype.slice.call(el.querySelectorAll('[data-card]'));
                let target = el.scrollLeft, raf = 0;

                function paint() {
                    if (!desk.matches) {
                        slides.forEach(function (s) { s.style.transform = ''; s.style.opacity = ''; s.style.zIndex = ''; });
                        return;
                    }
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

                {
                    el.addEventListener('mousemove', function (e) {
                        if (!desk.matches) return;
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
                if (desk.addEventListener) desk.addEventListener('change', function () { el.scrollLeft = 0; target = 0; paint(); });
                // Start on the first card, centred.
                el.scrollLeft = 0; target = 0;
                paint();
            });
        })();

        (function scrollReveal() {
            if (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
            if (!('IntersectionObserver' in window)) return;

            const items = document.querySelectorAll('.reveal');
            if (items.length) {
                document.documentElement.classList.add('reveal-on');

                // One shared queue, so things play strictly one after another instead of all at
                // once: heading, then the tabs, then the cards one by one in reading order. Each
                // newly visible item is scheduled right after the previous one finishes its slot.
                let queueFreeAt = 0;
                function stepFor(el) {
                    if (el.dataset.revealStep) return parseInt(el.dataset.revealStep, 10);
                    return el.classList.contains('startup-card') ? 60 : 100;
                }
                function schedule(el) {
                    const now = performance.now();
                    queueFreeAt = Math.max(queueFreeAt, now);
                    const delay = Math.min(queueFreeAt - now, 300);
                    queueFreeAt = now + delay + stepFor(el);
                    el.style.setProperty('--reveal-delay', Math.round(delay) + 'ms');
                    el.classList.add('is-visible');
                }

                const io = new IntersectionObserver(function (entries) {
                    const entering = [];
                    entries.forEach(function (entry) {
                        const shown = entry.intersectionRatio >= 0.12
                            || entry.intersectionRect.height > window.innerHeight * 0.3; // very tall blocks
                        if (shown) {
                            if (!entry.target.classList.contains('is-visible')) entering.push(entry.target);
                        } else if (!entry.isIntersecting) {
                            entry.target.classList.remove('is-visible'); // gone -> ready to play again
                        }
                    });
                    // Page order (top-to-bottom, left-to-right), not the order the browser reports them.
                    entering.sort(function (a, b) {
                        return (a.compareDocumentPosition(b) & Node.DOCUMENT_POSITION_FOLLOWING) ? -1 : 1;
                    });
                    entering.forEach(schedule);
                }, { threshold: [0, 0.12, 0.3], rootMargin: '0px 0px -6% 0px' });
                items.forEach(function (el) { io.observe(el); });
            }

            const hero = document.getElementById('hero');
            if (hero) {
                new IntersectionObserver(function (entries) {
                    hero.classList.toggle('hero-idle', !entries[0].isIntersecting);
                }).observe(hero);
            }
        })();
    </script>
</body>

</html>
