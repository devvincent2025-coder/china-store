import { router } from '@inertiajs/react';
import { useTranslation } from '@/hooks/useTranslation';

/**
 * Lists the AI tools (Claude, a ChatGPT Custom GPT, ...) this buyer has approved via the OAuth
 * consent screen at /oauth/authorize (see resources/views/oauth/authorize.blade.php) — nothing
 * here was ever pasted in by hand, and revoking access is a single click, mirroring how "connected
 * apps" works on any account that supports third-party OAuth sign-in.
 */
export default function ConnectedAppsPanel({ connectedApps }) {
    const { __ } = useTranslation();

    const revoke = (tokenId) => {
        if (! confirm(__('Disconnect this app? It will no longer be able to act on your account.'))) {
            return;
        }

        router.delete(route('profile.connected-apps.destroy', tokenId), { preserveScroll: true });
    };

    return (
        <div>
            <h2 className="text-sm font-semibold text-neutral-800">{__('Connected AI Assistants')}</h2>
            <p className="mt-1 text-xs text-neutral-500">
                {__('Apps you\'ve allowed to search products, manage your cart, or place orders on your behalf.')}
            </p>

            {connectedApps.length === 0 ? (
                <p className="mt-4 text-sm text-neutral-400">{__('Nothing connected yet.')}</p>
            ) : (
                <ul className="mt-4 divide-y divide-neutral-100">
                    {connectedApps.map((app) => (
                        <li key={app.id} className="flex items-center justify-between gap-4 py-3">
                            <div>
                                <p className="text-sm font-medium text-neutral-800">{app.client_name}</p>
                                <p className="text-xs text-neutral-500">{app.scopes.join(', ')}</p>
                            </div>
                            <button
                                type="button"
                                onClick={() => revoke(app.id)}
                                className="shrink-0 rounded-md border border-neutral-300 px-3 py-1.5 text-xs font-semibold text-neutral-700 hover:bg-neutral-50"
                            >
                                {__('Disconnect')}
                            </button>
                        </li>
                    ))}
                </ul>
            )}
        </div>
    );
}
