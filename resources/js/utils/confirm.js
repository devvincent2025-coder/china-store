let handler = null;

/**
 * Wired up once by ConfirmDialogHost (mounted globally in app.jsx, like ToastHost) so any file can
 * just `import confirm from '@/utils/confirm'` and `await confirm(message)` without needing a
 * hook/context wrapper at every call site — mirrors how the old `window.confirm()` calls it's
 * replacing were used, just async and actually styled.
 */
export function registerConfirmHandler(fn) {
    handler = fn;
}

/**
 * @param {string} message
 * @param {{ confirmLabel?: string, cancelLabel?: string, tone?: 'default' | 'danger' }} [options]
 * @returns {Promise<boolean>}
 */
export default function confirm(message, options = {}) {
    if (!handler) {
        // Should never happen — ConfirmDialogHost is always mounted — but a page rendered before
        // hydration finishes shouldn't hang forever waiting on a dialog that can't appear yet.
        return Promise.resolve(window.confirm(message));
    }

    return handler(message, options);
}
