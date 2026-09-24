import { Head, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import VendorLayout from '@/Layouts/VendorLayout';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import TextInput from '@/Components/TextInput';
import EmptyState from '@/Components/EmptyState';
import { useTranslation } from '@/hooks/useTranslation';
import confirm from '@/utils/confirm';

export default function Index({ banners, vendor }) {
    const { __ } = useTranslation();
    const [isDragging, setIsDragging] = useState(false);
    const [imagePreview, setImagePreview] = useState(null);
    const { data, setData, post, processing, errors, reset } = useForm({
        image: null,
        link_url: '',
        sort_order: 0,
    });

    const addImageFile = (fileList) => {
        const file = fileList?.[0];
        if (!file) return;

        setData('image', file);
        setImagePreview(URL.createObjectURL(file));
    };

    const handleImageDrop = (e) => {
        e.preventDefault();
        setIsDragging(false);
        addImageFile(e.dataTransfer.files);
    };

    const submit = (e) => {
        e.preventDefault();
        post(route('vendor.banners.store'), {
            forceFormData: true,
            onSuccess: () => { reset(); setImagePreview(null); },
        });
    };

    const destroy = async (banner) => {
        if (await confirm(__('Delete this banner?'), { tone: 'danger' })) {
            router.delete(route('vendor.banners.destroy', banner.id));
        }
    };

    return (
        <VendorLayout vendor={vendor}>
            <Head title={__('Store Banners')} />

            <h1 className="mb-6 text-xl font-bold text-neutral-900">{__('Store Banners')}</h1>

            <form onSubmit={submit} className="mb-8 rounded-lg border border-neutral-200 bg-white p-5">
                <h2 className="mb-4 text-sm font-semibold text-neutral-800">{__('Upload Banner')}</h2>
                <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
                    <div>
                        <InputLabel htmlFor="image-dropzone-input" value={__('Image')} />
                        <label
                            htmlFor="image-dropzone-input"
                            onDragOver={(e) => { e.preventDefault(); setIsDragging(true); }}
                            onDragLeave={() => setIsDragging(false)}
                            onDrop={handleImageDrop}
                            className={`mt-1 flex cursor-pointer flex-col items-center justify-center rounded-lg border-2 border-dashed px-4 py-6 text-center transition ${
                                isDragging ? 'border-orange-500 bg-orange-50' : 'border-neutral-300 bg-neutral-50 hover:border-orange-300 hover:bg-orange-50/50'
                            }`}
                        >
                            {imagePreview ? (
                                <img src={imagePreview} alt="" className="mb-2 h-20 w-full rounded-md object-cover" />
                            ) : (
                                <svg className="mb-2 h-6 w-6 text-neutral-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
                                </svg>
                            )}
                            <p className="text-xs font-medium text-neutral-700">
                                {data.image
                                    ? data.image.name
                                    : isDragging
                                        ? __('Drop image here')
                                        : __('Drag and drop an image here, or click to browse')}
                            </p>
                            <p className="mt-1 text-[11px] text-neutral-400">{__('PNG, JPG up to a few MB')}</p>
                            <input
                                id="image-dropzone-input"
                                type="file"
                                accept="image/*"
                                onChange={(e) => addImageFile(e.target.files)}
                                className="hidden"
                            />
                        </label>
                        <InputError message={errors.image} className="mt-1" />
                    </div>
                    <div>
                        <InputLabel htmlFor="link_url" value={__('Link URL (optional)')} />
                        <TextInput id="link_url" className="mt-1 block w-full" value={data.link_url} onChange={(e) => setData('link_url', e.target.value)} />
                        <InputError message={errors.link_url} className="mt-1" />
                    </div>
                    <div>
                        <InputLabel htmlFor="sort_order" value={__('Sort Order')} />
                        <TextInput id="sort_order" type="number" className="mt-1 block w-full" value={data.sort_order} onChange={(e) => setData('sort_order', e.target.value)} />
                    </div>
                </div>
                <button type="submit" disabled={processing} className="mt-4 rounded-md bg-orange-600 px-5 py-2 text-sm font-semibold text-white hover:bg-orange-700 disabled:opacity-50">
                    {__('Upload')}
                </button>
            </form>

            {banners.length === 0 ? (
                <EmptyState title={__('No banners yet')} description={__('Upload a banner to feature on your store page.')} />
            ) : (
                <div className="grid grid-cols-2 gap-4 sm:grid-cols-3 md:grid-cols-4">
                    {banners.map((banner) => (
                        <div key={banner.id} className="overflow-hidden rounded-lg border border-neutral-200 bg-white">
                            <img src={banner.image_url} alt="" className="aspect-video w-full object-cover" />
                            <div className="flex items-center justify-between p-2">
                                <span className="text-xs text-neutral-400">#{banner.sort_order}</span>
                                <button onClick={() => destroy(banner)} className="text-xs font-medium text-red-500 hover:underline">
                                    {__('Delete')}
                                </button>
                            </div>
                        </div>
                    ))}
                </div>
            )}
        </VendorLayout>
    );
}
