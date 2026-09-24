import '../css/app.css';
import './bootstrap';

import ToastHost from '@/Components/ToastHost';
import LocaleSync from '@/Components/LocaleSync';
import ImpersonationBanner from '@/Components/ImpersonationBanner';
import InstallAppModal from '@/Components/InstallAppModal';
import MobileFullscreenTrigger from '@/Components/MobileFullscreenTrigger';
import ConfirmDialogHost from '@/Components/ConfirmDialogHost';
import { createInertiaApp } from '@inertiajs/react';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createRoot } from 'react-dom/client';

// Unconditional registration (not just on push-notification opt-in, see utils/pushNotifications.js)
// — this is what makes the app installable at all: Chrome's installability criteria require an
// active service worker with a fetch handler, not just a manifest.json. Safe to call every load;
// the browser no-ops if the same script is already the active worker.
if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/sw.js').catch(() => {
            // Installability just degrades gracefully — nothing in the app depends on this
            // succeeding, so there's nothing useful to surface to the user if it fails.
        });
    });
}

createInertiaApp({
    // No title-suffixing callback here — every page's own <title> (set via SeoHead.jsx from the
    // `seo`/`seoDefaults` props, see App\Support\Seo\Seo) already ends with "- ChinaInNigeria"
    // itself. Inertia re-runs this callback on the title text ALREADY present in the DOM at
    // hydration time (see @inertiajs/core's head.ts collect(), which wraps the server-rendered
    // <title inertia> content through this same callback) — appending the app name here too used
    // to double it up into "...- ChinaInNigeria - ChinaInNigeria" the moment JS took over.
    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.jsx`,
            import.meta.glob('./Pages/**/*.jsx'),
        ),
    setup({ el, App, props }) {
        const root = createRoot(el);

        root.render(
            <App {...props}>
                {({ Component, props: pageProps, key }) => {
                    let page = <Component key={key} {...pageProps} />;

                    if (typeof Component.layout === 'function') {
                        page = Component.layout(page);
                    } else if (Array.isArray(Component.layout)) {
                        page = Component.layout
                            .concat(page)
                            .reverse()
                            .reduce((children, Layout) => (
                                <Layout {...pageProps}>{children}</Layout>
                            ));
                    }

                    return (
                        <>
                            <LocaleSync />
                            <ToastHost />
                            <ImpersonationBanner />
                            <InstallAppModal />
                            <MobileFullscreenTrigger />
                            <ConfirmDialogHost />
                            {page}
                        </>
                    );
                }}
            </App>,
        );
    },
    progress: {
        color: '#FF5000',
    },
});
