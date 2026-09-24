import { useRef } from 'react';

/**
 * Four separate digit boxes that behave like one field — used by both the email OTP page
 * (Auth/VerifyEmailOtp.jsx) and CompleteProfileModal's phone-OTP step. Auto-advances on input,
 * handles backspace-to-previous-box, and accepts a pasted full code in one box.
 */
export default function OtpInput({ value, onChange, length = 4, autoFocus = false, disabled = false }) {
    const inputRefs = useRef([]);
    const digits = value.padEnd(length, ' ').split('').slice(0, length);

    const setDigit = (index, digit) => {
        const next = value.split('');
        next[index] = digit;
        onChange(next.join('').slice(0, length).replace(/\s/g, ''));
    };

    const handleChange = (index, e) => {
        const raw = e.target.value.replace(/\D/g, '');

        if (raw.length > 1) {
            // A paste landed here — spread it across the remaining boxes from this one on.
            onChange((value.slice(0, index) + raw).slice(0, length));
            const nextIndex = Math.min(index + raw.length, length - 1);
            inputRefs.current[nextIndex]?.focus();
            return;
        }

        setDigit(index, raw);

        if (raw && index < length - 1) {
            inputRefs.current[index + 1]?.focus();
        }
    };

    const handleKeyDown = (index, e) => {
        if (e.key === 'Backspace' && !digits[index]?.trim() && index > 0) {
            inputRefs.current[index - 1]?.focus();
        }
    };

    return (
        <div className="flex justify-center gap-3">
            {Array.from({ length }).map((_, index) => (
                <input
                    key={index}
                    ref={(el) => { inputRefs.current[index] = el; }}
                    type="text"
                    inputMode="numeric"
                    autoComplete={index === 0 ? 'one-time-code' : 'off'}
                    autoFocus={autoFocus && index === 0}
                    disabled={disabled}
                    maxLength={length}
                    value={digits[index]?.trim() ?? ''}
                    onChange={(e) => handleChange(index, e)}
                    onKeyDown={(e) => handleKeyDown(index, e)}
                    className="h-14 w-12 rounded-lg border-0 bg-neutral-100 text-center text-2xl font-bold text-gray-900 focus:bg-white focus:ring-2 focus:ring-orange-500 disabled:opacity-50"
                />
            ))}
        </div>
    );
}
