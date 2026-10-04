{{--
    Page loader ("buffer") — four brand-colored dots that orbit and pinch
    together while the next page loads, so a slow click never looks frozen.

    Shown automatically on any normal navigation: a same-site link click or a
    form submit. It waits 150ms before appearing so fast pages don't flash it.
    It stays visible until the next page has actually loaded, however slow
    the server is. Skipped for: links opening a new tab, download links, in-page "#" links,
    clicks already handled by Alpine (e.g. the unsaved-changes guard calls
    preventDefault), and anything marked data-no-loader.

    Plain inline CSS/JS on purpose: the deploy pipeline doesn't run
    `npm run build`, so this works without rebuilding public/build.
--}}
<div id="lync-page-loader" class="lync-loader" role="status" aria-live="polite" aria-hidden="true">
    <div class="lync-loader__grid">
        <span class="lync-loader__dot lync-loader__dot--tl"></span>
        <span class="lync-loader__dot lync-loader__dot--tr"></span>
        <span class="lync-loader__dot lync-loader__dot--bl"></span>
        <span class="lync-loader__dot lync-loader__dot--br"></span>
    </div>
    <span class="lync-loader__sr">Loading…</span>
</div>

<style>
    .lync-loader {
        position: fixed;
        inset: 0;
        z-index: 2147483000;
        display: flex;
        align-items: center;
        justify-content: center;
        background: rgba(255, 255, 255, 0.82);
        backdrop-filter: blur(2px);
        opacity: 0;
        visibility: hidden;
        transition: opacity 0.2s ease, visibility 0s linear 0.2s;
    }

    .lync-loader.is-visible {
        opacity: 1;
        visibility: visible;
        transition: opacity 0.2s ease, visibility 0s;
    }

    .lync-loader__grid {
        position: relative;
        width: 64px;
        height: 64px;
        animation: lync-loader-turn 4.8s infinite;
    }

    .lync-loader__dot {
        position: absolute;
        top: 50%;
        left: 50%;
        width: 18px;
        height: 18px;
        margin: -9px 0 0 -9px;
        border-radius: 9999px;
        animation-duration: 1.2s;
        animation-iteration-count: infinite;
        animation-timing-function: cubic-bezier(0.65, 0, 0.35, 1);
    }

    .lync-loader__dot--tl { background: #E8AE3C; animation-name: lync-loader-tl; }
    .lync-loader__dot--tr { background: #6D0D23; animation-name: lync-loader-tr; }
    .lync-loader__dot--bl { background: #46304E; animation-name: lync-loader-bl; }
    .lync-loader__dot--br { background: #143A6B; animation-name: lync-loader-br; }

    /* The grid turns a quarter per beat (4 beats = one full turn, so the
       loop never snaps back), while each dot pinches toward the center and
       springs back out on the same beat. */
    @keyframes lync-loader-turn {
        0%   { transform: rotate(0deg);   animation-timing-function: cubic-bezier(0.65, 0, 0.35, 1); }
        25%  { transform: rotate(90deg);  animation-timing-function: cubic-bezier(0.65, 0, 0.35, 1); }
        50%  { transform: rotate(180deg); animation-timing-function: cubic-bezier(0.65, 0, 0.35, 1); }
        75%  { transform: rotate(270deg); animation-timing-function: cubic-bezier(0.65, 0, 0.35, 1); }
        100% { transform: rotate(360deg); }
    }

    @keyframes lync-loader-tl { 0%, 100% { transform: translate(-16px, -16px); } 50% { transform: translate(-5px, -5px); } }
    @keyframes lync-loader-tr { 0%, 100% { transform: translate(16px, -16px); }  50% { transform: translate(5px, -5px); } }
    @keyframes lync-loader-bl { 0%, 100% { transform: translate(-16px, 16px); }  50% { transform: translate(-5px, 5px); } }
    @keyframes lync-loader-br { 0%, 100% { transform: translate(16px, 16px); }   50% { transform: translate(5px, 5px); } }

    @media (prefers-reduced-motion: reduce) {
        .lync-loader__grid { animation-duration: 9.6s; }
        .lync-loader__dot { animation-duration: 2.4s; }
    }

    .lync-loader__sr {
        position: absolute;
        width: 1px;
        height: 1px;
        overflow: hidden;
        clip: rect(0 0 0 0);
        white-space: nowrap;
    }
</style>

<script>
    (function () {
        if (window.__lyncPageLoader) return;

        const loader = document.getElementById('lync-page-loader');
        if (!loader) return;

        let showTimer = null;
        let safetyTimer = null;

        function show() {
            clearTimeout(showTimer);
            showTimer = setTimeout(function () {
                loader.classList.add('is-visible');
                loader.setAttribute('aria-hidden', 'false');
                // Stays up until the next page actually replaces this one —
                // however long the server takes. Only a very long last-resort
                // timeout remains (the server itself gives up long before
                // this), so the screen can never be covered forever.
                clearTimeout(safetyTimer);
                safetyTimer = setTimeout(hide, 180000);
            }, 150);
        }

        function hide() {
            clearTimeout(showTimer);
            clearTimeout(safetyTimer);
            loader.classList.remove('is-visible');
            loader.setAttribute('aria-hidden', 'true');
        }

        window.__lyncPageLoader = { show: show, hide: hide };

        // Bubble phase on purpose: element-level handlers (Alpine @click,
        // the unsaved-changes guard) run first, and if they called
        // preventDefault the navigation isn't happening, so no loader.
        document.addEventListener('click', function (event) {
            if (event.defaultPrevented || event.button !== 0) return;
            if (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;

            const link = event.target.closest && event.target.closest('a[href]');
            if (!link) return;
            if (link.hasAttribute('download') || link.hasAttribute('data-no-loader')) return;
            if (link.target && link.target !== '_self') return;

            const rawHref = link.getAttribute('href') || '';
            if (rawHref === '' || rawHref.charAt(0) === '#' || /^(javascript|mailto|tel):/i.test(rawHref)) return;

            let url;
            try { url = new URL(link.href, window.location.href); } catch (e) { return; }
            if (url.origin !== window.location.origin) return;

            // Same page, only the #hash differs: the browser just scrolls.
            if (url.pathname === window.location.pathname && url.search === window.location.search && url.hash) return;

            show();
        });

        document.addEventListener('submit', function (event) {
            if (event.defaultPrevented) return;

            const form = event.target;
            if (form.hasAttribute('data-no-loader')) return;
            if (form.target && form.target !== '_self') return;

            show();
        });

        // If the browser is about to ask "Leave site? Changes you made may
        // not be saved", the user might choose to stay — so don't cover the
        // page. (Same flag the admin/founder layouts' own guard checks.)
        window.addEventListener('beforeunload', function () {
            try {
                if (window.Alpine && window.Alpine.store('navigation') && window.Alpine.store('navigation').hasUnsavedChanges) {
                    hide();
                }
            } catch (e) {}
        });

        // Coming back via Back/Forward (bfcache): make sure it's not left showing.
        window.addEventListener('pageshow', hide);
        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') hide();
        });
    })();
</script>
