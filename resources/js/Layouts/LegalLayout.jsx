import { Head, Link } from '@inertiajs/react';
import StorefrontLayout from '@/Layouts/StorefrontLayout';
import SeoHead from '@/Components/SeoHead';
import { useTranslation } from '@/hooks/useTranslation';

const PAGES = [
    { label: 'Terms & Conditions', route: 'legal.terms' },
    { label: 'Privacy Policy', route: 'legal.privacy' },
    { label: 'Return Policy', route: 'legal.return-policy' },
    { label: 'Customer Protection', route: 'legal.customer-protection' },
    { label: 'Vendor Protection', route: 'legal.vendor-protection' },
];

/**
 * Shared chrome for the five legal/policy pages — a side nav between them plus consistent prose
 * styling, so reading one naturally surfaces the others (e.g. Return Policy links out to Customer
 * Protection's escrow explanation rather than repeating it).
 */
export default function LegalLayout({ title, updatedAt, children, seo = null }) {
    const { __ } = useTranslation();

    return (
        <StorefrontLayout>
            {seo ? <SeoHead seo={seo} /> : <Head title={title} />}

            <div className="mx-auto max-w-5xl px-4 py-8 sm:px-6 lg:px-8">
                <div className="grid grid-cols-1 gap-8 lg:grid-cols-4">
                    <nav className="lg:col-span-1">
                        <p className="mb-2 text-xs font-semibold uppercase tracking-wide text-neutral-400">{__('Legal & Policies')}</p>
                        <ul className="space-y-1">
                            {PAGES.map((page) => (
                                <li key={page.route}>
                                    <Link
                                        href={route(page.route)}
                                        className={`block rounded-md px-3 py-2 text-sm ${
                                            route().current(page.route)
                                                ? 'bg-orange-50 font-semibold text-orange-600'
                                                : 'text-neutral-600 hover:bg-neutral-50 hover:text-orange-600'
                                        }`}
                                    >
                                        {__(page.label)}
                                    </Link>
                                </li>
                            ))}
                        </ul>
                    </nav>

                    <article className="lg:col-span-3">
                        <h1 className="mb-1 text-2xl font-bold text-neutral-900">{title}</h1>
                        {updatedAt && <p className="mb-6 text-xs text-neutral-400">{__('Last updated')}: {updatedAt}</p>}
                        <div className="max-w-none">{children}</div>
                    </article>
                </div>
            </div>
        </StorefrontLayout>
    );
}
