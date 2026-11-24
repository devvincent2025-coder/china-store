import { Dialog, DialogPanel, Transition, TransitionChild } from '@headlessui/react';
import { useCallback, useEffect, useRef, useState } from 'react';
import { useTranslation } from '@/hooks/useTranslation';

const MIN_SCALE = 1;
const MAX_SCALE = 4;
const STEP = 0.5;
const SWIPE_THRESHOLD = 60;

const clamp = (n, min, max) => Math.min(max, Math.max(min, n));

function IconButton({ onClick, label, disabled, children, className = '' }) {
    return (
        <button
            type="button"
            onClick={onClick}
            disabled={disabled}
            aria-label={label}
            title={label}
            className={`flex h-10 w-10 items-center justify-center rounded-full bg-white/10 text-white ring-1 ring-white/15 backdrop-blur transition hover:bg-white/20 disabled:cursor-not-allowed disabled:opacity-35 ${className}`}
        >
            {children}
        </button>
    );
}

/**
 * Full-screen product image viewer: prev/next (buttons, arrow keys, swipe), zoom (buttons,
 * +/- keys, mouse wheel, double-click/tap, pinch) and drag-to-pan while zoomed.
 *
 * @param {{ images: string[], index: number, open: boolean, alt?: string,
 *           onClose: () => void, onIndexChange: (i: number) => void }} props
 */
