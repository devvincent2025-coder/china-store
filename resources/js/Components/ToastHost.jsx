import { usePage } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';

const ICONS = {
    success: (
        <svg className="h-7 w-7 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden>
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2.2} d="M9 12l2 2 4-4m7 2a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
    ),
    error: (
        <svg className="h-7 w-7 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden>
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2.2} d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z" />
        </svg>
    ),
    warning: (
        <svg className="h-7 w-7 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden>
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2.2} d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
        </svg>
    ),
};

const STYLES = {
    success: 'border-tb-green/30 bg-white text-tb-green shadow-[0_8px_30px_rgba(0,162,106,0.18)]',
    error: 'border-tb-red/30 bg-white text-tb-red shadow-[0_8px_30px_rgba(255,0,54,0.16)]',
    warning: 'border-amber-400/40 bg-white text-amber-700 shadow-[0_8px_30px_rgba(217,119,6,0.16)]',
};

const ACCENT = {
    success: 'bg-tb-green',
    error: 'bg-tb-red',
    warning: 'bg-amber-500',
};

/**
 * Global toast host — reads Inertia flash.success / flash.error / flash.warning,
 * animates in at the top-right, and auto-dismisses after 3 seconds.
 */
export default function ToastHost() {
    const { flash } = usePage().props;
    const [toasts, setToasts] = useState([]);
    const seenRef = useRef(null);

    useEffect(() => {
        if (!flash?.success && !flash?.error && !flash?.warning) {
            seenRef.current = null;
            return undefined;
        }

        const next = [];
        if (flash?.success) next.push({ type: 'success', message: String(flash.success) });
        if (flash?.error) next.push({ type: 'error', message: String(flash.error) });
        if (flash?.warning) next.push({ type: 'warning', message: String(flash.warning) });

        const token = `${flash.success}|${flash.error}|${flash.warning}`;
        if (seenRef.current === token) return undefined;
        seenRef.current = token;

        const created = next.map((t) => ({
            ...t,
            id: `${Date.now()}-${Math.random().toString(36).slice(2, 8)}`,
            leaving: false,
        }));

        setToasts((prev) => [...prev, ...created]);

        const timers = created.map((toast) =>
            setTimeout(() => {
                setToasts((prev) => prev.map((t) => (t.id === toast.id ? { ...t, leaving: true } : t)));
                setTimeout(() => {
                    setToasts((prev) => prev.filter((t) => t.id !== toast.id));
                }, 320);
            }, 3000),
        );

        return () => timers.forEach(clearTimeout);
    }, [flash?.success, flash?.error, flash?.warning]);

    const dismiss = (id) => {
        setToasts((prev) => prev.map((t) => (t.id === id ? { ...t, leaving: true } : t)));
        setTimeout(() => {
            setToasts((prev) => prev.filter((t) => t.id !== id));
        }, 320);
    };

    if (toasts.length === 0) return null;

    return (
        <div
            className="pointer-events-none fixed right-3 top-3 z-[100] flex w-[min(100vw-1.5rem,26rem)] flex-col gap-3 sm:right-5 sm:top-5"
            aria-live="polite"
            aria-relevant="additions"
        >
            {toasts.map((toast) => (
                <div
                    key={toast.id}
                    role="status"
                    className={`pointer-events-auto relative overflow-hidden rounded-2xl border px-5 py-4 font-display ${STYLES[toast.type]} ${
                        toast.leaving ? 'cin-toast-out' : 'cin-toast-in'
                    }`}
                >
                    <div className={`absolute inset-y-0 left-0 w-1.5 ${ACCENT[toast.type]}`} />
                    <div className="flex items-start gap-3 pl-1">
                        {ICONS[toast.type]}
                        <div className="min-w-0 flex-1 pt-0.5">
                            <p className="text-[11px] font-bold uppercase tracking-wide opacity-70">
                                {toast.type}
                            </p>
                            <p className="mt-0.5 text-[16px] font-bold leading-snug text-tb-text sm:text-[17px]">
                                {toast.message}
                            </p>
                        </div>
                        <button
                            type="button"
                            onClick={() => dismiss(toast.id)}
                            className="rounded-md p-1 text-tb-muted hover:bg-neutral-100 hover:text-tb-text"
                            aria-label="Dismiss"
                        >
                            <svg className="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                </div>
            ))}
        </div>
    );
}
