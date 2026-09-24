import { forwardRef, useState } from 'react';
import TextInput from '@/Components/TextInput';
import EyeToggleButton from '@/Components/EyeToggleButton';

/**
 * Drop-in replacement for `<TextInput type="password">` — adds a show/hide toggle at the suffix
 * and bumps the font up to text-base (TextInput's own default, inherited from plain form-input
 * styles, rendered too small to comfortably read/type a password on mobile).
 */
export default forwardRef(function PasswordInput({ className = '', ...props }, ref) {
    const [visible, setVisible] = useState(false);

    return (
        // Layout classes callers pass (width/margin/display) go on THIS wrapper, not the inner
        // input — otherwise something like `w-3/4` would only shrink the input while the toggle
        // button (anchored `right-0` to whichever element gets the width) stayed pinned to the
        // full-width wrapper's edge, floating disconnected from the input it's supposed to sit in.
        <div className={`relative ${className}`}>
            <TextInput
                {...props}
                ref={ref}
                type={visible ? 'text' : 'password'}
                className="w-full pr-11 text-base"
            />
            <EyeToggleButton visible={visible} onToggle={() => setVisible((v) => !v)} />
        </div>
    );
});
