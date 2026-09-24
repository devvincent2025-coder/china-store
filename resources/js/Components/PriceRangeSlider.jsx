import { useCallback, useEffect, useId, useState } from 'react';
import { formatNaira } from '@/utils/money';

/**
 * Dual-thumb price range slider (Naira). Values are whole naira integers.
 * Uses stacked native range inputs — no extra dependency.
 */
export default function PriceRangeSlider({
    min = 0,
    max = 100000,
    valueMin,
    valueMax,
    onChange,
    step = 100,
}) {
    const id = useId();
    const floor = Math.floor(Number(min) || 0);
    const ceil = Math.max(floor + step, Math.ceil(Number(max) || floor + step));

    const [lo, setLo] = useState(() => clamp(Number(valueMin ?? floor), floor, ceil));
    const [hi, setHi] = useState(() => clamp(Number(valueMax ?? ceil), floor, ceil));

    useEffect(() => {
        setLo(clamp(Number(valueMin ?? floor), floor, ceil));
        setHi(clamp(Number(valueMax ?? ceil), floor, ceil));
    }, [valueMin, valueMax, floor, ceil]);

    const emit = useCallback((nextLo, nextHi) => {
        onChange?.({ min: nextLo, max: nextHi });
    }, [onChange]);

    const onLo = (e) => {
        const next = Math.min(Number(e.target.value), hi - step);
        setLo(next);
        emit(next, hi);
    };

    const onHi = (e) => {
        const next = Math.max(Number(e.target.value), lo + step);
        setHi(next);
        emit(lo, next);
    };

    const span = ceil - floor || 1;
    const leftPct = ((lo - floor) / span) * 100;
    const rightPct = ((hi - floor) / span) * 100;

    return (
        <div className="w-full">
            <div className="mb-3 flex items-center justify-between gap-2 font-ui text-[12px] text-tb-muted">
                <span className="rounded bg-tb-card px-2 py-1 font-semibold text-tb-text">
                    {formatNaira(lo * 100)}
                </span>
                <span>–</span>
                <span className="rounded bg-tb-card px-2 py-1 font-semibold text-tb-text">
                    {formatNaira(hi * 100)}
                </span>
            </div>

            <div className="relative h-8">
                <div className="absolute left-0 right-0 top-1/2 h-1.5 -translate-y-1/2 rounded-full bg-neutral-200" />
                <div
                    className="absolute top-1/2 h-1.5 -translate-y-1/2 rounded-full bg-tb"
                    style={{ left: `${leftPct}%`, right: `${100 - rightPct}%` }}
                />

                <label className="sr-only" htmlFor={`${id}-min`}>Minimum price</label>
                <input
                    id={`${id}-min`}
                    type="range"
                    min={floor}
                    max={ceil}
                    step={step}
                    value={lo}
                    onChange={onLo}
                    className="cin-range-thumb pointer-events-none absolute inset-0 z-20 w-full appearance-none bg-transparent"
                />

                <label className="sr-only" htmlFor={`${id}-max`}>Maximum price</label>
                <input
                    id={`${id}-max`}
                    type="range"
                    min={floor}
                    max={ceil}
                    step={step}
                    value={hi}
                    onChange={onHi}
                    className="cin-range-thumb pointer-events-none absolute inset-0 z-30 w-full appearance-none bg-transparent"
                />
            </div>
        </div>
    );
}

function clamp(n, min, max) {
    return Math.min(max, Math.max(min, Number.isFinite(n) ? n : min));
}
