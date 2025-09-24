/**
 * Checkout sticky layout helper (no external dependencies).
 * Keeps the left column fixed once we are on desktop viewports.
 */
(function (window, document) {
    'use strict';

    var BREAKPOINT = 1024;
    var selectors = [
        '.header-sticky',
        '.sticky-header',
        '#header.stuck',
        '#header[style*="position: fixed"]',
        '.site-header.fixed',
        '.site-header.is-stuck',
        '.nectar-header:not(.transparent)'
    ];

    var state = {
        main: null,
        sidebar: null,
        resizeRaf: null,
        observer: null,
        stickyActive: false
    };

    function isDesktop() {
        return window.matchMedia('(min-width: ' + BREAKPOINT + 'px)').matches;
    }

    function computeOffset() {
        var offset = 24;

        if (document.body.classList.contains('admin-bar')) {
            offset += 32;
        }

        for (var i = 0; i < selectors.length; i++) {
            var el = document.querySelector(selectors[i]);
            if (!el) { continue; }
            var style = window.getComputedStyle(el);
            if (style.display === 'none' || style.visibility === 'hidden') { continue; }
            if (style.position === 'fixed' || style.position === 'sticky') {
                offset += Math.round(el.getBoundingClientRect().height);
                break;
            }
        }

        return Math.max(Math.min(offset, 260), 24);
    }

    function updateSidebarShadows() {
        if (!state.sidebar) {
            return;
        }
        var threshold = 4;
        var top = state.sidebar.scrollTop <= threshold;
        var bottom = state.sidebar.scrollHeight - state.sidebar.clientHeight - state.sidebar.scrollTop <= threshold;
        state.sidebar.classList.toggle('has-shadow-top', !top);
        state.sidebar.classList.toggle('has-shadow-bottom', !bottom);
    }

    function cleanupSticky() {
        if (!state.stickyActive) {
            return;
        }

        if (state.main) {
            state.main.classList.remove('btr-sticky-main');
            state.main.style.removeProperty('--btr-sticky-offset');
        }

        if (state.sidebar) {
            state.sidebar.removeEventListener('scroll', updateSidebarShadows);
            state.sidebar.classList.remove('btr-sticky-sidebar', 'has-shadow-top', 'has-shadow-bottom');
            state.sidebar.style.removeProperty('--btr-sticky-offset');
        }

        document.documentElement.style.removeProperty('--btr-sticky-offset');
        state.stickyActive = false;
    }

    function applySticky() {
        cleanupSticky();

        if (!state.main || !isDesktop()) {
            return;
        }

        var offset = computeOffset();
        document.documentElement.style.setProperty('--btr-sticky-offset', offset + 'px');

        state.main.classList.add('btr-sticky-main');
        state.main.style.setProperty('--btr-sticky-offset', offset + 'px');

        state.sidebar = document.querySelector('.wc-block-checkout__sidebar, .wc-block-components-sidebar');
        if (state.sidebar) {
            state.sidebar.classList.add('btr-sticky-sidebar');
            state.sidebar.style.setProperty('--btr-sticky-offset', offset + 'px');
            state.sidebar.addEventListener('scroll', updateSidebarShadows, { passive: true });
            updateSidebarShadows();
        }

        state.stickyActive = true;
    }

    function handleResize() {
        if (state.resizeRaf) {
            cancelAnimationFrame(state.resizeRaf);
        }
        state.resizeRaf = requestAnimationFrame(applySticky);
    }

    function observeLayout() {
        if (state.observer) {
            return;
        }
        state.observer = new MutationObserver(function () {
            var candidate = document.querySelector('.wc-block-checkout__main, .wc-block-components-main');
            if (!candidate || state.main === candidate) {
                return;
            }
            state.main = candidate;
            applySticky();
        });

        state.observer.observe(document.body, {
            childList: true,
            subtree: true
        });
    }

    function init() {
        if (!document.body.classList.contains('woocommerce-checkout')) {
            return;
        }

        state.main = document.querySelector('.wc-block-checkout__main, .wc-block-components-main');
        if (!state.main) {
            observeLayout();
        }

        applySticky();

        window.addEventListener('resize', handleResize, { passive: true });
        window.addEventListener('orientationchange', handleResize, { passive: true });
        document.addEventListener('visibilitychange', function () {
            if (!document.hidden) {
                applySticky();
            }
        });

        observeLayout();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})(window, document);
