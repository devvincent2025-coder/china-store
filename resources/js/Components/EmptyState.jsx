import { Link } from '@inertiajs/react';

export default function EmptyState({ icon = null, title, description = null, actionHref = null, actionLabel = null }) {
    return (
        <div className="flex flex-col items-center justify-center rounded-lg border border-dashed border-neutral-300 bg-neutral-50 px-6 py-16 text-center">
            <div className="mb-4 text-neutral-300">
                {icon ?? (
                    <svg className="h-16 w-16" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z" />
                    </svg>
                )}
            </div>
            <h3 className="text-lg font-semibold text-neutral-800">{title}</h3>
            {description && <p className="mt-1 max-w-sm text-sm text-neutral-500">{description}</p>}
            {actionHref && (
                <Link
                    href={actionHref}
                    className="mt-6 inline-flex items-center rounded-md bg-orange-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-orange-700"
                >
                    {actionLabel}
                </Link>
            )}
        </div>
    );
}
