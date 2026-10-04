/*
 * Lync PUP landing hero - circle icons that flow along the orbit line and react to the cursor.
 *
 * Each <g class="lph-orbit-icon" data-line="..." data-at="..."> travels forward along its line.
 * When the cursor comes near:
 *   - the icon is pulled gently toward the cursor (magnet), grows and glows
 *   - the whole row of icons slows down, so they are easy to look at
 *
 * Settings on the <svg class="lph-orbit">:
 *   data-flow-seconds  seconds for an icon to travel the whole line (default 45)
 *   data-reach         how close the cursor must be, in design px (default 210)
 *   data-pull          how strongly an icon follows the cursor, 0..1 (default .35)
 */
(function () {
    'use strict';

    var reduceMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    function lerp(from, to, amount) {
        return from + (to - from) * amount;
    }

    function initOrbit(svg) {
        var hero = svg.closest('.lph-hero') || document.body;
        var lap = parseFloat(svg.dataset.flowSeconds || '45');
        var reach = parseFloat(svg.dataset.reach || '210');
        var pull = parseFloat(svg.dataset.pull || '.35');

        var icons = Array.prototype.map.call(svg.querySelectorAll('.lph-orbit-icon[data-line]'), function (g) {
            var path = svg.querySelector('#' + g.dataset.line);
            if (!path) return null;

            // JavaScript takes over from the simple CSS/SVG fallback animation
            Array.prototype.forEach.call(g.querySelectorAll('animateMotion'), function (a) { a.remove(); });

            return {
                g: g,
                path: path,
                length: path.getTotalLength(),
                t: parseFloat(g.dataset.at || '0'),
                offsetX: 0,
                offsetY: 0,
                scale: 1,
                hover: 0,
            };
        }).filter(Boolean);

        if (!icons.length) return;

        var mouse = null;

        hero.addEventListener('pointermove', function (event) {
            var matrix = svg.getScreenCTM();
            if (!matrix) return;
            var point = svg.createSVGPoint();
            point.x = event.clientX;
            point.y = event.clientY;
            var local = point.matrixTransform(matrix.inverse());
            mouse = { x: local.x, y: local.y };
        });
        hero.addEventListener('pointerleave', function () { mouse = null; });

        var last = performance.now();
        var slowdown = 0; // 0 = full speed, 1 = nearly stopped

        function frame(now) {
            var dt = Math.min(0.05, (now - last) / 1000);
            last = now;
            var ease = Math.min(1, dt * 6);
            var closest = 0;

            icons.forEach(function (icon) {
                // move forward along the line (slower while the cursor is near)
                if (!reduceMotion) {
                    icon.t = (icon.t + (dt / lap) * (1 - 0.85 * slowdown)) % 1;
                }

                var point = icon.path.getPointAtLength(icon.t * icon.length);

                // cursor: magnet pull, grow, glow
                var targetX = 0, targetY = 0, targetScale = 1, targetHover = 0;
                if (mouse) {
                    var dx = mouse.x - point.x;
                    var dy = mouse.y - point.y;
                    var distance = Math.sqrt(dx * dx + dy * dy);
                    if (distance < reach) {
                        var strength = 1 - distance / reach;
                        strength = strength * strength * (3 - 2 * strength); // smooth
                        targetX = dx * pull * strength;
                        targetY = dy * pull * strength;
                        targetScale = 1 + 0.22 * strength;
                        targetHover = strength;
                    }
                }

                icon.offsetX = lerp(icon.offsetX, targetX, ease);
                icon.offsetY = lerp(icon.offsetY, targetY, ease);
                icon.scale = lerp(icon.scale, targetScale, ease);
                icon.hover = lerp(icon.hover, targetHover, ease);
                closest = Math.max(closest, icon.hover);

                // fade in where the line starts and out where it leaves the picture
                var fade = Math.max(0, Math.min(1, icon.t / 0.04, (1 - icon.t) / 0.04));

                icon.g.setAttribute('transform',
                    'translate(' + (point.x + icon.offsetX).toFixed(2) + ' ' + (point.y + icon.offsetY).toFixed(2) + ') scale(' + icon.scale.toFixed(3) + ')');
                icon.g.style.opacity = fade.toFixed(3);
                icon.g.style.setProperty('--hover', icon.hover.toFixed(3));
            });

            slowdown = lerp(slowdown, closest, ease);
            hero.classList.toggle('lph-is-pointing', closest > 0.55);

            window.requestAnimationFrame(frame);
        }

        window.requestAnimationFrame(frame);
    }

    function start() {
        Array.prototype.forEach.call(document.querySelectorAll('.lph-orbit'), function (svg) {
            if (svg.dataset.lpReady) return;
            svg.dataset.lpReady = '1';
            initOrbit(svg);
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', start);
    } else {
        start();
    }
})();