export default function ImageLightbox({ images = [], index = 0, open, alt = '', onClose, onIndexChange }) {
    const { __ } = useTranslation();
    const [scale, setScale] = useState(1);
    const [offset, setOffset] = useState({ x: 0, y: 0 });
    const [dragging, setDragging] = useState(false);
    const stageRef = useRef(null);
    const pointers = useRef(new Map());
    const gesture = useRef(null);

    const count = images.length;
    const go = useCallback((dir) => count > 1 && onIndexChange((index + dir + count) % count), [count, index, onIndexChange]);

    const reset = useCallback(() => {
        setScale(1);
        setOffset({ x: 0, y: 0 });
    }, []);

    // New image (or reopened) -> back to fit-to-screen.
    useEffect(reset, [index, open, reset]);

    // Keep the image from being panned completely out of view.
    const clampOffset = useCallback((next, s) => {
        const rect = stageRef.current?.getBoundingClientRect();
        if (!rect || s <= 1) return { x: 0, y: 0 };
        const maxX = ((s - 1) * rect.width) / 2;
        const maxY = ((s - 1) * rect.height) / 2;
        return { x: clamp(next.x, -maxX, maxX), y: clamp(next.y, -maxY, maxY) };
    }, []);

    const zoomTo = useCallback(
        (next) => {
            const s = clamp(next, MIN_SCALE, MAX_SCALE);
            setScale(s);
            setOffset((o) => clampOffset(o, s));
        },
        [clampOffset],
    );

    // Keyboard: arrows navigate, +/- zoom, 0 resets (Esc is handled by Dialog).
    useEffect(() => {
        if (!open) return undefined;
        const onKey = (e) => {
            if (e.key === 'ArrowRight') go(1);
            else if (e.key === 'ArrowLeft') go(-1);
            else if (e.key === '+' || e.key === '=') zoomTo(scale + STEP);
            else if (e.key === '-') zoomTo(scale - STEP);
            else if (e.key === '0') reset();
        };
        window.addEventListener('keydown', onKey);
        return () => window.removeEventListener('keydown', onKey);
    }, [open, go, zoomTo, reset, scale]);

    // Wheel zoom needs a non-passive listener to stop the page behind from scrolling.
    useEffect(() => {
        const el = stageRef.current;
        if (!open || !el) return undefined;
        const onWheel = (e) => {
            e.preventDefault();
            zoomTo(scale * (e.deltaY < 0 ? 1.15 : 1 / 1.15));
        };
        el.addEventListener('wheel', onWheel, { passive: false });
        return () => el.removeEventListener('wheel', onWheel);
    }, [open, scale, zoomTo]);

    const distance = () => {
        const [a, b] = [...pointers.current.values()];
        return Math.hypot(a.x - b.x, a.y - b.y);
    };

    const onPointerDown = (e) => {
        e.currentTarget.setPointerCapture(e.pointerId);
        pointers.current.set(e.pointerId, { x: e.clientX, y: e.clientY });

        if (pointers.current.size === 2) {
            gesture.current = { type: 'pinch', startDist: distance(), startScale: scale };
        } else {
            gesture.current = { type: 'drag', startX: e.clientX, startY: e.clientY, origin: offset };
            setDragging(true);
        }
    };

    const onPointerMove = (e) => {
        if (!pointers.current.has(e.pointerId)) return;
        pointers.current.set(e.pointerId, { x: e.clientX, y: e.clientY });
        const g = gesture.current;
        if (!g) return;

        if (g.type === 'pinch' && pointers.current.size === 2) {
            zoomTo(g.startScale * (distance() / g.startDist));
        } else if (g.type === 'drag' && scale > 1) {
            setOffset(clampOffset({ x: g.origin.x + e.clientX - g.startX, y: g.origin.y + e.clientY - g.startY }, scale));
        }
    };

    const onPointerUp = (e) => {
        const g = gesture.current;
        // Swipe to change image only when not zoomed in (otherwise the drag was a pan).
        if (g?.type === 'drag' && scale === 1) {
            const dx = e.clientX - g.startX;
            if (Math.abs(dx) > SWIPE_THRESHOLD && Math.abs(dx) > Math.abs(e.clientY - g.startY)) go(dx < 0 ? 1 : -1);
        }
        pointers.current.delete(e.pointerId);
        if (pointers.current.size === 0) {
            gesture.current = null;
            setDragging(false);
        }
    };

    const toggleZoom = () => (scale > 1 ? reset() : zoomTo(2.5));

    return (
        <Transition show={open}>
            <Dialog as="div" className="relative z-[95]" onClose={onClose}>
                <TransitionChild
                    enter="ease-out duration-200"
                    enterFrom="opacity-0"
                    enterTo="opacity-100"
                    leave="ease-in duration-150"
                    leaveFrom="opacity-100"
                    leaveTo="opacity-0"
                >
                    <div className="fixed inset-0 bg-black/90 backdrop-blur-md" aria-hidden="true" />
                </TransitionChild>

                <TransitionChild
                    enter="ease-out duration-200"
                    enterFrom="opacity-0 scale-95"
                    enterTo="opacity-100 scale-100"
                    leave="ease-in duration-150"
                    leaveFrom="opacity-100 scale-100"
                    leaveTo="opacity-0 scale-95"
                >
                    <DialogPanel className="fixed inset-0 flex flex-col">
                        {/* Toolbar */}
                        <div className="flex items-center justify-between gap-3 px-3 py-3 sm:px-5">
                            <span className="rounded-full bg-white/10 px-3 py-1.5 font-display text-[13px] font-semibold tabular-nums text-white ring-1 ring-white/15">
                                {index + 1} / {count}
                            </span>
                            <div className="flex items-center gap-2">
                                <IconButton onClick={() => zoomTo(scale - STEP)} disabled={scale <= MIN_SCALE} label={__('Zoom out')}>
                                    <svg className="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><circle cx="11" cy="11" r="7" strokeWidth={2} /><path strokeLinecap="round" strokeWidth={2} d="M8 11h6M20 20l-3.5-3.5" /></svg>
                                </IconButton>
                                <button
                                    type="button"
                                    onClick={reset}
                                    className="hidden h-10 min-w-[64px] rounded-full bg-white/10 px-3 font-display text-[13px] font-semibold tabular-nums text-white ring-1 ring-white/15 transition hover:bg-white/20 sm:block"
                                    title={__('Reset zoom')}
                                >
                                    {Math.round(scale * 100)}%
                                </button>
                                <IconButton onClick={() => zoomTo(scale + STEP)} disabled={scale >= MAX_SCALE} label={__('Zoom in')}>
                                    <svg className="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><circle cx="11" cy="11" r="7" strokeWidth={2} /><path strokeLinecap="round" strokeWidth={2} d="M8 11h6M11 8v6M20 20l-3.5-3.5" /></svg>
                                </IconButton>
                                <IconButton onClick={onClose} label={__('Close')} className="ml-1 bg-white/20">
                                    <svg className="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path strokeLinecap="round" strokeWidth={2.2} d="M6 18L18 6M6 6l12 12" /></svg>
                                </IconButton>
                            </div>
                        </div>

                        {/* Stage */}
                        <div className="relative min-h-0 flex-1">
                            <div
                                ref={stageRef}
                                className={`absolute inset-0 flex touch-none select-none items-center justify-center overflow-hidden px-2 sm:px-20 ${
                                    scale > 1 ? (dragging ? 'cursor-grabbing' : 'cursor-grab') : 'cursor-zoom-in'
                                }`}
                                onPointerDown={onPointerDown}
                                onPointerMove={onPointerMove}
                                onPointerUp={onPointerUp}
                                onPointerCancel={onPointerUp}
                                onDoubleClick={toggleZoom}
                            >
                                {images[index] && (
                                    <img
                                        key={images[index]}
                                        src={images[index]}
                                        alt={alt}
                                        draggable={false}
                                        className="max-h-full max-w-full rounded-lg object-contain shadow-2xl"
                                        style={{
                                            transform: `translate(${offset.x}px, ${offset.y}px) scale(${scale})`,
                                            transition: dragging ? 'none' : 'transform 200ms ease-out',
                                        }}
                                    />
                                )}
                            </div>

                            {count > 1 && (
                                <>
                                    <IconButton onClick={() => go(-1)} label={__('Previous image')} className="absolute left-3 top-1/2 h-12 w-12 -translate-y-1/2 sm:left-5">
                                        <svg className="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2.4} d="M15 19l-7-7 7-7" /></svg>
                                    </IconButton>
                                    <IconButton onClick={() => go(1)} label={__('Next image')} className="absolute right-3 top-1/2 h-12 w-12 -translate-y-1/2 sm:right-5">
                                        <svg className="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2.4} d="M9 5l7 7-7 7" /></svg>
                                    </IconButton>
                                </>
                            )}
                        </div>

                        {/* Thumbnails */}
                        {count > 1 && (
                            <div className="no-scrollbar flex justify-start gap-2 overflow-x-auto px-3 py-3 sm:justify-center">
                                {images.map((src, i) => (
                                    <button
                                        key={`${src}-${i}`}
                                        type="button"
                                        onClick={() => onIndexChange(i)}
                                        aria-label={`${__('Image')} ${i + 1}`}
                                        className={`h-14 w-14 shrink-0 overflow-hidden rounded-xl transition sm:h-16 sm:w-16 ${
                                            i === index ? 'opacity-100 ring-2 ring-tb ring-offset-2 ring-offset-black' : 'opacity-50 hover:opacity-90'
                                        }`}
                                    >
                                        <img src={src} alt="" className="h-full w-full object-cover" draggable={false} />
                                    </button>
                                ))}
                            </div>
                        )}

                        <p className="pb-3 text-center text-[11px] text-white/50">
                            {__('Scroll or pinch to zoom · drag to pan · double-click to toggle zoom')}
                        </p>
                    </DialogPanel>
                </TransitionChild>
            </Dialog>
        </Transition>
    );
}
