import { useEffect, useState } from 'react';
import { useTranslation } from '@/hooks/useTranslation';

const SEEN_KEY = 'cin_install_prompt_seen';

function isStandalone() {
    return window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;
}

function isMobileViewport() {
    return window.matchMedia('(max-width: 820px)').matches;
}

function isIos() {
    return /iphone|ipad|ipod/i.test(window.navigator.userAgent) && !window.MSStream;
}

/**
 * A custom install prompt, shown once (ever, per browser — see SEEN_KEY) to a first-time mobile
 * visitor. Needed because neither platform gives you this for free: Chrome/Android fires
 * `beforeinstallprompt` but renders NO UI of its own for it (you must capture the event and build
 * your own button, which is what this component's Android branch does) — and iOS Safari has no
 * install API at all, so its branch just shows manual "Add to Home Screen" instructions instead.
 * Desktop is skipped entirely; this is specifically a mobile-shopping-experience nudge.
 */
export default function InstallAppModal() {
    const { __ } = useTranslation();
    const [deferredPrompt, setDeferredPrompt] = useState(null);
    const [show, setShow] = useState(false);
    const [platform, setPlatform] = useState(null); // 'android' | 'ios'
    const [installing, setInstalling] = useState(false);

    useEffect(() => {
        if (localStorage.getItem(SEEN_KEY)) return;
        if (isStandalone()) return;
        if (!isMobileViewport()) return;

        if (isIos()) {
            setPlatform('ios');
            setShow(true);
            return;
        }

        const onBeforeInstallPrompt = (event) => {
            event.preventDefault();
            setDeferredPrompt(event);
            setPlatform('android');
            setShow(true);
        };

        window.addEventListener('beforeinstallprompt', onBeforeInstallPrompt);
        return () => window.removeEventListener('beforeinstallprompt', onBeforeInstallPrompt);
    }, []);

    const dismiss = () => {
        localStorage.setItem(SEEN_KEY, '1');
        setShow(false);
    };

    const install = async () => {
        if (!deferredPrompt) return;
        setInstalling(true);
        deferredPrompt.prompt();
        await deferredPrompt.userChoice;
        setInstalling(false);
        dismiss();
    };

    if (!show) return null;

    return (
        <div
            className="fixed inset-0 z-[100] flex items-end justify-center bg-black/60 p-4 sm:items-center"
            role="dialog"
            aria-modal="true"
            aria-labelledby="install-app-title"
        >
            <div className="relative w-full max-w-sm overflow-hidden rounded-3xl bg-gradient-to-br from-orange-500 via-orange-600 to-red-600 p-1 shadow-2xl">
                <div className="rounded-[22px] bg-gradient-to-b from-white/10 to-transparent p-6 text-center text-white sm:p-8">
                    <button
                        type="button"
                        onClick={dismiss}
                        aria-label={__('Close')}
                        className="absolute right-4 top-4 flex h-8 w-8 items-center justify-center rounded-full bg-white/20 text-white hover:bg-white/30"
                    >
                        <svg className="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2.5} d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>

                    <div className="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-2xl bg-white/15 shadow-inner">
                        <img src="/images/pwa/icon-192.png" alt="" className="h-12 w-12 rounded-xl" />
                    </div>

                    <h2 id="install-app-title" className="font-display text-xl font-extrabold sm:text-2xl">
                        {__('Shop Faster — Install the App')}
                    </h2>
                    <p className="mt-2 text-sm leading-relaxed text-white/90">
                        {__("Add ChinaInNigeria to your home screen for instant access, quicker loading, and a shopping cart that's always one tap away.")}
                    </p>

                    {platform === 'android' ? (
                        <button
                            type="button"
                            onClick={install}
                            disabled={installing}
                            className="cin-cta-blink mt-6 w-full rounded-xl bg-green-500 px-6 py-4 text-base font-extrabold text-white shadow-lg transition-transform active:scale-[0.98] disabled:opacity-70"
                        >
                            {installing ? __('Installing…') : `📲 ${__('Install App')}`}
                        </button>
                    ) : (
                        <div className="mt-6 rounded-xl bg-white/10 p-4 text-left text-sm text-white/90">
                            <p className="font-semibold">{__('To install:')}</p>
                            <ol className="mt-1 list-decimal space-y-1 pl-4">
                                <li>{__("Tap the Share button in Safari's toolbar.")}</li>
                                <li>{__('Scroll down and tap Add to Home Screen.')}</li>
                            </ol>
                        </div>
                    )}

                    <button
                        type="button"
                        onClick={dismiss}
                        className="mt-4 w-full text-center text-sm font-medium text-white/80 hover:text-white hover:underline"
                    >
                        {__('Maybe later')}
                    </button>
                </div>
            </div>
        </div>
    );
}
