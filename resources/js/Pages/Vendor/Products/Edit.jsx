import { Head, router, useForm } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import VendorLayout from '@/Layouts/VendorLayout';
import ProductFormFields from '@/Components/ProductFormFields';
import TranslatableLocaleField from '@/Components/TranslatableLocaleField';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import TextInput from '@/Components/TextInput';
import Modal from '@/Components/Modal';
import DangerButton from '@/Components/DangerButton';
import SecondaryButton from '@/Components/SecondaryButton';
import CameraCaptureModal from '@/Components/CameraCaptureModal';
import EstimatedEarnings from '@/Components/EstimatedEarnings';
import TierPricingEditor from '@/Components/TierPricingEditor';
import { koboToNaira, formatNaira } from '@/utils/money';
import { t } from '@/utils/translatable';
import { useTranslation } from '@/hooks/useTranslation';

const MINIMUM_PRODUCT_PHOTOS = 3;
const MAXIMUM_PRODUCT_PHOTOS = 5;

function StagedThumbnail({ file, onRemove }) {
    // Each staged File gets its own local blob URL for a preview — created once per file and
    // revoked on unmount/removal so we don't leak memory as vendors add/remove several shots.
    const [previewUrl] = useState(() => URL.createObjectURL(file));

    useEffect(() => () => URL.revokeObjectURL(previewUrl), [previewUrl]);

    return (
        <div className="relative overflow-hidden rounded-md border border-neutral-200">
            <img src={previewUrl} alt="" className="aspect-square w-full object-cover" />
            <button
                type="button"
                onClick={onRemove}
                aria-label="Remove"
                className="absolute right-1 top-1 flex h-6 w-6 items-center justify-center rounded-full bg-black/60 text-white hover:bg-black/80"
            >
                <svg className="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2.5} d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>
    );
}

