import sys
p=sys.argv[1]; s=open(p,encoding='utf-8').read()
def rep(a,b):
    global s
    c=s.count(a); assert c==1,(a[:70],c); s=s.replace(a,b)
rep('''            top: max(calc(var(--hero-arch) + 2rem), calc(100% - var(--wm-w) * 1.4004 - 2rem)); }''',
'''            top: max(calc(var(--hero-arch) + 2rem), calc(100% - var(--wm-w) * 1.4004 - 2rem)); }
        /* On laptops the script at the bottom (placeWatermark) sizes it so its tip touches the curve and
           its base sits on the footer. */''')
rep('''        (function startupCarousels() {''','''        // Lync watermark (laptop widths): sized so the arrow tip touches the white curve and the base
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
            const TOP = 62 / 759, BOT = 17 / 759, TIP_X = 264 / 542, RATIO = 542 / 759;

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
                const left = Math.min(Math.max(8, 0.025 * window.innerWidth), 48);
                let h = H, y = 0;
                for (let n = 0; n < 4; n++) {
                    y = curveY((left + TIP_X * h * RATIO) / W, arch);
                    h = (H - y) / (1 - TOP - BOT);
                }
                img.style.cssText = 'left:' + left + 'px;top:' + (y - TOP * h) + 'px;bottom:auto;'
                    + 'height:' + h + 'px;width:' + (h * RATIO) + 'px;';
            }

            place();
            window.addEventListener('load', place);
            window.addEventListener('resize', place);
            if ('ResizeObserver' in window) new ResizeObserver(place).observe(main);
        })();

        (function startupCarousels() {''')
open(p,'w',encoding='utf-8',newline='\n').write(s); print('ok')
