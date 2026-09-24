import LegalLayout from '@/Layouts/LegalLayout';
import { Section, P, Ul, Ol } from '@/Components/Legal/LegalContent';
import { Link } from '@inertiajs/react';

export default function CustomerProtection({ escrowReturnWindowDays = 7, faqs = [], seo }) {
    return (
        <LegalLayout title="Customer Protection" updatedAt="22 September 2026" seo={seo}>
            <Section title="Our guarantee">
                <p className="text-lg font-medium text-neutral-800">
                    Your money stays protected until you confirm you're happy — or your return window passes.
                </p>
                <P>
                    This costs you nothing extra and is built directly into how escrow already works below: we
                    simply don't release the vendor's payout while you still have a genuine, unresolved problem
                    within your {escrowReturnWindowDays}-day window.
                </P>
            </Section>

            <Section title="Your money is held in escrow until delivery">
                <P>
                    When you pay for an order on ChinaInNigeria, your money does not go straight to the vendor.
                    It is held securely by our payment partner, Korapay, in a protected account controlled by the
                    Platform — not the vendor. The vendor only gets paid once your order is confirmed delivered,
                    exactly the way large marketplaces like Jumia protect their buyers.
                </P>
            </Section>

            <Section title="How it works">
                <Ol>
                    <li><strong>Payment</strong> — you pay online through Korapay when placing your order.</li>
                    <li><strong>Holding</strong> — ChinaInNigeria holds your payment instead of releasing it to the vendor immediately.</li>
                    <li><strong>Delivery</strong> — the vendor ships your item and updates tracking on your order page.</li>
                    <li><strong>Inspection &amp; release</strong> — once delivered, you have {escrowReturnWindowDays} days to inspect it. If everything's fine, click <strong>Confirm Receipt</strong> to release payment right away, or simply do nothing and it releases automatically after {escrowReturnWindowDays} days.</li>
                    <li><strong>Disputes &amp; refunds</strong> — if something's wrong, click <strong>Report a Problem</strong> within those {escrowReturnWindowDays} days instead, with photos if you have them. This pauses the vendor's payout while our team reviews it. The vendor gets 48–72 hours to respond with their own evidence, then an admin makes a final call: refund, replacement, or denial.</li>
                </Ol>
            </Section>

            <Section title="What this protects you from">
                <Ul>
                    <li>Paying for an item that never arrives — the vendor isn't paid until delivery is confirmed.</li>
                    <li>Being pressured to "sort it out with the vendor directly" — an admin reviews disputes impartially, using evidence from both sides.</li>
                    <li>Losing your money to a damaged, wrong, or missing item — see our <Link href={route('legal.return-policy')}>Return Policy</Link> for eligible reasons and the refund timeline.</li>
                    <li>Endless back-and-forth — once an admin decides, that decision is final.</li>
                </Ul>
            </Section>

            <Section title="Tracking every step">
                <P>
                    Every order shows a step-by-step delivery tracker (Order Placed → Processing → Shipped →
                    Delivered) with a timestamp for each step, on your <Link href={route('orders.index')}>My Orders</Link> page
                    or the public <Link href={route('track-order.index')}>Track Order</Link> page if you'd rather not log in.
                </P>
            </Section>

            <Section title="Where the risk sits, and why it stays small">
                <P>
                    The only real risk on our side is a genuine problem surfacing after your {escrowReturnWindowDays}-day
                    window has closed and the vendor has already been paid. That's exactly why we keep the window
                    length realistic — long enough to be fair, short enough that your payment isn't sitting in limbo
                    forever — and why we show it to you clearly at checkout and on every order page, so "you had
                    until [date] to report a problem" is never a surprise.
                </P>
            </Section>

            <Section title="What's not covered">
                <P>
                    Escrow protects the payment for goods bought and paid for through ChinaInNigeria's checkout.
                    It does not cover payments made outside the Platform, change-of-mind returns (see the vendor's
                    own listing terms), or issues reported after the {escrowReturnWindowDays}-day return window has
                    passed.
                </P>
            </Section>

            {faqs.length > 0 && (
                <Section title="Frequently asked questions">
                    <div className="space-y-4">
                        {faqs.map((faq) => (
                            <div key={faq.question}>
                                <p className="text-sm font-semibold text-neutral-800">{faq.question}</p>
                                <p className="mt-1 text-sm leading-relaxed text-neutral-700">{faq.answer}</p>
                            </div>
                        ))}
                    </div>
                </Section>
            )}
        </LegalLayout>
    );
}
