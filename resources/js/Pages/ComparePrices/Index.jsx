import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import StorefrontLayout from '@/Layouts/StorefrontLayout';
import EmptyState from '@/Components/EmptyState';
import StarRating from '@/Components/StarRating';
import SearchSuggestionsDropdown from '@/Components/SearchSuggestionsDropdown';
import CompareGroupSkeleton from '@/Components/CompareGroupSkeleton';
import useSearchSuggestions from '@/hooks/useSearchSuggestions';
import { formatNaira } from '@/utils/money';
import { useTranslation } from '@/hooks/useTranslation';

function ListingRow({ listing, isLowest }) {
    const { __ } = useTranslation();

    return (
        <tr className={isLowest ? 'bg-green-50/60' : ''}>
            <td className="px-4 py-3">
                <div className="flex items-center gap-2">
                    <span className="font-medium text-neutral-800">{listing.vendor.business_name}</span>
                    {isLowest && (
                        <span className="rounded-full bg-green-100 px-2 py-0.5 text-[10px] font-semibold text-green-700">
                            {__('Best Price')}
                        </span>
                    )}
                </div>
                {listing.vendor.location && (
                    <p className="text-xs text-neutral-400">{listing.vendor.location}</p>
                )}
            </td>
            <td className="px-4 py-3">
                {listing.vendor.rating ? (
                    <StarRating rating={listing.vendor.rating} count={listing.vendor.reviews_count} />
                ) : (
                    <span className="text-xs text-neutral-400">{__('No reviews yet')}</span>
                )}
            </td>
            <td className="px-4 py-3">
                <span className={`text-sm font-semibold ${isLowest ? 'text-green-700' : 'text-neutral-800'}`}>
                    {formatNaira(listing.price)}
                </span>
                {listing.compare_price > listing.price && (
                    <span className="ml-1 text-xs text-neutral-400 line-through">{formatNaira(listing.compare_price)}</span>
                )}
            </td>
            <td className="px-4 py-3">
                {listing.in_stock ? (
                    <span className="text-xs font-medium text-green-600">{__('In Stock')}</span>
                ) : (
                    <span className="text-xs font-medium text-red-500">{__('Out of Stock')}</span>
                )}
            </td>
            <td className="px-4 py-3 text-right">
                <Link href={route('products.show', listing.product_slug)} className="text-sm font-semibold text-orange-600 hover:underline">
                    {__('View Product')}
                </Link>
            </td>
        </tr>
    );
}

function ProductGroup({ group }) {
    const { __ } = useTranslation();
    const lowestPrice = group.lowest_price;

    return (
        <div className="overflow-hidden rounded-lg border border-neutral-200 bg-white">
            <div className="flex items-center gap-4 border-b border-neutral-100 px-4 py-3">
                <div className="h-14 w-14 shrink-0 overflow-hidden rounded-md bg-neutral-100">
                    {group.image && <img src={group.image} alt="" className="h-full w-full object-cover" />}
                </div>
                <div className="min-w-0 flex-1">
                    <h2 className="truncate font-semibold text-neutral-900">{group.title}</h2>
                    <p className="text-xs text-neutral-500">
                        {group.seller_count > 1
                            ? __(':count sellers found', { count: group.seller_count })
                            : __('1 seller found')}
                        {group.category ? ` · ${group.category}` : ''}
                    </p>
                </div>
                {group.lowest_price !== group.highest_price && (
                    <div className="shrink-0 text-right text-xs text-neutral-500">
                        {__('Price range')}
                        <p className="font-semibold text-neutral-800">{formatNaira(group.lowest_price)} – {formatNaira(group.highest_price)}</p>
                    </div>
                )}
            </div>

            <table className="w-full text-sm">
                <thead className="bg-neutral-50 text-left text-xs uppercase text-neutral-500">
                    <tr>
                        <th className="px-4 py-2">{__('Seller')}</th>
                        <th className="px-4 py-2">{__('Rating')}</th>
                        <th className="px-4 py-2">{__('Price')}</th>
                        <th className="px-4 py-2">{__('Availability')}</th>
                        <th className="px-4 py-2" />
                    </tr>
                </thead>
                <tbody className="divide-y divide-neutral-100">
                    {group.listings.map((listing) => (
                        <ListingRow key={listing.product_id} listing={listing} isLowest={listing.price === lowestPrice} />
                    ))}
                </tbody>
            </table>
        </div>
    );
}

export default function Index({ query, groups = [] }) {
    const { __ } = useTranslation();
    const [q, setQ] = useState(query ?? '');
    const [suggestOpen, setSuggestOpen] = useState(false);
    const [loading, setLoading] = useState(false);
    const { suggestions, loading: suggestionsLoading } = useSearchSuggestions(q);

    const runSearch = (term) => {
        router.get(route('compare.index'), { q: term }, {
            preserveState: true,
            onStart: () => setLoading(true),
            onFinish: () => setLoading(false),
        });
    };

    const submit = (e) => {
        e.preventDefault();
        setSuggestOpen(false);
        runSearch(q);
    };

    const selectSuggestion = (item) => {
        setSuggestOpen(false);
        setQ(item.title);
        runSearch(item.title);
    };

    return (
        <StorefrontLayout>
            <Head title={__('Compare Prices')} />

            <div className="mx-auto max-w-5xl px-4 py-6 sm:px-6 lg:px-8">
                <h1 className="mb-2 text-xl font-bold text-neutral-900">{__('Compare Prices')}</h1>
                <p className="mb-6 text-sm text-neutral-500">
                    {__('Search a product to see every vendor selling it — compare price, rating, and location before you buy.')}
                </p>

                <form onSubmit={submit} className="relative mb-6 flex gap-2">
                    <input
                        type="text"
                        value={q}
                        onChange={(e) => setQ(e.target.value)}
                        onFocus={() => setSuggestOpen(true)}
                        onBlur={() => setSuggestOpen(false)}
                        placeholder={__('e.g. "Wireless Bluetooth Earbuds"')}
                        className="flex-1 rounded-md border-neutral-300 text-sm focus:border-orange-500 focus:ring-orange-500"
                        autoComplete="off"
                    />
                    <button type="submit" className="rounded-md bg-orange-600 px-5 py-2 text-sm font-semibold text-white hover:bg-orange-700">
                        {__('Search')}
                    </button>
                    <SearchSuggestionsDropdown
                        show={suggestOpen && q.trim().length >= 2}
                        suggestions={suggestions}
                        loading={suggestionsLoading}
                        onSelect={selectSuggestion}
                        className="inset-x-0 top-full"
                    />
                </form>

                {loading ? (
                    <CompareGroupSkeleton />
                ) : (
                    <>
                        {query && groups.length === 0 && (
                            <EmptyState
                                title={__('No matching products found')}
                                description={__('Try a shorter or more general search term.')}
                            />
                        )}

                        {!query && (
                            <EmptyState
                                title={__('Search for a product to compare')}
                                description={__('Enter a product name above to see it side by side across every vendor that sells it.')}
                            />
                        )}

                        <div className="space-y-6">
                            {groups.map((group) => (
                                <ProductGroup key={group.title} group={group} />
                            ))}
                        </div>
                    </>
                )}
            </div>
        </StorefrontLayout>
    );
}
