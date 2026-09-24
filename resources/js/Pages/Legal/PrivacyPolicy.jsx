import LegalLayout from '@/Layouts/LegalLayout';
import { Section, P, Ul } from '@/Components/Legal/LegalContent';

export default function PrivacyPolicy() {
    return (
        <LegalLayout title="Privacy Policy" updatedAt="22 September 2026">
            <Section title="1. What we collect">
                <Ul>
                    <li><strong>Account information</strong>: name, email, phone number, password (stored hashed, never in plain text).</li>
                    <li><strong>Order information</strong>: items purchased, shipping address, order history, reviews you leave.</li>
                    <li><strong>Vendor information</strong> (for vendor accounts): business details, verification documents, bank account details used for escrow payouts.</li>
                    <li><strong>Payment references</strong>: we store a payment reference and status from Korapay, our payment processor — we never receive or store your card number, CVV, or bank PIN. That data is handled entirely by Korapay under its own PCI‑DSS‑compliant systems.</li>
                    <li><strong>Usage data</strong>: pages visited, search terms, device/browser type, and cookies used to keep you logged in and remember your cart.</li>
                </Ul>
            </Section>

            <Section title="2. How we use it">
                <Ul>
                    <li>To process and fulfill your orders, including sharing your shipping details with the relevant vendor(s) for that order.</li>
                    <li>To operate escrow: verifying your identity when confirming receipt or reporting a problem, and verifying a vendor's bank details before releasing a payout.</li>
                    <li>To send transactional emails — order confirmations, shipping updates, invoices, payment receipts.</li>
                    <li>To detect and prevent fraud, abuse, and violations of our Terms &amp; Conditions.</li>
                    <li>To improve the Platform (e.g. understanding which categories or searches are popular).</li>
                </Ul>
            </Section>

            <Section title="3. Who we share it with">
                <Ul>
                    <li><strong>Vendors</strong> — only the shipping details and items needed to fulfill your specific order.</li>
                    <li><strong>Korapay</strong> — the minimum information needed to process a payment or escrow payout (name, email, amount).</li>
                    <li><strong>Service providers</strong> — hosting, email delivery, and analytics providers who process data on our behalf, under contract, and only as needed to provide their service.</li>
                    <li>We do not sell your personal data to anyone.</li>
                </Ul>
            </Section>

            <Section title="4. Cookies &amp; sessions">
                <P>
                    We use a session cookie to keep you logged in and remember your cart and language
                    preference. We do not use third-party advertising trackers.
                </P>
            </Section>

            <Section title="5. Data retention">
                <P>
                    We keep order and payment records for as long as legally required for tax, accounting, and
                    dispute-resolution purposes, even after you close your account. Account profile data is
                    deleted or anonymized within a reasonable period after account deletion, except where we're
                    required to retain it by law.
                </P>
            </Section>

            <Section title="6. Your rights">
                <P>You can, at any time:</P>
                <Ul>
                    <li>Update your name, email, and phone number from your Profile settings.</li>
                    <li>Request a copy of the personal data we hold about you.</li>
                    <li>Request deletion of your account, subject to our legal obligation to retain order/payment records.</li>
                </Ul>
                <P>To exercise these rights, email <a href="mailto:privacy@chinainnigeria.com">privacy@chinainnigeria.com</a>.</P>
            </Section>

            <Section title="7. Children">
                <P>The Platform is not directed at children under 18. We do not knowingly collect data from children.</P>
            </Section>

            <Section title="8. Changes to this policy">
                <P>We may update this Privacy Policy from time to time; material changes will be highlighted on this page.</P>
            </Section>
        </LegalLayout>
    );
}
