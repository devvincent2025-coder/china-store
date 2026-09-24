import { router, usePage } from '@inertiajs/react';

/**
 * A persistent top bar shown on every page while an admin is browsing as another user (see
 * Http\Controllers\Admin\ImpersonationController) — mounted globally in app.jsx rather than per
 * layout, since impersonation can land the admin on either storefront or vendor-dashboard pages.
 */
export default function ImpersonationBanner() {
    const { auth, impersonating } = usePage().props;

    if (!impersonating) return null;

    const stop = () => router.post(route('admin.impersonate.stop'));

    return (
        <div className="sticky top-0 z-[60] flex items-center justify-center gap-3 bg-amber-500 px-4 py-2 text-sm font-semibold text-white">
            <span>
                Viewing as {auth?.user?.name} ({auth?.user?.email})
            </span>
            <button
                type="button"
                onClick={stop}
                className="rounded-md bg-black/20 px-3 py-1 text-xs font-bold hover:bg-black/30"
            >
                Return to Admin
            </button>
        </div>
    );
}
