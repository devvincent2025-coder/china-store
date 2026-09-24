import { Head, useForm } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import VendorLayout from '@/Layouts/VendorLayout';
import ProductFormFields from '@/Components/ProductFormFields';
import TranslatableLocaleField from '@/Components/TranslatableLocaleField';
import EstimatedEarnings from '@/Components/EstimatedEarnings';
import CameraCaptureModal from '@/Components/CameraCaptureModal';
import TierPricingEditor from '@/Components/TierPricingEditor';
import { useTranslation } from '@/hooks/useTranslation';

const MINIMUM_PRODUCT_PHOTOS = 3;
const MAXIMUM_PRODUCT_PHOTOS = 5;

function StagedThumbnail({ file, onRemove }) {
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

/**
 * Photo staging for a product that doesn't exist yet — same drag-and-drop/camera-capture UX as
 * Edit.jsx's ImageManager, but nothing uploads until the whole product form is submitted (there's
 * no product_id to attach photos to until then). See Vendor\ProductController::store(), which
 * ingests these files via ProductImageIngestionService right after creating the product row.
 */
function StagedPhotos({ files, onChange }) {
    const { __ } = useTranslation();
    const [isDragging, setIsDragging] = useState(false);
    const [cameraOpen, setCameraOpen] = useState(false);

    const totalCount = files.length;
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

        onChange([...files, ...incoming.slice(0, remainingSlots)]);
    };

    const removeFile = (index) => onChange(files.filter((_, i) => i !== index));

    const handleDrop = (e) => {
        e.preventDefault();
        setIsDragging(false);
        addFiles(e.dataTransfer.files);
    };

    return (
        <div className="rounded-lg border border-neutral-200 bg-white p-5">
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

            {files.length > 0 && (
                <div className="mt-3 grid grid-cols-3 gap-3 sm:grid-cols-4">
                    {files.map((file, index) => (
                        <StagedThumbnail key={`${file.name}-${index}`} file={file} onRemove={() => removeFile(index)} />
                    ))}
                </div>
            )}

            <CameraCaptureModal show={cameraOpen} onClose={() => setCameraOpen(false)} onCapture={(file) => addFiles([file])} />
        </div>
    );
}

function blankOption() {
    return {
        name: { zh: '', en: '', fr: '' },
        values: [{ zh: '', en: '', fr: '' }],
    };
}

/**
 * Options/variants staged locally — nothing is synced until the product itself is created, since
 * variants belong to a product_id that doesn't exist yet. See ProductOptionSyncService, called
 * from ProductController::store() right after product creation when any options were staged here.
 */
function StagedOptions({ options, onChange, errors }) {
    const { __ } = useTranslation();

    const updateOptionName = (optIndex, nextName) => {
        onChange(options.map((opt, i) => (i === optIndex ? { ...opt, name: nextName } : opt)));
    };

    const updateValue = (optIndex, valIndex, nextValue) => {
        onChange(
            options.map((opt, i) => {
                if (i !== optIndex) return opt;
                return { ...opt, values: opt.values.map((v, j) => (j === valIndex ? nextValue : v)) };
            })
        );
    };

    const addOption = () => onChange([...options, blankOption()]);
    const removeOption = (optIndex) => onChange(options.filter((_, i) => i !== optIndex));
    const addValue = (optIndex) => {
        onChange(options.map((opt, i) => (i === optIndex ? { ...opt, values: [...opt.values, { zh: '', en: '', fr: '' }] } : opt)));
    };
    const removeValue = (optIndex, valIndex) => {
        onChange(options.map((opt, i) => (i === optIndex ? { ...opt, values: opt.values.filter((_, j) => j !== valIndex) } : opt)));
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
                            <button type="button" onClick={() => addValue(optIndex)} className="text-sm font-medium text-orange-600 hover:underline">
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
                {options.length === 0 && (
                    <span className="text-xs text-neutral-400">{__('Optional — skip this if this product has no variations.')}</span>
                )}
            </div>
        </div>
    );
}

export default function Create({ categories, vendor, commissionTiers = [] }) {
    const { __ } = useTranslation();
    const { data, setData, post, processing, errors } = useForm({
        title: { zh: '', en: '', fr: '' },
        description: { zh: '', en: '', fr: '' },
        category_id: '',
        sku: '',
        price: '',
        compare_price: '',
        stock: 0,
        // Matches the "Auto-publish new products" preference set in Vendor Settings — a vendor who
        // wants to review every listing before it goes live can turn that off there.
        status: vendor?.auto_publish_products === false ? 'draft' : 'published',
        images: [],
        options: [],
        price_tiers: [],
    });

    const submit = (e) => {
        e.preventDefault();
        post(route('vendor.products.store'), { forceFormData: true });
    };

    return (
        <VendorLayout vendor={vendor}>
            <Head title={__('Add Product')} />

            <h1 className="mb-6 text-xl font-bold text-neutral-900">{__('Add Product')}</h1>

            <form onSubmit={submit} className="space-y-6">
                <StagedPhotos files={data.images} onChange={(files) => setData('images', files)} />

                <ProductFormFields data={data} setData={setData} errors={errors} categories={categories} />

                <StagedOptions options={data.options} onChange={(options) => setData('options', options)} errors={errors} />

                <div className="rounded-lg border border-neutral-200 bg-white p-5">
                    <h2 className="mb-1 text-sm font-semibold text-neutral-800">{__('Bulk Pricing (MOQ)')}</h2>
                    <p className="mb-4 text-sm text-neutral-500">
                        {__('Charge less per unit at higher quantities, and optionally require a minimum order quantity. Only applies if this product has no options/variants above — with variants, set tiers per variant instead after creating the product.')}
                    </p>
                    <TierPricingEditor
                        tiers={data.price_tiers}
                        onChange={(tiers) => setData('price_tiers', tiers)}
                        errors={errors}
                    />
                </div>

                <EstimatedEarnings price={data.price} stock={data.stock} vendor={vendor} commissionTiers={commissionTiers} />

                <button
                    type="submit"
                    disabled={processing}
                    className="w-full rounded-md bg-orange-600 py-3 text-sm font-semibold text-white hover:bg-orange-700 disabled:opacity-50 sm:w-auto sm:px-10"
                >
                    {processing ? __('Uploading…') : __('Create Product')}
                </button>
            </form>
        </VendorLayout>
    );
}
