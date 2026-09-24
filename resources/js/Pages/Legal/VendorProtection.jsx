import LegalLayout from '@/Layouts/LegalLayout';
import { Section, P, Ul, Ol } from '@/Components/Legal/LegalContent';
import { Link } from '@inertiajs/react';

export default function VendorProtection() {
    return (
        <LegalLayout title="Vendor Protection" updatedAt="22 September 2026">
            <Section title="You will be paid — on a predictable schedule">
                <P>
                    Escrow protects buyers by holding their payment until delivery, but it's built to protect you
                    too: once you mark an order delivered, the buyer has a fixed 7-day window to confirm receipt
                    or report a genuine problem. If they do neither, your payout releases to your bank account
                    automatically — you are never left waiting indefinitely on a buyer who simply goes quiet.
                </P>
            </Section>

            <Section title="How your payout works">
                <Ol>
                    <li>You mark the order <strong>Shipped</strong>, then <strong>Delivered</strong>, from your Orders dashboard.</li>
                    <li>Marking it delivered starts the buyer's 7-day return window.</li>
                    <li>If the buyer clicks Confirm Receipt, your payout is released immediately.</li>
                    <li>If they do nothing, it releases automatically once the 7 days pass.</li>
                    <li>Your payout goes straight to the bank account you verified during onboarding — the same commission rate and VAT/shipping configuration you set applies as shown on each order's breakdown.</li>
                </Ol>
            </Section>

            <Section title="If a buyer reports a problem">
                <P>
                    A report pauses your payout for that order and notifies you immediately with the reason the
                    buyer gave. It does not automatically mean a refund — an admin reviews the case (and may ask
                    you for more information, such as proof of what was shipped) before approving either a refund
                    to the buyer or releasing your payout as normal. This impartial review is what protects you
                    from a buyer falsely claiming an issue just to avoid paying, or from a customer wrongly being
                    denied a refund they're entitled to.
                </P>
            </Section>

            <Section title="What you should do to protect yourself">
                <Ul>
                    <li>Ship exactly what was ordered, and keep proof of what you sent (photos before packing, courier receipts).</li>
                    <li>Update tracking information promptly so buyers — and our review team, if a dispute arises — can see accurate delivery status.</li>
                    <li>Respond quickly if we reach out about a reported problem; the faster we have your side, the faster it's resolved.</li>
                    <li>Keep your bank details verified and up to date in Settings — payouts fail if they don't match a real, active account.</li>
                </Ul>
            </Section>

            <Section title="Your obligations as a vendor">
                <P>
                    In turn, we require every vendor to list goods accurately, honor the prices and stock shown at
                    checkout, and ship within the timelines committed to. Repeated valid disputes, counterfeit
                    goods, or attempts to route buyers around escrow may result in account suspension — see our
                    {' '}<Link href={route('legal.terms')}>Terms &amp; Conditions</Link>.
                </P>
            </Section>
        </LegalLayout>
    );
}
