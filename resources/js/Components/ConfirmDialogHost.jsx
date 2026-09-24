import { useCallback, useEffect, useState } from 'react';
import { registerConfirmHandler } from '@/utils/confirm';
import { useTranslation } from '@/hooks/useTranslation';

/**
 * Global replacement for the browser's native `window.confirm()` — mounted once in app.jsx (like
 * ToastHost/InstallAppModal), it registers itself as the handler `utils/confirm.js` dispatches to,
 * so any file can `await confirm('...')` and get a properly branded modal instead of the
 * "localhost:8080 says" browser chrome dialog.
 */
export default function ConfirmDialogHost() {
    const { __ } = useTranslation();
    const [state, setState] = useState(null);

    const request = useCallback((message, options = {}) => {
        return new Promise((resolve) => {
            setState({ message, options, resolve });
        });
    }, []);

    useEffect(() => {
        registerConfirmHandler(request);
    }, [request]);

    if (!state) return null;

    const { message, options, resolve } = state;
    const isDanger = options.tone === 'danger';

    const respond = (value) => {
        resolve(value);
        setState(null);
    };

    return (
        <div
            className="fixed inset-0 z-[200] flex items-center justify-center bg-black/60 p-4"
            role="alertdialog"
            aria-modal="true"
            aria-labelledby="confirm-dialog-message"
        >
            <div className="w-full max-w-sm rounded-2xl bg-white p-6 text-center shadow-2xl">
                <div className={'mx-auto flex h-12 w-12 items-center justify-center rounded-full ' + (isDanger ? 'bg-red-100' : 'bg-orange-100')}>
                    <svg
                        className={'h-6 w-6 ' + (isDanger ? 'text-red-600' : 'text-orange-600')}
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke="currentColor"
                    >
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z" />
                    </svg>
                </div>

                <p id="confirm-dialog-message" className="mt-4 text-sm font-medium leading-relaxed text-gray-700">
                    {message}
                </p>

                <div className="mt-6 flex gap-3">
                    <button
                        type="button"
                        onClick={() => respond(false)}
                        className="flex-1 rounded-lg border border-gray-300 px-4 py-2.5 text-sm font-semibold text-gray-700 transition hover:bg-gray-50"
                    >
                        {options.cancelLabel ?? __('Cancel')}
                    </button>
                    <button
                        type="button"
                        onClick={() => respond(true)}
                        autoFocus
                        className={
                            'flex-1 rounded-lg px-4 py-2.5 text-sm font-semibold text-white transition ' +
                            (isDanger ? 'bg-red-600 hover:bg-red-700' : 'bg-orange-600 hover:bg-orange-700')
                        }
                    >
                        {options.confirmLabel ?? __('OK')}
                    </button>
                </div>
            </div>
        </div>
    );
}
