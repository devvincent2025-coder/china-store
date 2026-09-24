import { useEffect } from 'react';

function isMobileViewport() {
    return window.matchMedia('(max-width: 820px)').matches;
}

function isStandalone() {
    return (
        window.matchMedia('(display-mode: standalone)').matches ||
        window.matchMedia('(display-mode: fullscreen)').matches ||
        window.navigator.standalone === true
    );
}

function requestFullscreen() {
    const el = document.documentElement;
    const request = el.requestFullscreen || el.webkitRequestFullscreen || el.mozRequestFullScreen || el.msRequestFullscreen;

    if (request) {
        Promise.resolve(request.call(el)).catch(() => {
            // Some mobile browsers (notably older iOS Safari) either don't implement this at all
            // or reject it depending on settings — there's nothing useful to surface to the
            // visitor if it fails, the site just stays in the normal browser chrome.
        });
    }
}

/**
 * Browsers block the Fullscreen API from firing without a user gesture, so a page can never go
 * fullscreen purely "on load" the way an installed PWA can (see manifest.json's
 * `display: fullscreen`, which starts chrome-free with no tap needed at all). This is the closest
 * equivalent for a visitor who's just using the site in a regular mobile browser tab: it silently
 * arms a one-time listener for the very first tap/click anywhere on the page, and requests
 * fullscreen inside that same gesture — so the first thing a mobile visitor touches also happens
 * to drop the address bar and browser chrome.
 */
export default function MobileFullscreenTrigger() {
    useEffect(() => {
        if (!isMobileViewport() || isStandalone()) return;
        if (typeof document.fullscreenEnabled !== 'undefined' && !document.fullscreenEnabled) return;

        const trigger = () => {
            requestFullscreen();
            window.removeEventListener('touchend', trigger);
            window.removeEventListener('click', trigger);
        };

        window.addEventListener('touchend', trigger, { passive: true });
        window.addEventListener('click', trigger);

        return () => {
            window.removeEventListener('touchend', trigger);
            window.removeEventListener('click', trigger);
        };
    }, []);

    return null;
}
