import { Link } from '@inertiajs/react';
import { useTranslation } from '@/hooks/useTranslation';
import { formatNaira } from '@/utils/money';
import PaymentSuccessIllustration from '@/Components/illustrations/PaymentSuccessIllustration';
import PaymentFailedIllustration from '@/Components/illustrations/PaymentFailedIllustration';
import EcommerceScatterIcons from '@/Components/EcommerceScatterIcons';

/**
 * Full-viewport celebratory/apology modal shown right after the buyer is bounced back from
 * Korapay — see Orders/Show.jsx, which pops this from the one-shot `flash.paymentResult` prop set
 * by CheckoutController::callback(). Unlike WelcomeModal (a plain white card on a dark overlay),
 * this one colors the ENTIRE viewport background with the brand gradient so it reads as a
 * dedicated moment rather than just another dialog.
 */
export default function PaymentResultModal({ status, orderNumber, total, onClose }) {
    const { __ } = useTranslation();
    const isSuccess = status === 'success';

    return (
        <div
            className={
                'fixed inset-0 z-[100] flex items-center justify-center p-4 ' +
                (isSuccess
                    ? 'bg-gradient-to-br from-green-500 via-emerald-600 to-teal-700'
                    : 'bg-gradient-to-br from-red-500 via-rose-600 to-red-800')
            }
            role="dialog"
            aria-modal="true"
            aria-labelledby="payment-result-title"
        >
            <EcommerceScatterIcons />

            <div className="relative w-full max-w-md overflow-hidden rounded-3xl bg-white p-6 text-center shadow-2xl sm:p-8">
                <div className="cin-checkmark-wrap mx-auto flex h-40 w-40 items-center justify-center">
                    {isSuccess ? (
                        <PaymentSuccessIllustration className="h-40 w-40" />
                    ) : (
                        <PaymentFailedIllustration className="h-40 w-40" />
                    )}
                </div>

                <h2 id="payment-result-title" className="font-display mt-4 text-2xl font-bold text-gray-900">
                    {isSuccess ? __('Payment Successful!') : __('Payment Failed')}
                </h2>

                <p className="mt-3 text-sm leading-relaxed text-gray-600">
                    {isSuccess
                        ? __('Your order :order has been confirmed. We\'ve emailed you a full receipt, and each vendor will start preparing your items shortly.', { order: orderNumber })
                        : __("We couldn't process your payment for order :order. No money has left your account — your items are still reserved so you can try again.", { order: orderNumber })}
                </p>

                {total != null && (
                    <p className={'mt-3 text-lg font-bold ' + (isSuccess ? 'text-green-600' : 'text-red-600')}>
                        {formatNaira(total)}
                    </p>
                )}

                <Link
                    href={route('orders.show', orderNumber)}
                    onClick={onClose}
                    className={
                        'mt-6 block w-full rounded-lg px-6 py-3.5 text-base font-bold text-white transition-transform focus:outline-none focus:ring-2 focus:ring-offset-2 active:scale-[0.98] ' +
                        (isSuccess
                            ? 'cin-cta-blink focus:ring-green-500'
                            : 'bg-red-600 hover:bg-red-700 focus:ring-red-500')
                    }
                >
                    {isSuccess ? __('View Order') : __('Try Again')}
                </Link>

                <button
                    type="button"
                    onClick={onClose}
                    className="mt-3 w-full text-center text-sm font-medium text-gray-400 hover:text-gray-600 hover:underline"
                >
                    {__('Close')}
                </button>
            </div>
        </div>
    );
}
