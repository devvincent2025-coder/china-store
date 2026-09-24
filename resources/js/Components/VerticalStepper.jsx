import { useTranslation } from '@/hooks/useTranslation';

const DOT_STYLES = {
    complete: 'bg-green-500 ring-green-100',
    current: 'bg-orange-600 ring-orange-100 animate-pulse',
    upcoming: 'bg-neutral-200 ring-white',
    skipped: 'bg-neutral-200 ring-white',
    cancelled: 'bg-red-500 ring-red-100',
};

const LABEL_STYLES = {
    complete: 'text-neutral-800',
    current: 'text-orange-600',
    upcoming: 'text-neutral-400',
    skipped: 'text-neutral-300 line-through',
    cancelled: 'text-red-600',
};

const LINE_STYLES = {
    complete: 'bg-green-500',
    current: 'bg-neutral-200',
    upcoming: 'bg-neutral-200',
    skipped: 'bg-neutral-200',
    cancelled: 'bg-neutral-200',
};

/**
 * Generic vertical progress stepper — used on the buyer order page, the vendor order page, and the
 * public track-order page so a fulfillment journey (or any status timeline) always renders the same
 * way. `steps` comes from utils/orderSteps.js's buildFulfillmentSteps() for order tracking, but this
 * component itself has no order-specific knowledge.
 */
export default function VerticalStepper({ steps }) {
    const { __ } = useTranslation();

    return (
        <ol>
            {steps.map((step, index) => (
                <li key={step.key} className="relative flex gap-3 pb-6 last:pb-0">
                    {index < steps.length - 1 && (
                        <span className={`absolute left-[7px] top-4 h-full w-0.5 ${LINE_STYLES[step.state]}`} />
                    )}
                    <span
                        className={`relative z-10 mt-0.5 flex h-4 w-4 shrink-0 items-center justify-center rounded-full ring-4 ${DOT_STYLES[step.state] ?? DOT_STYLES.upcoming}`}
                    >
                        {step.state === 'complete' && (
                            <svg className="h-2.5 w-2.5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden>
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={3} d="M5 13l4 4L19 7" />
                            </svg>
                        )}
                    </span>
                    <div className="flex-1">
                        <p className={`text-sm font-semibold ${LABEL_STYLES[step.state] ?? LABEL_STYLES.upcoming}`}>
                            {__(step.label)}
                        </p>
                        {step.timestamp ? (
                            <p className="text-xs text-neutral-400">{new Date(step.timestamp).toLocaleString('en-NG')}</p>
                        ) : step.state === 'current' ? (
                            <p className="text-xs text-orange-500">{__('In progress')}</p>
                        ) : step.state === 'upcoming' ? (
                            <p className="text-xs text-neutral-300">{__('Pending')}</p>
                        ) : null}
                        {step.note && <p className="mt-1 text-xs italic text-neutral-500">"{step.note}"</p>}
                    </div>
                </li>
            ))}
        </ol>
    );
}
