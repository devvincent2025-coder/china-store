import { useEffect, useRef, useState } from 'react';
import { router } from '@inertiajs/react';
import axios from 'axios';

/**
 * Taobao-style image search dropdown: paste or upload a photo, then POST to
 * /search/image which ranks catalog products by visual fingerprint similarity.
 */
export default function ImageSearchModal({ open, onClose, anchorRef, mobileAnchorRef }) {
    const inputRef = useRef(null);
    const panelRef = useRef(null);
    const [preview, setPreview] = useState(null);
    const [file, setFile] = useState(null);
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState('');

    useEffect(() => {
        if (!open) return undefined;

        const onKey = (e) => {
            if (e.key === 'Escape') onClose();
        };

        const onPaste = (e) => {
            const items = e.clipboardData?.items;
            if (!items) return;
            for (const item of items) {
                if (item.type.startsWith('image/')) {
                    const blob = item.getAsFile();
                    if (blob) acceptFile(blob);
                    e.preventDefault();
                    break;
                }
            }
        };

        const onDocClick = (e) => {
            if (panelRef.current?.contains(e.target)) return;
            if (anchorRef?.current?.contains(e.target)) return;
            if (mobileAnchorRef?.current?.contains(e.target)) return;
            onClose();
        };

        document.addEventListener('keydown', onKey);
        document.addEventListener('paste', onPaste);
        document.addEventListener('mousedown', onDocClick);
        return () => {
            document.removeEventListener('keydown', onKey);
            document.removeEventListener('paste', onPaste);
            document.removeEventListener('mousedown', onDocClick);
        };
    }, [open, onClose, anchorRef, mobileAnchorRef]);

    useEffect(() => {
        if (!open) {
            setPreview(null);
            setFile(null);
            setError('');
            setBusy(false);
        }
    }, [open]);

    const acceptFile = (next) => {
        if (!next || !next.type.startsWith('image/')) {
            setError('Please choose an image file.');
            return;
        }
        if (next.size > 5 * 1024 * 1024) {
            setError('Image must be under 5MB.');
            return;
        }
        setError('');
        setFile(next);
        setPreview((prev) => {
            if (prev) URL.revokeObjectURL(prev);
            return URL.createObjectURL(next);
        });
    };

    const onDrop = (e) => {
        e.preventDefault();
        const dropped = e.dataTransfer.files?.[0];
        if (dropped) acceptFile(dropped);
    };

    const submit = async () => {
        if (!file) {
            setError('Paste or upload an image first.');
            return;
        }

        setBusy(true);
        setError('');

        try {
            const body = new FormData();
            body.append('image', file);

            const { data } = await axios.post(route('search.image'), body, {
                headers: { Accept: 'application/json' },
            });

            onClose();
            router.get(data.redirect || route('products.index', { visual: 1 }), {}, { preserveState: false });
        } catch (err) {
            const data = err.response?.data;
            setError(
                data?.message
                || data?.errors?.image?.[0]
                || 'Image search failed. Try another photo.',
            );
            setBusy(false);
        }
    };

    if (!open) return null;

    return (
        <div
            ref={panelRef}
            className="absolute left-0 right-0 top-2 z-50 mx-auto w-full max-w-[420px] rounded-xl border border-tb-border bg-white p-4 shadow-lift md:left-auto md:right-4 md:mx-0"
            role="dialog"
            aria-label="Search by image"
        >
            <div className="mb-3 flex items-center justify-between">
                <h3 className="font-display text-[16px] font-bold text-tb-text">Search by image</h3>
                <button type="button" onClick={onClose} className="rounded p-1 text-tb-muted hover:bg-tb-card hover:text-tb-text" aria-label="Close">
                    <svg className="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <div
                onDragOver={(e) => e.preventDefault()}
                onDrop={onDrop}
                onClick={() => inputRef.current?.click()}
                className="flex min-h-[180px] cursor-pointer flex-col items-center justify-center rounded-lg border border-dashed border-[#d0d0d0] bg-white px-4 py-6 text-center hover:border-tb"
            >
                {preview ? (
                    <img src={preview} alt="Preview" className="max-h-40 max-w-full rounded-md object-contain" />
                ) : (
                    <>
                        <div className="relative text-tb-muted">
                            <svg className="h-12 w-12" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M4 5a1 1 0 011-1h4l2-2h2l2 2h4a1 1 0 011 1v12a1 1 0 01-1 1H5a1 1 0 01-1-1V5z" />
                                <circle cx="12" cy="12" r="3.2" strokeWidth={1.5} />
                            </svg>
                            <span className="absolute -bottom-1 -right-1 flex h-5 w-5 items-center justify-center rounded bg-tb text-[12px] font-bold text-white">+</span>
                        </div>
                        <p className="mt-3 font-ui text-[13px] text-tb-muted">Ctrl+V Paste the image here</p>
                    </>
                )}
            </div>

            <input
                ref={inputRef}
                type="file"
                accept="image/*"
                className="hidden"
                onChange={(e) => {
                    const next = e.target.files?.[0];
                    if (next) acceptFile(next);
                }}
            />

            {error && <p className="mt-2 text-center font-ui text-[12px] text-tb-red">{error}</p>}

            <button
                type="button"
                onClick={submit}
                disabled={busy}
                className="mt-4 flex h-10 w-full items-center justify-center rounded-lg border border-[#e5e5e5] font-display text-[14px] font-bold text-tb hover:bg-tb-soft disabled:opacity-60"
            >
                {busy ? 'Searching…' : 'Upload Image'}
            </button>
        </div>
    );
}
