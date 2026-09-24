const STAGES = ['processing', 'shipped', 'delivered'];

const LABELS = {
    processing: 'Processing',
    shipped: 'Shipped',
    delivered: 'Delivered',
};

/**
 * Builds the ordered step list a <VerticalStepper> renders for one vendor_order, from its current
 * fulfillment_status plus the full status_history log (see VendorOrderStatusHistory) — the vendor_order
 * columns only ever hold the CURRENT state, so history is what supplies each step's timestamp.
 *
 * `vendorOrder.status_history` is the snake_case serialization of the statusHistory() relation.
 */
export function buildFulfillmentSteps(vendorOrder) {
    const history = vendorOrder.status_history || [];
    const timestampFor = (status) => history.find((entry) => entry.status === status)?.created_at ?? null;
    const noteFor = (status) => history.find((entry) => entry.status === status)?.note ?? null;

    const status = vendorOrder.fulfillment_status;
    const isCancelled = status === 'cancelled';
    const currentIndex = STAGES.indexOf(status);

    const steps = [
        {
            key: 'placed',
            label: 'Order Placed',
            timestamp: vendorOrder.created_at,
            note: null,
            state: 'complete',
        },
        ...STAGES.map((stage, index) => ({
            key: stage,
            label: LABELS[stage],
            timestamp: timestampFor(stage),
            note: noteFor(stage),
            state: isCancelled
                ? (index === 0 ? 'complete' : 'skipped')
                : index < currentIndex ? 'complete'
                    // The last stage (delivered) is a finished, successful state, not something
                    // still "in progress" waiting on a next step — it should read the same green
                    // and checked as every other completed step once reached, not stay orange
                    // forever since there is no further stage after it.
                    : index === currentIndex ? (index === STAGES.length - 1 ? 'complete' : 'current')
                        : 'upcoming',
        })),
    ];

    if (isCancelled) {
        steps.push({
            key: 'cancelled',
            label: 'Cancelled',
            timestamp: timestampFor('cancelled'),
            note: noteFor('cancelled'),
            state: 'cancelled',
        });
    }

    return steps;
}
