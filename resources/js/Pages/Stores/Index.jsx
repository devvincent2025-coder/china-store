import { Head, Link } from '@inertiajs/react';
import StorefrontLayout from '@/Layouts/StorefrontLayout';
import { t } from '@/utils/translatable';
import { useTranslation } from '@/hooks/useTranslation';

export default function Index({ stores }) {
    const { __ } = useTranslation();
    const rows = stores?.data ?? stores ?? [];

    return (
        <StorefrontLayout>
            <Head title={__('Stores')} />

            <div className="mx-auto max-w-marketplace px-2 py-6 sm:px-3">
                <h1 className="font-display text-2xl font-bold text-tb-text">{__('Stores')}</h1>
                <p className="mt-1 text-sm text-tb-muted">{__('Browse verified Chinese-run stores on ChinaInNigeria.')}</p>

                {rows.length === 0 ? (
                    <p className="mt-10 text-center text-sm text-tb-muted">{__('No stores yet.')}</p>
                ) : (
                    <div className="mt-6 grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5">
                        {rows.map((store) => (
                            <Link
                                key={store.id}
                                href={route('vendors.show', store.slug)}
                                className="overflow-hidden rounded-[12px] bg-tb-card transition hover:shadow-lift"
                            >
                                <div className="business-entry-item-card !h-[120px]">
                                    {store.banner_url || store.logo_url ? (
                                        <img
                                            src={store.banner_url || store.logo_url}
                                            alt=""
                                            className="h-full w-full object-cover"
                                        />
                                    ) : null}
                                </div>
                                <div className="p-3">
                                    <div className="flex items-center gap-2">
                                        {store.logo_url && (
                                            <img src={store.logo_url} alt="" className="h-8 w-8 rounded-full object-cover" />
                                        )}
                                        <h2 className="font-display line-clamp-1 text-sm font-semibold text-tb-text">
                                            {t(store.name)}
                                        </h2>
                                    </div>
                                    {store.city && (
                                        <p className="mt-1 text-[11px] text-tb-muted">
                                            {store.city}
                                            {store.state ? `, ${store.state}` : ''}
                                        </p>
                                    )}
                                </div>
                            </Link>
                        ))}
                    </div>
                )}
            </div>
        </StorefrontLayout>
    );
}
