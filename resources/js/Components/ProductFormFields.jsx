import TranslatableLocaleField from '@/Components/TranslatableLocaleField';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import TextInput from '@/Components/TextInput';
import { t } from '@/utils/translatable';
import { useTranslation } from '@/hooks/useTranslation';

/**
 * Shared title/description/category/price/stock/status fields used by both
 * Vendor/Products/Create and Vendor/Products/Edit. Typing in one language
 * auto-fills the other two via vendor.translate.
 */
export default function ProductFormFields({ data, setData, errors, categories }) {
    const { __ } = useTranslation();

    return (
        <div className="space-y-6">
            <div className="rounded-lg border border-neutral-200 bg-white p-5">
                <h2 className="mb-4 text-sm font-semibold text-neutral-800">{__('Product Title')}</h2>
                <TranslatableLocaleField
                    idPrefix="title"
                    values={data.title}
                    onChange={(next) => setData('title', next)}
                    errors={errors}
                    errorKeyPrefix="title"
                    placeholders={{ zh: '商品标题', en: 'Product title', fr: 'Titre du produit' }}
                />
            </div>

            <div className="rounded-lg border border-neutral-200 bg-white p-5">
                <h2 className="mb-4 text-sm font-semibold text-neutral-800">{__('Description')}</h2>
                <TranslatableLocaleField
                    idPrefix="description"
                    richText
                    values={data.description}
                    onChange={(next) => setData('description', next)}
                    errors={errors}
                    errorKeyPrefix="description"
                    placeholders={{ zh: '商品描述', en: 'Product description', fr: 'Description du produit' }}
                />
            </div>

            <div className="rounded-lg border border-neutral-200 bg-white p-5">
                <h2 className="mb-4 text-sm font-semibold text-neutral-800">{__('Pricing & Inventory')}</h2>
                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <InputLabel htmlFor="category_id" value={__('Category')} />
                        <select
                            id="category_id"
                            value={data.category_id}
                            onChange={(e) => setData('category_id', e.target.value)}
                            className="mt-1 block w-full rounded-md border-neutral-300 shadow-sm focus:border-orange-500 focus:ring-orange-500"
                        >
                            <option value="">{__('Select category...')}</option>
                            {categories.map((cat) => (
                                <option key={cat.id} value={cat.id}>{t(cat.name)}</option>
                            ))}
                        </select>
                        <InputError message={errors.category_id} className="mt-1" />
                    </div>
                    <div>
                        <InputLabel htmlFor="sku" value={__('SKU (optional)')} />
                        <TextInput id="sku" className="mt-1 block w-full" value={data.sku ?? ''} onChange={(e) => setData('sku', e.target.value)} />
                        <InputError message={errors.sku} className="mt-1" />
                    </div>
                    <div>
                        <InputLabel htmlFor="price" value={__('Price (₦)')} />
                        <TextInput id="price" type="number" step="0.01" className="mt-1 block w-full" value={data.price} onChange={(e) => setData('price', e.target.value)} />
                        <InputError message={errors.price} className="mt-1" />
                    </div>
                    <div>
                        <InputLabel htmlFor="compare_price" value={__('Compare-at Price (₦, optional)')} />
                        <TextInput id="compare_price" type="number" step="0.01" className="mt-1 block w-full" value={data.compare_price ?? ''} onChange={(e) => setData('compare_price', e.target.value)} />
                        <InputError message={errors.compare_price} className="mt-1" />
                    </div>
                    <div>
                        <InputLabel htmlFor="stock" value={__('Stock')} />
                        <TextInput id="stock" type="number" className="mt-1 block w-full" value={data.stock} onChange={(e) => setData('stock', e.target.value)} />
                        <InputError message={errors.stock} className="mt-1" />
                    </div>
                    <div>
                        <InputLabel htmlFor="status" value={__('Status')} />
                        <select
                            id="status"
                            value={data.status}
                            onChange={(e) => setData('status', e.target.value)}
                            className="mt-1 block w-full rounded-md border-neutral-300 shadow-sm focus:border-orange-500 focus:ring-orange-500"
                        >
                            <option value="draft">{__('Draft')}</option>
                            <option value="published">{__('Published')}</option>
                            <option value="archived">{__('Archived')}</option>
                        </select>
                        <InputError message={errors.status} className="mt-1" />
                    </div>
                </div>
            </div>
        </div>
    );
}
