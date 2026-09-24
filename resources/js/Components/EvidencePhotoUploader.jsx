import { useEffect, useState } from 'react';
import { useTranslation } from '@/hooks/useTranslation';

const MAX_EVIDENCE_PHOTOS = 5;

function Thumbnail({ file, onRemove }) {
    const [previewUrl] = useState(() => URL.createObjectURL(file));

    useEffect(() => () => URL.revokeObjectURL(previewUrl), [previewUrl]);

    return (
        <div className="relative overflow-hidden rounded-md border border-neutral-200">
            <img src={previewUrl} alt="" className="aspect-square w-full object-cover" />
            <button
                type="button"
                onClick={onRemove}
                aria-label="Remove"
                className="absolute right-1 top-1 flex h-5 w-5 items-center justify-center rounded-full bg-black/60 text-white hover:bg-black/80"
            >
                <svg className="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2.5} d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>
    );
}

/**
 * Small drag-and-drop photo picker for dispute evidence (buyer opening a report, vendor responding
 * to one) — same drag/drop UX as KycModal.jsx's single-file dropzone and Vendor/Products/Create.jsx's
 * StagedPhotos, just generalized for "0 to :max optional photos" instead of a required range.
 */
export default function EvidencePhotoUploader({ files, onChange, max = MAX_EVIDENCE_PHOTOS }) {
    const { __ } = useTranslation();
    const [isDragging, setIsDragging] = useState(false);

    const remainingSlots = Math.max(0, max - files.length);
    const atMaximum = remainingSlots === 0;

    const addFiles = (fileList) => {
        const incoming = Array.from(fileList).filter((file) => file.type.startsWith('image/'));
        if (incoming.length === 0) return;

        onChange([...files, ...incoming.slice(0, remainingSlots)]);
    };

    const handleDrop = (e) => {
        e.preventDefault();
        setIsDragging(false);
        addFiles(e.dataTransfer.files);
    };

    return (
        <div>
            {files.length > 0 && (
                <div className="mb-2 grid grid-cols-4 gap-2 sm:grid-cols-5">
                    {files.map((file, index) => (
                        <Thumbnail key={index} file={file} onRemove={() => onChange(files.filter((_, i) => i !== index))} />
                    ))}
                </div>
            )}

            {!atMaximum && (
                <label
                    htmlFor="evidence-dropzone-input"
                    onDragOver={(e) => { e.preventDefault(); setIsDragging(true); }}
                    onDragLeave={() => setIsDragging(false)}
                    onDrop={handleDrop}
                    className={`flex cursor-pointer items-center justify-center gap-2 rounded-md border-2 border-dashed px-3 py-3 text-center text-xs transition ${
                        isDragging ? 'border-orange-500 bg-orange-50 text-orange-600' : 'border-neutral-300 bg-neutral-50 text-neutral-500 hover:border-orange-300 hover:bg-orange-50/50'
                    }`}
                >
                    <svg className="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M12 4v16m8-8H4" />
                    </svg>
                    {__('Drag photos here, or click to add (:count/:max)', { count: files.length, max })}
                    <input
                        id="evidence-dropzone-input"
                        type="file"
                        accept="image/*"
                        multiple
                        className="hidden"
                        onChange={(e) => { addFiles(e.target.files); e.target.value = ''; }}
                    />
                </label>
            )}
        </div>
    );
}
