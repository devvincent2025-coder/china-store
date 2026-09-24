/**
 * Plain-Tailwind typography helpers for the legal/policy pages — this project has no
 * @tailwindcss/typography plugin installed, so these stand in for `prose` classes.
 */
export function Section({ title, children }) {
    return (
        <section className="mb-8">
            <h2 className="mb-2 text-lg font-semibold text-neutral-900">{title}</h2>
            <div className="space-y-3">{children}</div>
        </section>
    );
}

export function P({ children }) {
    return <p className="text-sm leading-relaxed text-neutral-700">{children}</p>;
}

export function Ul({ children }) {
    return <ul className="list-disc space-y-1.5 pl-5 text-sm leading-relaxed text-neutral-700">{children}</ul>;
}

export function Ol({ children }) {
    return <ol className="list-decimal space-y-1.5 pl-5 text-sm leading-relaxed text-neutral-700">{children}</ol>;
}
