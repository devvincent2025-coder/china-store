import { useEffect, useRef, useState } from 'react';
import { PHONE_COUNTRIES, formatIntlPhone } from '@/utils/phoneCountries';

/**
 * Phone field with flag + dial-code country picker. Emits a full international number
 * (e.g. +2348012345678) via onChange so the parent can store it as the login identifier.
 */
export default function PhoneInput({
    id = 'phone',
    value = '',
    onChange,
    defaultIso = 'NG',
    placeholder = '801 234 5678',
    autoFocus = false,
    className = '',
}) {
    const [open, setOpen] = useState(false);
    const [country, setCountry] = useState(
        () => PHONE_COUNTRIES.find((c) => c.iso === defaultIso) ?? PHONE_COUNTRIES[0],
    );
    const [national, setNational] = useState('');
    const rootRef = useRef(null);

    useEffect(() => {
        const onDoc = (e) => {
            if (!rootRef.current?.contains(e.target)) setOpen(false);
        };
        document.addEventListener('mousedown', onDoc);
        return () => document.removeEventListener('mousedown', onDoc);
    }, []);

    // Keep parent value in sync when country or national digits change.
    const emit = (nextCountry, nextNational) => {
        const full = formatIntlPhone(nextCountry.dial, nextNational);
        onChange?.(full);
    };

    // If parent clears value (e.g. switching login tabs), clear the national field.
    useEffect(() => {
        if (!value) setNational('');
    }, [value]);

    return (
        <div ref={rootRef} className={`relative flex overflow-hidden rounded-lg bg-neutral-100 focus-within:bg-white focus-within:ring-2 focus-within:ring-orange-500 ${className}`}>
            <button
                type="button"
                onClick={() => setOpen((o) => !o)}
                className="flex shrink-0 items-center gap-1.5 border-r border-neutral-200 bg-transparent px-3 py-3 text-sm text-gray-800 hover:bg-neutral-50"
                aria-label={`Country: ${country.name}`}
                aria-expanded={open}
            >
                <span className="text-base leading-none" aria-hidden>{country.flag}</span>
                <span className="font-medium tabular-nums">+{country.dial}</span>
                <svg className="h-3.5 w-3.5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden>
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 9l-7 7-7-7" />
                </svg>
            </button>

            <input
                id={id}
                type="tel"
                inputMode="tel"
                autoComplete="tel-national"
                autoFocus={autoFocus}
                placeholder={placeholder}
                value={national}
                onChange={(e) => {
                    const next = e.target.value.replace(/[^\d\s-]/g, '');
                    setNational(next);
                    emit(country, next);
                }}
                className="min-w-0 flex-1 border-0 bg-transparent px-3 py-3 text-sm text-gray-900 placeholder:text-gray-400 focus:ring-0"
            />

            {open && (
                <ul
                    className="absolute left-0 top-full z-30 mt-1 max-h-56 w-full overflow-auto rounded-lg border border-neutral-200 bg-white py-1 shadow-lift"
                    role="listbox"
                >
                    {PHONE_COUNTRIES.map((c) => (
                        <li key={c.iso}>
                            <button
                                type="button"
                                role="option"
                                aria-selected={c.iso === country.iso}
                                onClick={() => {
                                    setCountry(c);
                                    setOpen(false);
                                    emit(c, national);
                                }}
                                className={`flex w-full items-center gap-2 px-3 py-2 text-left text-sm hover:bg-orange-50 ${
                                    c.iso === country.iso ? 'bg-orange-50 text-orange-700' : 'text-gray-800'
                                }`}
                            >
                                <span className="text-base leading-none" aria-hidden>{c.flag}</span>
                                <span className="flex-1 truncate">{c.name}</span>
                                <span className="tabular-nums text-gray-500">+{c.dial}</span>
                            </button>
                        </li>
                    ))}
                </ul>
            )}
        </div>
    );
}
