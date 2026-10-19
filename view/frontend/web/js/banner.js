/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 *
 * Zero banner carousels ([data-zero-banner]): arrows, dots, and autoplay
 * every data-autoplay seconds (0 = off), from the visitor's first touch,
 * scroll, pointer move or key: a page nobody has touched yet stays still, so
 * the first slide (the page's largest image) isn't swapped away while it is
 * still being looked at, and a speed measurement sees a settled screen.
 * Each banner is set up once, however
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
        // The slide width is read once and on resize, not on every scroll frame:
        // reading clientWidth after the dots changed forces a layout each time.
        var width = track.clientWidth;
        if (window.ResizeObserver) {
            new ResizeObserver(function () { width = track.clientWidth; }).observe(track);
        } else {
            window.addEventListener('resize', function () { width = track.clientWidth; });
        }

        function current() {
            return width ? Math.round(Math.abs(track.scrollLeft) / width) : 0;
        }
        function go(index) {
            var i = (index + count) % count;
            track.scrollTo({ left: dir * i * width, behavior: reduceMotion ? 'auto' : 'smooth' });
        }

        var prev = root.querySelector('[data-prev]');
        var next = root.querySelector('[data-next]');
        if (prev) { prev.addEventListener('click', function () { go(current() - 1); }); }
        if (next) { next.addEventListener('click', function () { go(current() + 1); }); }
        dots.forEach(function (dot, i) {
            dot.addEventListener('click', function () { go(i); });
        });

        var frame = 0;
        var active = current();
        track.addEventListener('scroll', function () {
            cancelAnimationFrame(frame);
            frame = requestAnimationFrame(function () {
                var c = current();
                if (c === active) {
                    return;
                }
                active = c;
                dots.forEach(function (dot, i) { dot.setAttribute('aria-current', i === c ? 'true' : 'false'); });
            });
        }, { passive: true });

        var interval = (parseInt(root.getAttribute('data-autoplay'), 10) || 0) * 1000;
        var pauseButton = root.querySelector('[data-pause]');
        if (!interval || !pauseButton) {
            return;
        }
        // WCAG 2.2.2: moving content can be paused (button), and stops while
        // the mouse is over it or keyboard focus is inside, or when the
        // visitor asked for reduced motion. Autoplay is armed by the first
        // interaction with the page (onFirstInteraction) or by the button;
        // until then the button shows Play, as nothing moves yet.
        var paused = reduceMotion, armed = false, held = false, timer = 0;
        function render() {
            var showPlay = paused || !armed;
            pauseButton.setAttribute('aria-label', pauseButton.dataset[showPlay ? 'labelPlay' : 'labelPause']);
            pauseButton.querySelector('[data-icon-pause]').toggleAttribute('hidden', showPlay);
            pauseButton.querySelector('[data-icon-play]').toggleAttribute('hidden', !showPlay);
        }
        function schedule() {
            clearTimeout(timer);
            if (armed && !paused) {
                timer = setTimeout(function () {
                    if (!held && !document.hidden) { go(current() + 1); }
                    schedule();
                }, interval);
            }
        }
        function arm() {
            armed = true;
            render();
            schedule();
        }
        pauseButton.addEventListener('click', function () {
            if (armed) {
                paused = !paused;
            } else {
                paused = false;
            }
            arm();
        });
        // A mouse over the banner holds it; a finger doesn't (its emulated mouseenter never leaves).
        root.addEventListener('pointerenter', function (event) { if (event.pointerType === 'mouse') { held = true; } });
        root.addEventListener('pointerleave', function (event) { if (event.pointerType === 'mouse') { held = false; } });
        // Keyboard focus inside holds it; the focus a tap leaves on the button doesn't.
        root.addEventListener('focusin', function (event) { held = event.target.matches(':focus-visible'); });
        root.addEventListener('focusout', function () { held = false; });
        track.addEventListener('pointerdown', function () { paused = true; render(); schedule(); });
        render();
        onFirstInteraction(arm, pauseButton);
    }

    /**
     * Runs fn once, on the visitor's first touch, scroll, pointer move or key;
     * not for an event on `except` (a banner's own button handles its click).
     */
    var interacted = false, waiting = [];
    function onFirstInteraction(fn, except) {
        if (interacted) {
            fn();
            return;
        }
        waiting.push({ fn: fn, except: except });
    }
    ['pointermove', 'pointerdown', 'touchstart', 'scroll', 'keydown', 'wheel'].forEach(function (type) {
        window.addEventListener(type, function handler(event) {
            if (interacted) {
                return;
            }
            var target = event.target && event.target.closest ? event.target : null;
            var rest = waiting.filter(function (w) { return w.except && target && w.except.contains(target); });
            interacted = true;
            waiting.filter(function (w) { return rest.indexOf(w) === -1; }).forEach(function (w) { w.fn(); });
            waiting = rest;
            if (rest.length) {
                interacted = false;
            }
        }, { passive: true });
    });

    document.querySelectorAll('[data-zero-banner]').forEach(initBanner);
})();
