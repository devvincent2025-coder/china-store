import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import VendorLayout from '@/Layouts/VendorLayout';
import StatusBadge from '@/Components/StatusBadge';
import EmptyState from '@/Components/EmptyState';
import Pagination from '@/Components/Pagination';
import ExportMenu from '@/Components/ExportMenu';
import { formatNaira } from '@/utils/money';
import { t } from '@/utils/translatable';
import { useTranslation } from '@/hooks/useTranslation';
import confirm from '@/utils/confirm';

export default function Index({ products, vendor, categories = [], filters = {} }) {
    const { __ } = useTranslation();
    const [selected, setSelected] = useState([]);
    const [bulkAction, setBulkAction] = useState('price_percentage');
    const [bulkValue, setBulkValue] = useState('');

    const [q, setQ] = useState(filters.q ?? '');
    const [status, setStatus] = useState(filters.status ?? '');
    const [category, setCategory] = useState(filters.category ?? '');
    const [lowStock, setLowStock] = useState(Boolean(filters.low_stock));
    const [sort, setSort] = useState(filters.sort ?? '');

    const applyFilters = (overrides = {}) => {
        const params = {
            q, status, category, sort,
            low_stock: lowStock ? 1 : undefined,
            ...overrides,
        };

        Object.keys(params).forEach((key) => {
            if (params[key] === '' || params[key] === undefined || params[key] === false) {
                delete params[key];
            }
        });

        router.get(route('vendor.products.index'), params, { preserveState: true, replace: true });
    };

    const submitSearch = (e) => {
        e.preventDefault();
        applyFilters();
    };

    const clearFilters = () => {
        setQ('');
        setStatus('');
        setCategory('');
        setLowStock(false);
        setSort('');
        router.get(route('vendor.products.index'), {}, { preserveState: true, replace: true });
    };

    const hasActiveFilters = Boolean(filters.q || filters.status || filters.category || filters.low_stock || filters.sort);

    const toggle = (id) => {
        setSelected((prev) => (prev.includes(id) ? prev.filter((x) => x !== id) : [...prev, id]));
    };

    const toggleAll = () => {
        setSelected(selected.length === products.data.length ? [] : products.data.map((p) => p.id));
    };

    const applyBulk = () => {
        if (selected.length === 0 || bulkValue === '') return;
        router.post(route('vendor.products.bulk-update'), {
            product_ids: selected,
            action: bulkAction,
            value: bulkValue,
        }, { onSuccess: () => { setSelected([]); setBulkValue(''); } });
    };

    const destroy = async (product) => {
        if (await confirm(__('Delete this product?'), { tone: 'danger' })) {
            router.delete(route('vendor.products.destroy', product.id));
        }
    };

    return (
        <VendorLayout vendor={vendor}>
            <Head title={__('My Products')} />

            <div className="mb-6 flex items-center justify-between">
                <h1 className="text-xl font-bold text-neutral-900">{__('Products')}</h1>
                <div className="flex items-center gap-2">
                    <ExportMenu routeName="vendor.exports.products" />
                    <Link
                        href={route('vendor.products.create')}
                        className="rounded-md bg-orange-600 px-4 py-2 text-sm font-semibold text-white hover:bg-orange-700"
                    >
                        + {__('Add Product')}
                    </Link>
                </div>
            </div>

            <form onSubmit={submitSearch} className="mb-4 flex flex-wrap items-center gap-2 rounded-lg border border-neutral-200 bg-white p-3">
                <input
                    type="text"
                    value={q}
                    onChange={(e) => setQ(e.target.value)}
                    placeholder="Search by title or SKU..."
                    className="w-full flex-1 rounded-md border-neutral-300 text-sm focus:border-orange-500 focus:ring-orange-500 sm:min-w-[200px] sm:w-auto"
                />
                <select
                    value={status}
                    onChange={(e) => { setStatus(e.target.value); applyFilters({ status: e.target.value }); }}
                    className="rounded-md border-neutral-300 text-sm"
                >
                    <option value="">All statuses</option>
                    <option value="draft">{__('Draft')}</option>
                    <option value="published">{__('Published')}</option>
                    <option value="archived">{__('Archived')}</option>
                </select>
                {categories.length > 0 && (
                    <select
                        value={category}
                        onChange={(e) => { setCategory(e.target.value); applyFilters({ category: e.target.value }); }}
                        className="rounded-md border-neutral-300 text-sm"
                    >
                        <option value="">All categories</option>
                        {categories.map((cat) => (
                            <option key={cat.id} value={cat.id}>{t(cat.name)}</option>
                        ))}
                    </select>
                )}
                <select
                    value={sort}
                    onChange={(e) => { setSort(e.target.value); applyFilters({ sort: e.target.value }); }}
                    className="rounded-md border-neutral-300 text-sm"
                >
                    <option value="">Newest first</option>
                    <option value="oldest">Oldest first</option>
                    <option value="price_asc">Price: low to high</option>
                    <option value="price_desc">Price: high to low</option>
                    <option value="stock_asc">Stock: low to high</option>
                    <option value="stock_desc">Stock: high to low</option>
                </select>
                <label className="flex items-center gap-1.5 text-sm text-neutral-600">
                    <input
                        type="checkbox"
                        checked={lowStock}
                        onChange={(e) => { setLowStock(e.target.checked); applyFilters({ low_stock: e.target.checked ? 1 : undefined }); }}
                        className="rounded border-neutral-300 text-orange-600 focus:ring-orange-500"
                    />
                    Low stock (≤5)
                </label>
                <button type="submit" className="rounded-md bg-neutral-800 px-4 py-2 text-sm font-semibold text-white hover:bg-neutral-900">
                    {__('Search')}
                </button>
                {hasActiveFilters && (
                    <button type="button" onClick={clearFilters} className="text-sm text-neutral-400 underline hover:text-neutral-600">
                        Clear filters
                    </button>
                )}
            </form>

            {selected.length > 0 && (
                <div className="mb-4 flex flex-wrap items-center gap-2 rounded-lg border border-orange-200 bg-orange-50 p-3">
                    <span className="text-sm text-orange-800">{selected.length} {__('selected')}</span>
                    <select
                        value={bulkAction}
                        onChange={(e) => setBulkAction(e.target.value)}
                        className="rounded-md border-neutral-300 text-sm"
                    >
                        <option value="price_percentage">{__('Adjust price by %')}</option>
                        <option value="set_stock">{__('Set stock to')}</option>
                    </select>
                    <input
                        type="number"
                        value={bulkValue}
                        onChange={(e) => setBulkValue(e.target.value)}
                        placeholder={__('Value')}
                        className="w-24 rounded-md border-neutral-300 text-sm"
                    />
                    <button onClick={applyBulk} className="rounded-md bg-orange-600 px-3 py-1.5 text-sm font-semibold text-white hover:bg-orange-700">
                        {__('Apply')}
                    </button>
                </div>
            )}

            {products.data.length === 0 ? (
                <EmptyState
                    title={hasActiveFilters ? __('No Products Found') : __('No products yet')}
                    description={hasActiveFilters ? 'Try adjusting or clearing your filters.' : __('Add your first product to start selling.')}
                    actionHref={hasActiveFilters ? undefined : route('vendor.products.create')}
                    actionLabel={hasActiveFilters ? undefined : __('Add Product')}
                />
            ) : (
                <>
                    {/* Mobile: card list — avoids the wide multi-column table forcing horizontal
                        scroll on a phone. Same data/actions as the table below, just stacked. */}
                    <div className="space-y-3 sm:hidden">
                        {products.data.map((product) => (
                            <div key={product.id} className="rounded-lg border border-neutral-200 bg-white p-3">
                                <div className="flex items-start gap-3">
                                    <input
                                        type="checkbox"
                                        checked={selected.includes(product.id)}
                                        onChange={() => toggle(product.id)}
                                        className="mt-1 shrink-0"
                                    />
                                    <div className="h-12 w-12 shrink-0 overflow-hidden rounded-md bg-neutral-100">
                                        {product.images?.[0] && (
                                            <img src={product.images[0].enhanced_url ?? product.images[0].original_url} alt="" className="h-full w-full object-cover" />
                                        )}
                                    </div>
                                    <div className="min-w-0 flex-1">
                                        <p className="line-clamp-2 text-sm text-neutral-800">{t(product.title)}</p>
                                        <div className="mt-1 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-neutral-500">
                                            <span className="font-semibold text-neutral-700">{formatNaira(product.price)}</span>
                                            <span>{__('Stock')}: {product.stock}</span>
                                            <StatusBadge status={product.status} />
                                        </div>
                                        <div className="mt-2 flex items-center gap-3 text-sm">
                                            <Link href={route('vendor.products.edit', product.id)} className="font-medium text-orange-600 hover:underline">
                                                {__('Edit')}
                                            </Link>
                                            <button onClick={() => destroy(product)} className="font-medium text-red-500 hover:underline">
                                                {__('Delete')}
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        ))}
                    </div>

                    {/* Desktop/tablet: table */}
                    <div className="hidden overflow-x-auto rounded-lg border border-neutral-200 bg-white sm:block">
                        <table className="w-full min-w-[640px] text-sm">
                            <thead className="bg-neutral-50 text-left text-xs uppercase text-neutral-500">
                                <tr>
                                    <th className="px-4 py-2">
                                        <input type="checkbox" checked={selected.length === products.data.length} onChange={toggleAll} />
                                    </th>
                                    <th className="px-4 py-2">{__('Product')}</th>
                                    <th className="px-4 py-2">{__('Price')}</th>
                                    <th className="px-4 py-2">{__('Stock')}</th>
                                    <th className="px-4 py-2">{__('Status')}</th>
                                    <th className="px-4 py-2" />
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-neutral-100">
                                {products.data.map((product) => (
                                    <tr key={product.id}>
                                        <td className="px-4 py-3">
                                            <input type="checkbox" checked={selected.includes(product.id)} onChange={() => toggle(product.id)} />
                                        </td>
                                        <td className="px-4 py-3">
                                            <div className="flex items-center gap-3">
                                                <div className="h-10 w-10 shrink-0 overflow-hidden rounded-md bg-neutral-100">
                                                    {product.images?.[0] && (
                                                        <img src={product.images[0].enhanced_url ?? product.images[0].original_url} alt="" className="h-full w-full object-cover" />
                                                    )}
                                                </div>
                                                <span className="line-clamp-1 text-neutral-800">{t(product.title)}</span>
                                            </div>
                                        </td>
                                        <td className="px-4 py-3 text-neutral-600">{formatNaira(product.price)}</td>
                                        <td className="px-4 py-3 text-neutral-600">{product.stock}</td>
                                        <td className="px-4 py-3"><StatusBadge status={product.status} /></td>
                                        <td className="px-4 py-3 text-right">
                                            <Link href={route('vendor.products.edit', product.id)} className="mr-3 font-medium text-orange-600 hover:underline">
                                                {__('Edit')}
                                            </Link>
                                            <button onClick={() => destroy(product)} className="font-medium text-red-500 hover:underline">
                                                {__('Delete')}
                                            </button>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                    <Pagination paginator={products} />
                </>
            )}
        </VendorLayout>
    );
}
