import { Head, Link, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import StorefrontLayout from '@/Layouts/StorefrontLayout';
import ProductCard from '@/Components/ProductCard';
import { useTranslation } from '@/hooks/useTranslation';

const STORAGE_KEY = 'cin_favourites';

export default function Index() {
    const { auth } = usePage().props;
    const { __ } = useTranslation();
    const [items, setItems] = useState([]);

    useEffect(() => {
        try {
            const raw = localStorage.getItem(STORAGE_KEY);
            setItems(raw ? JSON.parse(raw) : []);
        } catch {
            setItems([]);
        }
    }, []);

    const clearAll = () => {
        localStorage.removeItem(STORAGE_KEY);
        setItems([]);
    };

    return (
        <StorefrontLayout>
            <Head title={__('Favourites')} />

            <div className="mx-auto max-w-marketplace px-2 py-6 sm:px-3">
                <div className="flex items-end justify-between gap-3">
                    <div>
                        <h1 className="font-display text-2xl font-bold text-tb-text">{__('Favourites')}</h1>
                        <p className="mt-1 text-sm text-tb-muted">{__('Products you saved for later.')}</p>
                    </div>
                    {items.length > 0 && (
                        <button type="button" onClick={clearAll} className="text-sm text-tb-muted hover:text-tb">
                            {__('Clear all')}
                        </button>
                    )}
                </div>

                {!auth?.user && (
                    <div className="mt-4 rounded-[12px] bg-tb-card px-4 py-3 text-sm text-tb-muted">
                        <Link href={route('login')} className="font-semibold text-tb hover:underline">
                            {__('Log in')}
                        </Link>{' '}
                        to sync favourites across devices. Saved items on this device still show below.
                    </div>
                )}

                {items.length === 0 ? (
                    <div className="mt-16 text-center">
                        <p className="font-display text-lg font-semibold text-tb-text">{__('No favourites yet')}</p>
                        <p className="mt-1 text-sm text-tb-muted">Tap the heart on products you love to save them here.</p>
                        <Link
                            href={route('products.index')}
                            className="mt-6 inline-flex rounded-full bg-tb px-6 py-2.5 text-sm font-semibold text-white hover:bg-tb-hover"
                        >
                            {__('Browse products')}
                        </Link>
                    </div>
                ) : (
                    <div className="mt-6 grid grid-cols-2 gap-[10px] sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6">
                        {items.map((product) => (
                            <ProductCard key={product.id || product.slug} product={product} />
                        ))}
                    </div>
                )}
            </div>
        </StorefrontLayout>
    );
}
