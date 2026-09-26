import { Head, Link, useForm, usePage } from '@inertiajs/react';
import StorefrontLayout from '@/Layouts/StorefrontLayout';
import OpenShopIllustration from '@/Components/OpenShopIllustration';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import TextInput from '@/Components/TextInput';
import PhoneInput from '@/Components/PhoneInput';
import { useTranslation } from '@/hooks/useTranslation';

function BecomeVendorForm({ user }) {
    const { __ } = useTranslation();
    const { data, setData, post, processing, errors } = useForm({
        business_name: '',
        phone: user.phone ?? '',
    });

    const submit = (e) => {
        e.preventDefault();
        post(route('open-shop.become-vendor'));
    };

    return (
        <div className="max-w-md rounded-lg bg-tb-soft px-4 py-4">
            <p className="text-sm text-tb-text">
                {__("You're logged in as {name} — sell from this same account, no separate signup needed.", { name: user.name })}
            </p>

            <form onSubmit={submit} className="mt-4 space-y-4">
                <div>
                    <InputLabel htmlFor="business_name" value={__('Business / Store Name')} />
                    <TextInput
                        id="business_name"
                        className="mt-1 block w-full"
                        value={data.business_name}
                        onChange={(e) => setData('business_name', e.target.value)}
                        autoFocus
                        required
                    />
                    <InputError message={errors.business_name} className="mt-1" />
                </div>

                {/* Only asked for on the rare account with no phone on file yet — see
                    Auth\BecomeVendorController's doc comment. */}
                {!user.phone && (
                    <div>
                        <InputLabel htmlFor="become-vendor-phone" value={__('Phone Number')} />
                        <PhoneInput
                            id="become-vendor-phone"
                            value={data.phone}
                            onChange={(phone) => setData('phone', phone)}
                            className="mt-1"
                        />
                        <InputError message={errors.phone} className="mt-1" />
                    </div>
                )}

                <button
                    type="submit"
                    disabled={processing}
                    className="inline-flex rounded-full bg-tb px-6 py-2.5 text-sm font-semibold text-white hover:bg-tb-hover disabled:opacity-50"
                >
                    {processing ? __('Setting up…') : __('Start Selling')}
                </button>
            </form>
        </div>
    );
}

export default function Index() {
    const { auth } = usePage().props;
    const user = auth?.user;
    const { __ } = useTranslation();

    return (
        <StorefrontLayout>
            <Head title={__('Open a shop')} />

            <div className="mx-auto max-w-marketplace px-2 py-10 sm:px-3">
                <div className="overflow-hidden rounded-[12px] bg-tb-card">
                    <div className="grid gap-8 p-8 md:grid-cols-2 md:p-12">
                        <div>
                            <p className="font-display text-sm font-semibold uppercase tracking-wide text-tb">
                                {__('Sell on ChinaInNigeria')}
                            </p>
                            <h1 className="font-display mt-2 text-3xl font-bold text-tb-text sm:text-4xl">
                                {__('Open a shop and reach buyers across Nigeria')}
                            </h1>
                            <p className="mt-4 text-[15px] leading-relaxed text-tb-muted">
                                {__('List products from your Chinese-run store, upload photos, set prices in Naira, and get paid through secure multi-vendor checkout.')}
                            </p>
                            <ul className="mt-6 space-y-3 text-sm text-tb-text">
                                {[
                                    __('Verified vendor badge after document review'),
                                    __('Product images, variants, coupons & store banners'),
                                    __('Orders dashboard and payouts'),
                                ].map((item) => (
                                    <li key={item} className="flex items-start gap-2.5">
                                        <span className="mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-tb-green/15 text-tb-green">
                                            <svg className="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden>
                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2.5} d="M5 13l4 4L19 7" />
                                            </svg>
                                        </span>
                                        <span>{item}</span>
                                    </li>
                                ))}
                            </ul>

                            <div className="mt-8">
                                {user ? (
                                    // Reaching this page as a logged-in user always means a BUYER
                                    // account (OpenShopController redirects an existing vendor
                                    // straight to their dashboard before this ever renders) — see
                                    // Auth\BecomeVendorController's doc comment for why this adds a
                                    // vendor record to the SAME account rather than requiring a
                                    // brand new one.
                                    <BecomeVendorForm user={user} />
                                ) : (
                                    <div className="flex flex-wrap gap-3">
                                        <Link
                                            href={route('vendor.register')}
                                            className="inline-flex rounded-full bg-tb px-6 py-2.5 text-sm font-semibold text-white hover:bg-tb-hover"
                                        >
                                            {__('Create for free')}
                                        </Link>
                                        <Link
                                            href={route('login')}
                                            className="inline-flex rounded-full border border-tb px-6 py-2.5 text-sm font-semibold text-tb hover:bg-tb-soft"
                                        >
                                            {__('Log in')}
                                        </Link>
                                    </div>
                                )}
                            </div>
                        </div>

                        <div className="relative flex min-h-[280px] flex-col overflow-hidden rounded-[16px] bg-gradient-to-br from-tb to-[#ff8a3d] p-6 text-white sm:p-8 md:min-h-[340px]">
                            <div className="relative z-10 max-w-[240px]">
                                <p className="font-display text-2xl font-bold sm:text-3xl">{__('Start selling today')}</p>
                                <p className="mt-3 text-sm leading-relaxed text-white/90">
                                    {__('Join verified vendors already shipping from Ibadan, Ogun, Lagos, Abuja, and beyond.')}
                                </p>
                            </div>

                            <OpenShopIllustration className="pointer-events-none absolute -bottom-2 -right-4 h-[78%] w-auto max-w-[110%] sm:-right-2 sm:h-[85%] md:bottom-0" />
                        </div>
                    </div>
                </div>
            </div>
        </StorefrontLayout>
    );
}
