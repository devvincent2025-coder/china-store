import LegalLayout from '@/Layouts/LegalLayout';
import { Section, P, Ul, Ol } from '@/Components/Legal/LegalContent';
import { Link } from '@inertiajs/react';

export default function ReturnPolicy({ escrowReturnWindowDays = 7 }) {
    return (
        <LegalLayout title="Return Policy" updatedAt="22 September 2026">
            <Section title={`The ${escrowReturnWindowDays}-day return window`}>
                <P>
                    You have <strong>{escrowReturnWindowDays} days from the day an item is marked delivered</strong> to
                    report a problem with it. This mirrors the standard marketplace return window used across
                    Nigerian e-commerce platforms. Once those {escrowReturnWindowDays} days pass with no report, the
                    order is considered accepted and the vendor's escrow payout is released automatically — see our
                    {' '}<Link href={route('legal.customer-protection')}>Customer Protection</Link> page for how escrow works.
                </P>
            </Section>

            <Section title="Who pays for the return">
                <Ul>
                    <li><strong>Item damaged, wrong, or fake</strong> — the vendor pays for return delivery and you get a full refund.</li>
                    <li><strong>You changed your mind</strong> — this is handled at each vendor's own discretion, not by escrow protection. Where a vendor does offer it, you pay for return delivery — check the individual product listing for their own terms before ordering.</li>
                </Ul>
            </Section>

            <Section title="What's eligible for a return">
                <Ul>
                    <li><strong>Damaged</strong> — the item arrived broken, faulty, or physically damaged.</li>
                    <li><strong>Wrong item</strong> — you received a different product than what you ordered.</li>
                    <li><strong>Not as described</strong> — the item materially differs from its listing (e.g. wrong size, color, or specification, not a subjective quality opinion).</li>
                    <li><strong>Missing item</strong> — part of your order was not included in the delivery.</li>
                </Ul>
                <P>
                    Change-of-mind returns are handled at each vendor's own discretion and are not covered by
                    this policy or by escrow protection — check the individual product listing for the vendor's
                    own return terms before ordering.
                </P>
            </Section>

            <Section title="How to request a return">
                <Ol>
                    <li>Go to your order on the <Link href={route('orders.index')}>My Orders</Link> page (or via the <Link href={route('track-order.index')}>Track Order</Link> page if you're not logged in).</li>
                    <li>Once the item shows as "Delivered", click <strong>Report a Problem</strong> instead of Confirm Receipt.</li>
                    <li>Select a reason, describe the issue, and attach photos right there — no need to wait for a follow-up email.</li>
                    <li>The vendor has 48–72 hours to respond with their side and any evidence. If they don't respond, or you can't agree, our team steps in.</li>
                    <li>An admin reviews both sides and makes a final call: refund, replacement, or denial. This decision is final.</li>
                </Ol>
            </Section>

            <Section title="How fast a vendor must respond">
                <P>
                    Once you report a problem, the vendor has <strong>48–72 hours</strong> to respond with their side
                    of the story and any evidence — photos, packaging, delivery confirmation. If they don't respond
                    in time, or the two of you can't agree on what happened, an admin steps in and makes the final
                    call based on whatever evidence is available. That decision is final — it isn't left to keep
                    going back and forth between you and the vendor.
                </P>
            </Section>

            <Section title="Refund timeline">
                <P>
                    Once a refund is approved, it is processed back to your original payment method within
                    {' '}<strong>10 business days</strong>. Because your payment was held in escrow rather than
                    already paid out to the vendor, most refunds can be issued directly from the held balance
                    without needing the vendor to send money back.
                </P>
            </Section>

            <Section title="Non-returnable items">
                <P>
                    Perishable goods, made-to-order/custom items, and opened personal-care or hygiene products
                    are generally not eligible for return unless they arrived damaged or defective. Any such
                    exclusions specific to a product will be noted on its listing.
                </P>
            </Section>
        </LegalLayout>
    );
}
