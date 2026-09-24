import { useTranslation } from '@/hooks/useTranslation';

// Maps a lowercase status key to its exact lang/en.json UI-string key, for statuses that have one.
const LABEL_KEYS = {
    pending: 'Pending',
    processing: 'Processing',
    shipped: 'Shipped',
    delivered: 'Delivered',
    cancelled: 'Cancelled',
    approved: 'Approved',
    rejected: 'Rejected',
    active: 'Active',
    suspended: 'Suspended',
    draft: 'Draft',
    sent: 'Sent',
    partially_paid: 'Partially Paid',
    void: 'Void',
    // escrow
    holding: 'In Escrow',
    releasing: 'Releasing',
    released: 'Released',
    refund_pending: 'Refund Pending',
    refunded: 'Refunded',
    // disputes
    open: 'Open',
    approved_refund: 'Refund Approved',
    approved_release: 'Release Approved',
    closed: 'Closed',
    // support tickets
    resolved: 'Resolved',
    // ad campaigns
    pending_payment: 'Pending Payment',
    pending_approval: 'Pending Approval',
    paused: 'Paused',
    expired: 'Expired',
};

const STYLES = {
    // generic
    active: 'bg-green-100 text-green-700',
    inactive: 'bg-neutral-100 text-neutral-500',
    // product status
    published: 'bg-green-100 text-green-700',
    draft: 'bg-neutral-100 text-neutral-600',
    archived: 'bg-neutral-100 text-neutral-400',
    // documents / bank verification
    pending: 'bg-amber-100 text-amber-700',
    approved: 'bg-green-100 text-green-700',
    verified: 'bg-green-100 text-green-700',
    rejected: 'bg-red-100 text-red-700',
    failed: 'bg-red-100 text-red-700',
    not_submitted: 'bg-neutral-100 text-neutral-500',
    // fulfillment / payment
    processing: 'bg-amber-100 text-amber-700',
    shipped: 'bg-blue-100 text-blue-700',
    delivered: 'bg-green-100 text-green-700',
    cancelled: 'bg-red-100 text-red-700',
    paid: 'bg-green-100 text-green-700',
    unpaid: 'bg-amber-100 text-amber-700',
    // invoices
    sent: 'bg-blue-100 text-blue-700',
    partially_paid: 'bg-amber-100 text-amber-700',
    void: 'bg-neutral-100 text-neutral-400',
    // escrow
    holding: 'bg-blue-100 text-blue-700',
    releasing: 'bg-amber-100 text-amber-700',
    released: 'bg-green-100 text-green-700',
    refund_pending: 'bg-amber-100 text-amber-700',
    refunded: 'bg-red-100 text-red-700',
    // disputes
    open: 'bg-amber-100 text-amber-700',
    approved_refund: 'bg-red-100 text-red-700',
    approved_release: 'bg-green-100 text-green-700',
    closed: 'bg-neutral-100 text-neutral-400',
    // support tickets
    resolved: 'bg-green-100 text-green-700',
    // ad campaigns
    pending_payment: 'bg-neutral-100 text-neutral-500',
    pending_approval: 'bg-amber-100 text-amber-700',
    paused: 'bg-neutral-100 text-neutral-500',
    expired: 'bg-neutral-100 text-neutral-400',
};

export default function StatusBadge({ status, className = '' }) {
    const { __ } = useTranslation();
    const key = (status ?? '').toString().toLowerCase();
    const style = STYLES[key] || 'bg-neutral-100 text-neutral-600';
    const label = key ? (LABEL_KEYS[key] ? __(LABEL_KEYS[key]) : key.replace(/_/g, ' ')) : 'unknown';

    return (
        <span
            className={`inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold capitalize ${style} ${className}`}
        >
            {label}
        </span>
    );
}
