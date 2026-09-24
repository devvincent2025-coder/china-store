import { Link } from '@inertiajs/react';
import { useState } from 'react';
import StorefrontLayout from '@/Layouts/StorefrontLayout';
import SeoHead from '@/Components/SeoHead';
import ProductCard from '@/Components/ProductCard';
import Pagination from '@/Components/Pagination';
import EmptyState from '@/Components/EmptyState';
import ProductGridSkeleton from '@/Components/ProductGridSkeleton';
import TopPicksSection from '@/Components/TopPicksSection';
import { t } from '@/utils/translatable';
import { useTranslation } from '@/hooks/useTranslation';

export default function Show({ category, subcategories, products, topPicks = [], seo }) {
    const { __ } = useTranslation();
    const [loading, setLoading] = useState(false);

    return (
        <StorefrontLayout>
            <SeoHead seo={seo} />

            <div className="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
                <div className="mb-6 flex items-center gap-4 rounded-lg border border-neutral-200 bg-white p-5">
                    {category.image_url && (
                        <img src={category.image_url} alt={t(category.name)} className="h-16 w-16 rounded-full object-cover" />
                    )}
                    <h1 className="text-xl font-bold text-neutral-900 sm:text-2xl">{t(category.name)}</h1>
                </div>

                {subcategories.length > 0 && (
                    <div className="mb-6 flex flex-wrap gap-2">
                        {subcategories.map((sub) => (
                            <Link
                                key={sub.id}
                                href={route('categories.show', sub.slug)}
                                className="rounded-full border border-neutral-300 px-4 py-1.5 text-sm text-neutral-600 hover:border-orange-500 hover:text-orange-600"
                            >
                                {t(sub.name)}
                            </Link>
                        ))}
                    </div>
                )}

                <TopPicksSection topPicks={topPicks} />

                {loading ? (
                    <ProductGridSkeleton count={products.data.length || 12} />
                ) : products.data.length === 0 ? (
                    <EmptyState title={__('No products in this category yet')} description={__('Check back soon or browse other categories.')} />
                ) : (
                    <>
                        <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5">
                            {products.data.map((product) => (
                                <ProductCard key={product.id} product={product} />
                            ))}
                        </div>
                        <Pagination
                            paginator={products}
                            onStart={() => setLoading(true)}
                            onFinish={() => setLoading(false)}
                        />
                    </>
                )}
            </div>
        </StorefrontLayout>
    );
}
