import LegalLayout from '@/Layouts/LegalLayout';
import { Section, P, Ul } from '@/Components/Legal/LegalContent';
import { Link } from '@inertiajs/react';

export default function Terms() {
    return (
        <LegalLayout title="Terms &amp; Conditions" updatedAt="22 September 2026">
            <Section title="1. Who we are">
                <P>
                    ChinaInNigeria ("we", "us", "the Platform") operates an online marketplace connecting
                    independent vendors — mostly Chinese-run stores — with buyers across Nigeria. By creating an
                    account, browsing, or placing an order on the Platform, you agree to these Terms &amp;
                    Conditions.
                </P>
            </Section>

            <Section title="2. The marketplace model">
                <P>
                    Products listed on the Platform are sold by independent, third-party vendors — not by
                    ChinaInNigeria directly. Each vendor is responsible for the accuracy of their own listings,
                    the quality and legality of their goods, and fulfilling orders within the timelines they
                    commit to. ChinaInNigeria is not a party to the underlying sale contract between you and the
                    vendor, but we do act as the payment intermediary (see Section 4) and set the platform rules
                    every vendor must follow.
                </P>
            </Section>

            <Section title="3. Accounts">
                <Ul>
                    <li>You must provide accurate information when registering and keep your login credentials confidential.</li>
                    <li>You must be at least 18 years old, or have a parent/guardian's consent, to create an account or place an order.</li>
                    <li>Vendor accounts additionally require identity/business document verification and a verified Nigerian bank account before the store goes live — see our <Link href={route('legal.vendor-protection')}>Vendor Protection</Link> page.</li>
                </Ul>
            </Section>

            <Section title="4. Orders, pricing &amp; payment">
                <Ul>
                    <li>All prices are listed in Nigerian Naira (₦) and include any VAT the vendor has configured on their store.</li>
                    <li>Payment is processed by Korapay, a licensed payment service provider. ChinaInNigeria never stores your card details.</li>
                    <li>Your payment is held in escrow — see our <Link href={route('legal.customer-protection')}>Customer Protection</Link> page — and is only released to the vendor once your order is confirmed delivered or the return window lapses without a dispute.</li>
                    <li>Stock, pricing, and product availability can change at any time before an order is placed; once paid, an order is confirmed at the price shown at checkout.</li>
                </Ul>
            </Section>

            <Section title="5. Shipping &amp; fulfillment">
                <P>
                    Each vendor sets and is responsible for their own shipping cost and delivery timelines shown
                    at checkout. A single order spanning multiple vendors is split into one shipment per vendor,
                    and you can track each one independently from your order page or our public
                    {' '}<Link href={route('track-order.index')}>Track Order</Link> page.
                </P>
            </Section>

            <Section title="6. Cancellations, returns &amp; refunds">
                <P>
                    See our dedicated <Link href={route('legal.return-policy')}>Return Policy</Link> for eligible
                    reasons, the return window, and refund timelines.
                </P>
            </Section>

            <Section title="7. Prohibited conduct">
                <Ul>
                    <li>Listing counterfeit, stolen, illegal, or unsafe goods.</li>
                    <li>Attempting to pay a vendor outside the Platform to avoid escrow protection or platform fees.</li>
                    <li>Submitting fraudulent orders, chargebacks, or dispute claims.</li>
                    <li>Scraping, reverse-engineering, or interfering with the Platform's normal operation.</li>
                </Ul>
                <P>Violating any of the above may result in suspension or termination of your account.</P>
            </Section>

            <Section title="8. Intellectual property">
                <P>
                    The ChinaInNigeria name, logo, and platform software are our property. Product photos and
                    descriptions belong to the vendor who listed them, or their own suppliers/manufacturers.
                </P>
            </Section>

            <Section title="9. Limitation of liability">
                <P>
                    ChinaInNigeria facilitates the marketplace and payment escrow but is not the manufacturer or
                    seller of vendor products. To the fullest extent permitted by Nigerian law, our liability for
                    any claim arising from a transaction is limited to the amount actually paid for the order in
                    question.
                </P>
            </Section>

            <Section title="10. Governing law">
                <P>These Terms are governed by the laws of the Federal Republic of Nigeria.</P>
            </Section>

            <Section title="11. Changes to these Terms">
                <P>
                    We may update these Terms from time to time. Continued use of the Platform after an update
                    constitutes acceptance of the revised Terms.
                </P>
            </Section>

            <Section title="12. Contact">
                <P>Questions about these Terms can be sent to <a href="mailto:support@chinainnigeria.com">support@chinainnigeria.com</a>.</P>
            </Section>
        </LegalLayout>
    );
}
