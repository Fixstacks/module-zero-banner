/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 *
 * Zero banner carousels ([data-zero-banner]): arrows, dots, and autoplay
 * every data-autoplay seconds (0 = off). Each banner is set up once, however
 * many banners load this file (no inline script: strict CSP).
 */
(function () {
    'use strict';

    function initBanner(root) {
        if (root.hasAttribute('data-banner-ready')) {
            return;
        }
        root.setAttribute('data-banner-ready', '');
        var track = root.querySelector('[data-track]');
        if (!track) {
            return;
        }
        var count = track.children.length;
        var dots = root.querySelectorAll('[data-dot]');
        var reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        // Right-to-left pages scroll toward negative scrollLeft (slide 2 is at -width).
        var dir = getComputedStyle(track).direction === 'rtl' ? -1 : 1;

        function current() {
            return Math.round(Math.abs(track.scrollLeft) / track.clientWidth);
        }
        function go(index) {
            var i = (index + count) % count;
            track.scrollTo({ left: dir * i * track.clientWidth, behavior: reduceMotion ? 'auto' : 'smooth' });
        }

        var prev = root.querySelector('[data-prev]');
        var next = root.querySelector('[data-next]');
        if (prev) { prev.addEventListener('click', function () { go(current() - 1); }); }
        if (next) { next.addEventListener('click', function () { go(current() + 1); }); }
        dots.forEach(function (dot, i) {
            dot.addEventListener('click', function () { go(i); });
        });

        var frame = 0;
        track.addEventListener('scroll', function () {
            cancelAnimationFrame(frame);
            frame = requestAnimationFrame(function () {
                var c = current();
                dots.forEach(function (dot, i) { dot.setAttribute('aria-current', i === c ? 'true' : 'false'); });
            });
        }, { passive: true });

        var interval = (parseInt(root.getAttribute('data-autoplay'), 10) || 0) * 1000;
        var pauseButton = root.querySelector('[data-pause]');
        if (!interval || !pauseButton) {
            return;
        }
        // WCAG 2.2.2: moving content can be paused (button), and stops while
        // hovered/focused or when the visitor asked for reduced motion.
        var paused = reduceMotion, held = false, timer = 0;
        function render() {
            pauseButton.setAttribute('aria-label', pauseButton.dataset[paused ? 'labelPlay' : 'labelPause']);
            pauseButton.querySelector('[data-icon-pause]').toggleAttribute('hidden', paused);
            pauseButton.querySelector('[data-icon-play]').toggleAttribute('hidden', !paused);
        }
        function schedule() {
            clearTimeout(timer);
            if (!paused) {
                timer = setTimeout(function () {
                    if (!held && !document.hidden) { go(current() + 1); }
                    schedule();
                }, interval);
            }
        }
        pauseButton.addEventListener('click', function () {
            paused = !paused;
            render();
            schedule();
        });
        root.addEventListener('mouseenter', function () { held = true; });
        root.addEventListener('mouseleave', function () { held = false; });
        root.addEventListener('focusin', function () { held = true; });
        root.addEventListener('focusout', function () { held = false; });
        track.addEventListener('pointerdown', function () { paused = true; render(); schedule(); });
        render();
        schedule();
    }

    document.querySelectorAll('[data-zero-banner]').forEach(initBanner);
})();
