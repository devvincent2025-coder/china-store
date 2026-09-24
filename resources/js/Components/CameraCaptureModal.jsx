import { useEffect, useRef, useState } from 'react';
import Modal from '@/Components/Modal';
import { useTranslation } from '@/hooks/useTranslation';

/**
 * A dedicated in-app camera page (as a modal) for capturing product photos directly from the
 * device's camera, instead of only accepting drag-and-drop/file-picker uploads. Stays open across
 * multiple shots — each Capture click hands a fresh File back to `onCapture` — so a vendor can snap
 * several product photos in one session before closing it.
 */
export default function CameraCaptureModal({ show, onClose, onCapture }) {
    const { __ } = useTranslation();
    const videoRef = useRef(null);
    const canvasRef = useRef(null);
    const streamRef = useRef(null);
    const [error, setError] = useState(null);
    const [ready, setReady] = useState(false);
    const [shotCount, setShotCount] = useState(0);

    useEffect(() => {
        if (!show) return undefined;

        let cancelled = false;
        setError(null);
        setReady(false);
        setShotCount(0);

        navigator.mediaDevices?.getUserMedia({ video: { facingMode: 'environment' }, audio: false })
            .then((stream) => {
                if (cancelled) {
                    stream.getTracks().forEach((track) => track.stop());
                    return;
                }
                streamRef.current = stream;
                if (videoRef.current) {
                    videoRef.current.srcObject = stream;
                }
                setReady(true);
            })
            .catch(() => {
                if (!cancelled) {
                    setError(__("We couldn't access your camera. Check your browser's camera permission for this site, or use the file picker instead."));
                }
            });

        return () => {
            cancelled = true;
            streamRef.current?.getTracks().forEach((track) => track.stop());
            streamRef.current = null;
        };
    }, [show]);

    const capture = () => {
        const video = videoRef.current;
        const canvas = canvasRef.current;
        if (!video || !canvas || !video.videoWidth) return;

        canvas.width = video.videoWidth;
        canvas.height = video.videoHeight;
        canvas.getContext('2d').drawImage(video, 0, 0, canvas.width, canvas.height);

        canvas.toBlob((blob) => {
            if (!blob) return;
            const file = new File([blob], `product-photo-${Date.now()}.jpg`, { type: 'image/jpeg' });
            onCapture(file);
            setShotCount((n) => n + 1);
        }, 'image/jpeg', 0.92);
    };

    return (
        <Modal show={show} onClose={onClose} maxWidth="lg">
            <div className="p-6">
                <div className="mb-4 flex items-center justify-between">
                    <h2 className="text-lg font-medium text-neutral-900">{__('Take Product Photos')}</h2>
                    <button
                        type="button"
                        onClick={onClose}
                        aria-label={__('Close')}
                        className="rounded-full p-1 text-neutral-400 hover:bg-neutral-100 hover:text-neutral-600"
                    >
                        <svg className="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                {error ? (
                    <p className="rounded-md bg-red-50 p-4 text-sm text-red-600">{error}</p>
                ) : (
                    <div className="relative overflow-hidden rounded-lg bg-neutral-900">
                        <video
                            ref={videoRef}
                            autoPlay
                            playsInline
                            muted
                            className="aspect-[4/3] w-full object-cover"
                        />
                        {!ready && (
                            <div className="absolute inset-0 flex items-center justify-center text-sm text-white/70">
                                {__('Starting camera…')}
                            </div>
                        )}
                        {shotCount > 0 && (
                            <span className="absolute right-2 top-2 rounded-full bg-black/60 px-2.5 py-1 text-xs font-semibold text-white">
                                {shotCount} {__('captured')}
                            </span>
                        )}
                    </div>
                )}

                <canvas ref={canvasRef} className="hidden" />

                <div className="mt-4 flex items-center justify-between gap-3">
                    <p className="text-xs text-neutral-500">{__('Add at least 3 photos for this product.')}</p>
                    <div className="flex gap-2">
                        <button
                            type="button"
                            onClick={onClose}
                            className="rounded-md border border-neutral-300 px-4 py-2 text-sm font-semibold text-neutral-700 hover:bg-neutral-50"
                        >
                            {__('Done')}
                        </button>
                        <button
                            type="button"
                            onClick={capture}
                            disabled={!ready || !!error}
                            className="rounded-md bg-orange-600 px-5 py-2 text-sm font-semibold text-white hover:bg-orange-700 disabled:opacity-50"
                        >
                            {__('Capture')}
                        </button>
                    </div>
                </div>
            </div>
        </Modal>
    );
}