function ImageManager({ product }) {
    const { __ } = useTranslation();
    const { data, setData, post, processing, errors, reset } = useForm({ images: [] });
    const [imageToDelete, setImageToDelete] = useState(null);
    const [deleting, setDeleting] = useState(false);
    const [isDragging, setIsDragging] = useState(false);
    const [cameraOpen, setCameraOpen] = useState(false);

    const existingCount = product.images?.length ?? 0;
    const totalCount = existingCount + data.images.length;
    const meetsMinimum = totalCount >= MINIMUM_PRODUCT_PHOTOS;
    const remainingSlots = Math.max(0, MAXIMUM_PRODUCT_PHOTOS - totalCount);
    const atMaximum = remainingSlots === 0;

    const addFiles = (fileList) => {
        const incoming = Array.from(fileList).filter((file) => file.type.startsWith('image/'));
        if (incoming.length === 0) return;

        if (incoming.length > remainingSlots) {
            alert(__('You can have at most :max photos per product — only :remaining more can be added.', {
                max: MAXIMUM_PRODUCT_PHOTOS,
                remaining: remainingSlots,
            }));
        }

        setData('images', [...data.images, ...incoming.slice(0, remainingSlots)]);
    };

    const removeStagedFile = (index) => {
        setData('images', data.images.filter((_, i) => i !== index));
    };

    const handleDrop = (e) => {
        e.preventDefault();
        setIsDragging(false);
        addFiles(e.dataTransfer.files);
    };

    const uploadImages = (e) => {
        e.preventDefault();
        post(route('vendor.products.images.store', product.id), {
            forceFormData: true,
            onSuccess: () => reset('images'),
        });
    };

    const setPrimary = (image) => {
        router.patch(route('vendor.products.images.primary', { product: product.id, image: image.id }));
    };

    const confirmDelete = () => {
        if (!imageToDelete) return;
        setDeleting(true);
        router.delete(route('vendor.products.images.destroy', { product: product.id, image: imageToDelete.id }), {
            preserveScroll: true,
            onFinish: () => {
                setDeleting(false);
                setImageToDelete(null);
            },
        });
    };

    return (
        <div className="rounded-lg border-neutral-200 bg-white p-5">
            <div className="mb-1 flex items-center justify-between gap-3">
                <h2 className="text-sm font-semibold text-neutral-800">{__('Photos')}</h2>
                <span className={`text-xs font-semibold ${meetsMinimum ? 'text-green-600' : 'text-amber-600'}`}>
                    {totalCount} / {MAXIMUM_PRODUCT_PHOTOS} {__('photos')}
                </span>
            </div>
            <p className="mb-4 text-xs text-neutral-500">
                {__('Every product needs a minimum of :min photos and a maximum of :max.', {
                    min: MINIMUM_PRODUCT_PHOTOS,
                    max: MAXIMUM_PRODUCT_PHOTOS,
                })}
            </p>
            {!meetsMinimum && (
                <p className="mb-4 rounded-md bg-amber-50 px-3 py-2 text-xs text-amber-700">
                    {__('Add at least :count photos so buyers can see this product from different angles.', { count: MINIMUM_PRODUCT_PHOTOS })}
                </p>
            )}
            {atMaximum && (
                <p className="mb-4 rounded-md bg-neutral-50 px-3 py-2 text-xs text-neutral-500">
                    {__('You\'ve reached the maximum of :max photos — remove one to add another.', { max: MAXIMUM_PRODUCT_PHOTOS })}
                </p>
            )}

            {product.images?.length > 0 ? (
                <div className="mb-4 grid grid-cols-3 gap-3 sm:grid-cols-4">
                    {product.images.map((image) => (
                        <div key={image.id} className="relative overflow-hidden rounded-md border border-neutral-200">
                            <img src={image.enhanced_url ?? image.original_url} alt="" className="aspect-square w-full object-cover" />
                            {image.is_primary && (
                                <span className="absolute left-1 top-1 rounded bg-orange-600 px-1.5 py-0.5 text-[10px] font-semibold text-white">
                                    {__('Primary')}
                                </span>
                            )}
                            <div className="flex divide-x divide-neutral-200 border-t border-neutral-200 bg-white text-[11px]">
                                {!image.is_primary && (
                                    <button type="button" onClick={() => setPrimary(image)} className="flex-1 py-1 text-orange-600 hover:bg-orange-50">
                                        {__('Set primary')}
                                    </button>
                                )}
                                <button type="button" onClick={() => setImageToDelete(image)} className="flex-1 py-1 text-red-500 hover:bg-red-50">
                                    {__('Delete')}
                                </button>
                            </div>
                        </div>
                    ))}
                </div>
            ) : (
                <p className="mb-4 text-sm text-neutral-400">{__('No photos uploaded yet.')}</p>
            )}

            <form onSubmit={uploadImages}>
                {!atMaximum && (
                    <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <label
                            htmlFor="image-dropzone-input"
                            onDragOver={(e) => { e.preventDefault(); setIsDragging(true); }}
                            onDragLeave={() => setIsDragging(false)}
                            onDrop={handleDrop}
                            className={`flex cursor-pointer flex-col items-center justify-center rounded-lg border-2 border-dashed px-4 py-8 text-center transition ${
                                isDragging ? 'border-orange-500 bg-orange-50' : 'border-neutral-300 bg-neutral-50 hover:border-orange-300 hover:bg-orange-50/50'
                            }`}
                        >
                            <svg className="mb-2 h-8 w-8 text-neutral-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
                            </svg>
                            <p className="text-sm font-medium text-neutral-700">
                                {isDragging ? __('Drop photos here') : __('Drag and drop photos here, or click to browse')}
                            </p>
                            <p className="mt-1 text-xs text-neutral-400">
                                {__(':count more allowed — PNG, JPG up to a few MB each', { count: remainingSlots })}
                            </p>
                            <input
                                id="image-dropzone-input"
                                type="file"
                                multiple
                                accept="image/*"
                                onChange={(e) => addFiles(e.target.files)}
                                className="hidden"
                            />
                        </label>

                        <button
                            type="button"
                            onClick={() => setCameraOpen(true)}
                            className="flex flex-col items-center justify-center gap-2 rounded-lg border-2 border-dashed border-neutral-300 px-4 py-8 text-sm font-semibold text-neutral-700 hover:border-orange-300 hover:bg-orange-50/50"
                        >
                            <svg className="h-8 w-8 text-neutral-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M3 7a2 2 0 012-2h3l2-2h4l2 2h3a2 2 0 012 2v10a2 2 0 01-2 2H5a2 2 0 01-2-2V7z" />
                                <circle cx="12" cy="13" r="3.5" />
                            </svg>
                            {__('Take Photo')}
                        </button>
                    </div>
                )}

                {data.images.length > 0 && (
                    <div className="mt-3 grid grid-cols-3 gap-3 sm:grid-cols-4">
                        {data.images.map((file, index) => (
                            <StagedThumbnail key={`${file.name}-${index}`} file={file} onRemove={() => removeStagedFile(index)} />
                        ))}
                    </div>
                )}

                <div className="mt-3 flex items-center gap-3">
                    <button
                        type="submit"
                        disabled={processing || data.images.length === 0}
                        className="rounded-md bg-orange-600 px-4 py-2 text-sm font-semibold text-white hover:bg-orange-700 disabled:opacity-50"
                    >
                        {processing ? __('Uploading…') : __('Upload')}
                    </button>
                    <InputError message={errors.images} />
                </div>
            </form>

            <CameraCaptureModal
                show={cameraOpen}
                onClose={() => setCameraOpen(false)}
                onCapture={(file) => addFiles([file])}
            />

            <Modal show={imageToDelete !== null} onClose={() => !deleting && setImageToDelete(null)} maxWidth="sm">
                <div className="p-6">
                    <h2 className="text-lg font-medium text-neutral-900">{__('Delete this photo?')}</h2>
                    <p className="mt-2 text-sm text-neutral-600">
                        {__('This photo will be permanently removed from the product. This cannot be undone.')}
                    </p>
                    {imageToDelete && (
                        <img
                            src={imageToDelete.enhanced_url ?? imageToDelete.original_url}
                            alt=""
                            className="mt-4 mx-auto aspect-square w-28 rounded-md object-cover border border-neutral-200"
                        />
                    )}
                    <div className="mt-6 flex justify-end gap-3">
                        <SecondaryButton type="button" onClick={() => setImageToDelete(null)} disabled={deleting}>
                            {__('Cancel')}
                        </SecondaryButton>
                        <DangerButton type="button" onClick={confirmDelete} disabled={deleting}>
                            {deleting ? __('Deleting…') : __('Delete photo')}
                        </DangerButton>
                    </div>
                </div>
            </Modal>
        </div>
    );
}

