import { Head, Link } from '@inertiajs/react';
import StorefrontLayout from '@/Layouts/StorefrontLayout';

const MESSAGES = {
    403: {
        title: "You don't have access to this page",
        description: 'You may need to sign in with a different account, or this area may be restricted.',
    },
    404: {
        title: 'This page has wandered off',
        description: "The link you followed may be out of date, or the item may no longer be available.",
    },
    500: {
        title: 'Something went wrong on our end',
        description: "We've been notified and we're looking into it. Please try again in a moment.",
    },
    503: {
        title: "We'll be right back",
        description: "ChinaInNigeria is down for scheduled maintenance. Please check back shortly.",
    },
};

export default function Error({ status }) {
    const { title, description } = MESSAGES[status] ?? MESSAGES[404];

    return (
        <StorefrontLayout>
            <Head title={`${status} — ${title}`} />

            <div className="mx-auto flex max-w-marketplace flex-col items-center px-4 py-24 text-center">
                <img src="/images/branding/icon.svg" alt="" className="mb-6 h-20 w-20 opacity-80" />

                <p className="font-display text-6xl font-extrabold text-tb sm:text-7xl">{status}</p>

                <h1 className="mt-4 text-xl font-bold text-tb-text sm:text-2xl">{title}</h1>
                <p className="mt-2 max-w-md text-sm text-tb-muted">{description}</p>

                <div className="mt-8 flex flex-wrap items-center justify-center gap-3">
                    <Link
                        href={route('home')}
                        className="rounded-full bg-orange-600 px-6 py-2.5 text-sm font-semibold text-white hover:bg-orange-700"
                    >
                        Back to Home
                    </Link>
                    <Link
                        href={route('products.index')}
                        className="rounded-full border border-tb-border px-6 py-2.5 text-sm font-semibold text-tb-text hover:border-tb hover:text-tb"
                    >
                        Browse Products
                    </Link>
                </div>
            </div>
        </StorefrontLayout>
    );
}
