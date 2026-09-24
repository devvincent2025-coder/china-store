import { Head, useForm } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { useTranslation } from '@/hooks/useTranslation';

/**
 * Reached from the "Manage email preferences / Unsubscribe" link in every email footer — see
 * resources/views/vendor/mail/html/footer.blade.php. Only marketing emails (new arrivals, shop
 * reminders, etc — App\Notifications\Marketing\*) are actually optional; transactional mail about
 * orders, payouts, security and support keeps sending regardless, since those are tied to actions
 * on the account itself.
 */
export default function EmailPreferences({ marketingOptOut }) {
    const { __ } = useTranslation();
    const { data, setData, patch, processing, recentlySuccessful } = useForm({
        marketing_opt_out: marketingOptOut,
    });

    const submit = (e) => {
        e.preventDefault();
        patch(route('email-preferences.update'));
    };

    return (
        <AuthenticatedLayout header={<h2 className="text-xl font-semibold text-gray-800">{__('Email Preferences')}</h2>}>
            <Head title={__('Email Preferences')} />

            <div className="py-12">
                <div className="mx-auto max-w-2xl sm:px-6 lg:px-8">
                    <form onSubmit={submit} className="bg-white p-4 shadow sm:rounded-lg sm:p-8">
                        <h3 className="mb-1 text-sm font-semibold text-neutral-800">{__('Marketing emails')}</h3>
                        <p className="mb-4 text-sm text-neutral-500">
                            {__('New arrivals from stores you shop at, store announcements, and reminders. You can turn these off at any time.')}
                        </p>

                        <label className="flex items-center gap-3 rounded-md border border-neutral-200 p-3">
                            <input
                                type="checkbox"
                                checked={!data.marketing_opt_out}
                                onChange={(e) => setData('marketing_opt_out', !e.target.checked)}
                                className="rounded text-orange-600 focus:ring-orange-500"
                            />
                            <span className="text-sm text-neutral-700">{__('Send me marketing emails')}</span>
                        </label>

                        <div className="mt-6 rounded-md bg-neutral-50 p-3 text-xs text-neutral-500">
                            {__('Order updates, payout/escrow notices, security alerts and support replies are always sent — these relate directly to your account and cannot be turned off here.')}
                        </div>

                        <div className="mt-6 flex items-center gap-3">
                            <button
                                type="submit"
                                disabled={processing}
                                className="rounded-md bg-orange-600 px-5 py-2 text-sm font-semibold text-white hover:bg-orange-700 disabled:opacity-50"
                            >
                                {__('Save Preferences')}
                            </button>
                            {recentlySuccessful && <span className="text-sm text-green-600">{__('Saved.')}</span>}
                        </div>
                    </form>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