function blankOption() {
    return {
        name: { zh: '', en: '', fr: '' },
        values: [{ zh: '', en: '', fr: '' }],
    };
}

function optionsFromProduct(product) {
    if (!product.options || product.options.length === 0) {
        return [blankOption()];
    }

    return product.options.map((option) => ({
        name: { zh: option.name?.zh ?? '', en: option.name?.en ?? '', fr: option.name?.fr ?? '' },
        values: option.values.map((v) => ({ zh: v.value?.zh ?? '', en: v.value?.en ?? '', fr: v.value?.fr ?? '' })),
    }));
}

function OptionsEditor({ product }) {
    const { __ } = useTranslation();
    const [options, setOptions] = useState(optionsFromProduct(product));
    const [errors, setErrors] = useState({});
    const [processing, setProcessing] = useState(false);

    const updateOptionName = (optIndex, nextName) => {
        setOptions((prev) => prev.map((opt, i) => (i === optIndex ? { ...opt, name: nextName } : opt)));
    };

    const updateValue = (optIndex, valIndex, nextValue) => {
        setOptions((prev) =>
            prev.map((opt, i) => {
                if (i !== optIndex) return opt;
                return {
                    ...opt,
                    values: opt.values.map((v, j) => (j === valIndex ? nextValue : v)),
                };
            })
        );
    };

    const addOption = () => setOptions((prev) => [...prev, blankOption()]);

    const removeOption = (optIndex) => setOptions((prev) => prev.filter((_, i) => i !== optIndex));

    const addValue = (optIndex) => {
        setOptions((prev) =>
            prev.map((opt, i) => (i === optIndex ? { ...opt, values: [...opt.values, { zh: '', en: '', fr: '' }] } : opt))
        );
    };

    const removeValue = (optIndex, valIndex) => {
        setOptions((prev) =>
            prev.map((opt, i) => (i === optIndex ? { ...opt, values: opt.values.filter((_, j) => j !== valIndex) } : opt))
        );
    };

    const hasBlockingIssue = options.some((opt) => !opt.name.en?.trim() || opt.values.length === 0 || opt.values.some((v) => !v.en?.trim()));

    const submit = () => {
        setProcessing(true);
        router.post(
            route('vendor.products.options.sync', product.id),
            { options },
            {
                preserveScroll: true,
                onError: (errs) => setErrors(errs),
                onSuccess: () => setErrors({}),
                onFinish: () => setProcessing(false),
            }
        );
    };

    return (
        <div className="rounded-lg border border-neutral-200 bg-white p-5">
            <h2 className="mb-1 text-sm font-semibold text-neutral-800">{__('Options & Variants')}</h2>
            <p className="mb-4 text-sm text-neutral-500">
                {__('Sell this product in different packaging (piece, pack, carton, bale) or with different colors/variations — each combination gets its own price and stock.')}
            </p>

            <div className="space-y-5">
                {options.map((option, optIndex) => (
                    <div key={optIndex} className="rounded-md border border-neutral-200 p-4">
                        <div className="mb-3 flex items-start justify-between gap-3">
                            <div className="min-w-0 flex-1">
                                <p className="mb-2 text-xs font-semibold uppercase text-neutral-500">{__('Option name')}</p>
                                <TranslatableLocaleField
                                    compact
                                    idPrefix={`option_${optIndex}_name`}
                                    values={option.name}
                                    onChange={(next) => updateOptionName(optIndex, next)}
                                    errors={errors}
                                    errorKeyPrefix={`options.${optIndex}.name`}
                                    placeholders={{ zh: '包装', en: __('Packaging'), fr: 'Emballage' }}
                                />
                            </div>
                            <button
                                type="button"
                                onClick={() => removeOption(optIndex)}
                                className="mt-6 shrink-0 text-sm font-medium text-red-500 hover:underline"
                            >
                                {__('Remove option')}
                            </button>
                        </div>

                        <div className="space-y-3 pl-1">
                            <p className="text-xs font-semibold uppercase text-neutral-500">{__('Values')}</p>
                            {option.values.map((value, valIndex) => (
                                <div key={valIndex} className="flex flex-col gap-2 sm:flex-row sm:items-start">
                                    <div className="min-w-0 flex-1">
                                        <TranslatableLocaleField
                                            compact
                                            idPrefix={`option_${optIndex}_value_${valIndex}`}
                                            values={value}
                                            onChange={(next) => updateValue(optIndex, valIndex, next)}
                                            errors={errors}
                                            errorKeyPrefix={`options.${optIndex}.values.${valIndex}`}
                                            placeholders={{ zh: '中文', en: 'English', fr: 'Français' }}
                                        />
                                    </div>
                                    <button
                                        type="button"
                                        onClick={() => removeValue(optIndex, valIndex)}
                                        className="shrink-0 text-sm font-medium text-red-500 hover:underline sm:mt-10"
                                    >
                                        {__('Remove')}
                                    </button>
                                </div>
                            ))}
                            <button
                                type="button"
                                onClick={() => addValue(optIndex)}
                                className="text-sm font-medium text-orange-600 hover:underline"
                            >
                                {__('+ Add Value')}
                            </button>
                        </div>
                    </div>
                ))}
            </div>

            <div className="mt-4 flex flex-wrap items-center gap-3">
                <button type="button" onClick={addOption} className="rounded-md border border-neutral-300 px-4 py-2 text-sm font-semibold text-neutral-700 hover:bg-neutral-50">
                    {__('+ Add Option')}
                </button>
                <button
                    type="button"
                    onClick={submit}
                    disabled={processing || hasBlockingIssue}
                    className="rounded-md bg-orange-600 px-4 py-2 text-sm font-semibold text-white hover:bg-orange-700 disabled:opacity-50"
                >
                    {__('Save Options & Generate Variants')}
                </button>
                {hasBlockingIssue && (
                    <span className="text-xs text-neutral-500">
                        {__('Each option needs an English name and at least one value with an English label.')}
                    </span>
                )}
            </div>
        </div>
    );
}

