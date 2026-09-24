import StorefrontLayout from '@/Layouts/StorefrontLayout';
import SeoHead from '@/Components/SeoHead';
import ProductCard from '@/Components/ProductCard';
import Pagination from '@/Components/Pagination';
import EmptyState from '@/Components/EmptyState';
import { useTranslation } from '@/hooks/useTranslation';

export default function Index({ products, categories, seo }) {
    const { __ } = useTranslation();

    return (
        <StorefrontLayout categories={categories} showCategoryBar>
            <SeoHead seo={seo} />

            <div className="mx-auto max-w-marketplace px-2 py-6 sm:px-3">
                <div className="mb-6">
                    <h1 className="font-display text-2xl font-bold text-tb-text">{__('Kids')}</h1>
                    <p className="mt-1 font-ui text-sm text-tb-muted">
                        {__('Toys, baby essentials, and kids finds from verified vendors.')}
                    </p>
                </div>

                {products.data.length === 0 ? (
                    <EmptyState title={__('No kids products yet')} description={__('Check back soon or browse other categories.')} />
                ) : (
                    <>
                        <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5">
                            {products.data.map((product) => (
                                <ProductCard key={product.id} product={product} />
                            ))}
                        </div>
                        <Pagination paginator={products} />
                    </>
                )}
            </div>
        </StorefrontLayout>
    );
}
