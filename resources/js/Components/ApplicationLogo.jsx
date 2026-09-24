/**
 * The full brand lockup (truck-through-gate icon + "ChinaInNigeria" wordmark baked into one SVG —
 * see /logo/01b-truck-through-gate-lockup.svg in the repo root for the source file, served here
 * from /images/branding/lockup.svg). Used wherever both the icon and the app name need to show
 * together, e.g. the guest/authenticated layout headers.
 *
 * The source SVG is full-color (not a single currentColor fill), so `className` here should only
 * carry sizing/spacing utilities (h-*, w-*) — fill/text-color utilities passed in by older callers
 * are harmless no-ops against an <img>.
 */
export default function ApplicationLogo({ className, ...props }) {
    return (
        <img
            src="/images/branding/lockup.svg"
            alt="ChinaInNigeria"
            className={className}
            {...props}
        />
    );
}