function VariantTiersRow({ product, variant }) {
    const { __ } = useTranslation();
    const [tiers, setTiers] = useState(() => tiersFromServer(variant.price_tiers));
    const [processing, setProcessing] = useState(false);
    const [errors, setErrors] = useState({});

    const save = () => {
        setProcessing(true);
        router.post(
            route('vendor.products.variants.tiers.sync', [product.id, variant.id]),
            { price_tiers: tiers },
            {
                preserveScroll: true,
                onError: (errs) => setErrors(errs),
                onSuccess: () => setErrors({}),
                onFinish: () => setProcessing(false),
            }
        );
    };

    return (
        <tr>
            <td colSpan={5} className="bg-neutral-50 px-4 py-4">
                <TierPricingEditor tiers={tiers} onChange={setTiers} errors={errors} />
                <button
                    type="button"
                    onClick={save}
                    disabled={processing}
                    className="mt-3 rounded-md bg-neutral-800 px-3 py-1.5 text-sm font-semibold text-white hover:bg-neutral-900 disabled:opacity-50"
                >
                    {__('Save Tier Pricing')}
                </button>
            </td>
        </tr>
    );
}

function VariantRow({ product, variant, value, onChange }) {
    const { __ } = useTranslation();
    const [processing, setProcessing] = useState(false);
    const [errors, setErrors] = useState({});
    const [tiersOpen, setTiersOpen] = useState(false);

    // Eloquent serializes relation keys as snake_case regardless of the PHP method's camelCase
    // name, so the loaded `optionValues` relation arrives as `option_values`.
    const label = variant.option_values?.map((ov) => t(ov.value)).join(' / ') ?? '';
    const tierCount = variant.price_tiers?.length ?? 0;

    const save = () => {
        setProcessing(true);
        router.patch(
            route('vendor.products.variants.update', [product.id, variant.id]),
            { sku: value.sku === '' ? null : value.sku, price: value.price === '' ? null : Number(value.price), stock: Number(value.stock) },
            {
                preserveScroll: true,
                onError: (errs) => setErrors(errs),
                onSuccess: () => setErrors({}),
                onFinish: () => setProcessing(false),
            }
        );
    };

    return (
        <>
            <tr>
                <td className="px-4 py-3 text-neutral-800">{label}</td>
                <td className="px-4 py-3">
                    <TextInput value={value.sku} onChange={(e) => onChange({ ...value, sku: e.target.value })} className="w-32" />
                    <InputError message={errors.sku} className="mt-1" />
                </td>
                <td className="px-4 py-3">
                    <TextInput
                        type="number"
                        step="0.01"
                        value={value.price}
                        onChange={(e) => onChange({ ...value, price: e.target.value })}
                        placeholder={`${__('Inherit base price')} (${formatNaira(product.price)})`}
                        className="w-40"
                    />
                    <InputError message={errors.price} className="mt-1" />
                </td>
                <td className="px-4 py-3">
                    <TextInput type="number" value={value.stock} onChange={(e) => onChange({ ...value, stock: e.target.value })} className="w-24" />
                    <InputError message={errors.stock} className="mt-1" />
                </td>
                <td className="px-4 py-3 text-right">
                    <div className="flex items-center justify-end gap-2">
                        <button
                            type="button"
                            onClick={() => setTiersOpen((open) => !open)}
                            className="rounded-md border border-neutral-300 px-3 py-1.5 text-sm font-semibold text-neutral-700 hover:bg-neutral-50"
                        >
                            {__('Tiers')} {tierCount > 0 && `(${tierCount})`}
                        </button>
                        <button
                            type="button"
                            onClick={save}
                            disabled={processing}
                            className="rounded-md bg-orange-600 px-3 py-1.5 text-sm font-semibold text-white hover:bg-orange-700 disabled:opacity-50"
                        >
                            {__('Save')}
                        </button>
                    </div>
                </td>
            </tr>
            {tiersOpen && <VariantTiersRow product={product} variant={variant} />}
        </>
    );
}

