import { useState } from 'react';
import Modal from '@/Components/Modal';
import { useTranslation } from '@/hooks/useTranslation';

/**
 * Mandatory, non-dismissable terms gate shown before a vendor can reach the "Boost Your Store"
 * form — closeable=false means there's no backdrop-click/Escape way out, only the checkbox +
 * button below. The core rule this exists to surface: a paid campaign can't be cancelled, so the
 * vendor should schedule its start date for whenever they're actually ready to run it, not just
 * "as soon as possible".
 */
export default function AdsTermsModal({ show, onAgree }) {
    const { __ } = useTranslation();
    const [checked, setChecked] = useState(false);

    return (
        <Modal show={show} closeable={false} maxWidth="lg">
            <div className="p-6">
                <h2 className="text-lg font-bold text-neutral-900">{__('Store Boost — Terms & Conditions')}</h2>

                <div className="mt-4 max-h-72 space-y-3 overflow-y-auto rounded-md border border-neutral-200 bg-neutral-50 p-4 text-sm text-neutral-600">
                    <p>
                        {__('Payment for a store boost campaign is final. Once you pay, the campaign cannot be cancelled or refunded for unused days — please choose a start date you\'re actually ready for, not just the earliest one available.')}
                    </p>
                    <p>
                        {__('Your campaign only starts counting down its paid days once an admin approves it. If it\'s approved on or after your chosen start date, it begins immediately; if approved earlier, it will wait until the date you picked.')}
                    </p>
                    <p>
                        {__('You can pause a running campaign at any time, but once paused you must wait 24 hours before you\'re able to resume it — plan pauses accordingly.')}
                    </p>
                    <p>
                        {__('The campaign automatically stops showing to buyers once its paid duration ends, whether or not you\'ve paused it along the way.')}
                    </p>
                    <p>
                        {__('Your banner must accurately represent your store and comply with our marketplace content guidelines. Campaigns with misleading or inappropriate banners may be rejected without a refund.')}
                    </p>
                </div>

                <label className="mt-4 flex items-start gap-2 text-sm text-neutral-700">
                    <input
                        type="checkbox"
                        checked={checked}
                        onChange={(e) => setChecked(e.target.checked)}
                        className="mt-0.5 rounded border-neutral-300 text-rose-600 focus:ring-rose-500"
                    />
                    {__('I have read and understand these terms, including that payment is final and non-cancellable.')}
                </label>

                <button
                    type="button"
                    disabled={!checked}
                    onClick={onAgree}
                    className="mt-5 w-full rounded-md bg-rose-600 py-2.5 text-sm font-semibold text-white hover:bg-rose-700 disabled:cursor-not-allowed disabled:opacity-50"
                >
                    {__('I Agree — Continue')}
                </button>
            </div>
        </Modal>
    );
}
