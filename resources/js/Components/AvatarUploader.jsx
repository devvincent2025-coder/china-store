import { useRef, useState } from 'react';
import { router } from '@inertiajs/react';
import CameraCaptureModal from '@/Components/CameraCaptureModal';
import { useTranslation } from '@/hooks/useTranslation';

/**
 * Shared avatar upload used on every account type's profile page (buyer, vendor) — picks a file or
 * captures one from the camera, uploads immediately (no separate "save" step, matching how a
 * profile picture is expected to behave everywhere). See Http\Controllers\AvatarController.
 */
export default function AvatarUploader({ user, size = 'lg' }) {
    const { __ } = useTranslation();
    const fileInputRef = useRef(null);
    const [cameraOpen, setCameraOpen] = useState(false);
    const [uploading, setUploading] = useState(false);

    const dimension = size === 'lg' ? 'h-24 w-24' : 'h-14 w-14';

    const upload = (file) => {
        setUploading(true);
        router.post(route('avatar.store'), { avatar: file }, {
            forceFormData: true,
            preserveScroll: true,
            onFinish: () => setUploading(false),
        });
    };

    const remove = () => {
        router.delete(route('avatar.destroy'), { preserveScroll: true });
    };

    const initial = (user?.name ?? '?').charAt(0).toUpperCase();

    return (
        <div className="flex items-center gap-4">
            <div className={`relative shrink-0 overflow-hidden rounded-full border-2 border-white bg-orange-100 shadow ${dimension}`}>
                {user?.avatar_url ? (
                    <img src={user.avatar_url} alt={user.name} className="h-full w-full object-cover" />
                ) : (
                    <div className="flex h-full w-full items-center justify-center text-xl font-bold text-orange-600">{initial}</div>
                )}
                {uploading && (
                    <div className="absolute inset-0 flex items-center justify-center bg-black/40">
                        <svg className="h-6 w-6 animate-spin text-white" fill="none" viewBox="0 0 24 24">
                            <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
                            <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z" />
                        </svg>
                    </div>
                )}
            </div>

            <div className="flex flex-col gap-1.5">
                <div className="flex gap-2">
                    <button
                        type="button"
                        onClick={() => fileInputRef.current?.click()}
                        className="rounded-md border border-neutral-300 px-3 py-1.5 text-xs font-semibold text-neutral-700 hover:bg-neutral-50"
                    >
                        {__('Upload Photo')}
                    </button>
                    <button
                        type="button"
                        onClick={() => setCameraOpen(true)}
                        className="rounded-md border border-neutral-300 px-3 py-1.5 text-xs font-semibold text-neutral-700 hover:bg-neutral-50"
                    >
                        {__('Take Photo')}
                    </button>
                </div>
                {user?.avatar_url && (
                    <button type="button" onClick={remove} className="text-left text-xs font-medium text-red-500 hover:underline">
                        {__('Remove photo')}
                    </button>
                )}
            </div>

            <input
                ref={fileInputRef}
                type="file"
                accept="image/*"
                className="hidden"
                onChange={(e) => {
                    if (e.target.files?.[0]) upload(e.target.files[0]);
                    e.target.value = '';
                }}
            />

            <CameraCaptureModal show={cameraOpen} onClose={() => setCameraOpen(false)} onCapture={(file) => upload(file)} />
        </div>
    );
}