function initialVariantValues(variants) {
    const values = {};
    (variants ?? []).forEach((variant) => {
        values[variant.id] = {
            sku: variant.sku ?? '',
            price: variant.price != null ? koboToNaira(variant.price) : '',
            stock: variant.stock ?? 0,
        };
    });
    return values;
}

function VariantsTable({ product }) {
    const { __ } = useTranslation();
    const [values, setValues] = useState(() => initialVariantValues(product.variants));
    const [savingAll, setSavingAll] = useState(false);
    const [bulkErrors, setBulkErrors] = useState({});

    if (!product.variants || product.variants.length === 0) return null;

    const updateRow = (variantId, next) => {
        setValues((prev) => ({ ...prev, [variantId]: next }));
    };

    const saveAll = () => {
        setSavingAll(true);
        router.patch(
            route('vendor.products.variants.bulk-update', product.id),
            {
                variants: product.variants.map((variant) => {
                    const row = values[variant.id];
                    return {
                        id: variant.id,
                        sku: row.sku === '' ? null : row.sku,
                        price: row.price === '' ? null : Number(row.price),
                        stock: Number(row.stock),
                    };
                }),
            },
            {
                preserveScroll: true,
                onError: (errs) => setBulkErrors(errs),
                onSuccess: () => setBulkErrors({}),
                onFinish: () => setSavingAll(false),
            }
        );
    };

    return (
        <div className="rounded-lg border border-neutral-200 bg-white p-5">
            <div className="mb-4 flex flex-wrap items-center justify-between gap-3">
                <h2 className="text-sm font-semibold text-neutral-800">{__('Variants')}</h2>
                <button
                    type="button"
                    onClick={saveAll}
                    disabled={savingAll}
                    className="rounded-md bg-neutral-800 px-4 py-2 text-sm font-semibold text-white hover:bg-neutral-900 disabled:opacity-50"
                >
                    {savingAll ? __('Saving all…') : __('Save All')}
                </button>
            </div>
            {Object.keys(bulkErrors).length > 0 && (
                <p className="mb-3 text-sm text-red-500">{__('Some variants could not be saved — check the fields below.')}</p>
            )}
            <div className="overflow-x-auto">
                <table className="w-full text-sm">
                    <thead className="bg-neutral-50 text-left text-xs uppercase text-neutral-500">
                        <tr>
                            <th className="px-4 py-2">{__('Combination')}</th>
                            <th className="px-4 py-2">{__('SKU')}</th>
                            <th className="px-4 py-2">{__('Price (₦)')}</th>
                            <th className="px-4 py-2">{__('Stock')}</th>
                            <th className="px-4 py-2" />
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-neutral-100">
                        {product.variants.map((variant) => (
                            <VariantRow
                                key={variant.id}
                                product={product}
                                variant={variant}
                                value={values[variant.id]}
                                onChange={(next) => updateRow(variant.id, next)}
                            />
                        ))}
                    </tbody>
                </table>
            </div>
        </div>
    );
}

function tiersFromServer(priceTiers) {
    return (priceTiers ?? []).map((tier) => ({
        min_quantity: tier.min_quantity,
        max_quantity: tier.max_quantity ?? '',
        price: koboToNaira(tier.price_kobo),
    }));
}

export default function Edit({ product, categories, vendor, commissionTiers = [] }) {
    const { __ } = useTranslation();
    const hasVariants = (product.variants?.length ?? 0) > 0;
    const { data, setData, patch, processing, errors } = useForm({
        title: { zh: product.title?.zh ?? '', en: product.title?.en ?? '', fr: product.title?.fr ?? '' },
        description: { zh: product.description?.zh ?? '', en: product.description?.en ?? '', fr: product.description?.fr ?? '' },
        category_id: product.category_id ?? '',
        sku: product.sku ?? '',
        price: koboToNaira(product.price),
        compare_price: product.compare_price ? koboToNaira(product.compare_price) : '',
        stock: product.stock ?? 0,
        status: product.status ?? 'draft',
        price_tiers: tiersFromServer(product.price_tiers),
    });

    const submit = (e) => {
        e.preventDefault();
        patch(route('vendor.products.update', product.id));
    };

    return (
        <VendorLayout vendor={vendor}>
            <Head title={__('Edit Product')} />

            <h1 className="mb-6 text-xl font-bold text-neutral-900">{__('Edit Product')}</h1>

            <div className="space-y-6">
                <ImageManager product={product} />

                <form onSubmit={submit} className="space-y-6">
                    <ProductFormFields data={data} setData={setData} errors={errors} categories={categories} />

                    {!hasVariants && (
                        <div className="rounded-lg border border-neutral-200 bg-white p-5">
                            <h2 className="mb-1 text-sm font-semibold text-neutral-800">{__('Bulk Pricing (MOQ)')}</h2>
                            <p className="mb-4 text-sm text-neutral-500">
                                {__('Charge less per unit at higher quantities, and optionally require a minimum order quantity.')}
                            </p>
                            <TierPricingEditor
                                tiers={data.price_tiers}
                                onChange={(tiers) => setData('price_tiers', tiers)}
                                errors={errors}
                            />
                        </div>
                    )}

                    <EstimatedEarnings price={data.price} stock={data.stock} vendor={vendor} commissionTiers={commissionTiers} />

                    <button
                        type="submit"
                        disabled={processing}
                        className="w-full rounded-md bg-orange-600 py-3 text-sm font-semibold text-white hover:bg-orange-700 disabled:opacity-50 sm:w-auto sm:px-10"
                    >
                        {__('Save Changes')}
                    </button>
                </form>

                <OptionsEditor product={product} />
                <VariantsTable product={product} />
            </div>
        </VendorLayout>
    );
}
