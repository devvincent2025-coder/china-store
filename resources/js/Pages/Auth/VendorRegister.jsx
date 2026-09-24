import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import PasswordInput from '@/Components/PasswordInput';
import GuestLayout from '@/Layouts/GuestLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import { useTranslation } from '@/hooks/useTranslation';

export default function VendorRegister() {
    const { __ } = useTranslation();
    const { data, setData, post, processing, errors, reset } = useForm({
        name: '',
        email: '',
        phone: '',
        password: '',
        password_confirmation: '',
        business_name: '',
    });

    const submit = (e) => {
        e.preventDefault();

        post(route('vendor.register'), {
            onFinish: () => reset('password', 'password_confirmation'),
        });
    };

    return (
        <GuestLayout>
            <Head title="Become a Vendor" />

            <h1 className="mb-4 text-lg font-bold text-neutral-900">
                Sell on <span className="text-orange-600">ChinaInNigeria</span>
            </h1>
            <p className="mb-6 text-sm text-neutral-500">
                Create your vendor account, then complete onboarding (business profile, documents, bank details).
            </p>

            <form onSubmit={submit}>
                <div>
                    <InputLabel htmlFor="business_name" value={__('Business Name')} />
                    <TextInput
                        id="business_name"
                        name="business_name"
                        value={data.business_name}
                        className="mt-1 block w-full"
                        isFocused={true}
                        onChange={(e) => setData('business_name', e.target.value)}
                        required
                    />
                    <InputError message={errors.business_name} className="mt-2" />
                </div>

                <div className="mt-4">
                    <InputLabel htmlFor="name" value="Contact Name" />
                    <TextInput
                        id="name"
                        name="name"
                        value={data.name}
                        className="mt-1 block w-full"
                        autoComplete="name"
                        onChange={(e) => setData('name', e.target.value)}
                        required
                    />
                    <InputError message={errors.name} className="mt-2" />
                </div>

                <div className="mt-4">
                    <InputLabel htmlFor="email" value={__('Email')} />
                    <TextInput
                        id="email"
                        type="email"
                        name="email"
                        value={data.email}
                        className="mt-1 block w-full"
                        autoComplete="username"
                        onChange={(e) => setData('email', e.target.value)}
                        required
                    />
                    <InputError message={errors.email} className="mt-2" />
                </div>

                <div className="mt-4">
                    <InputLabel htmlFor="phone" value={__('Phone Number')} />
                    <TextInput
                        id="phone"
                        name="phone"
                        value={data.phone}
                        className="mt-1 block w-full"
                        autoComplete="tel"
                        onChange={(e) => setData('phone', e.target.value)}
                        required
                    />
                    <InputError message={errors.phone} className="mt-2" />
                </div>

                <div className="mt-4">
                    <InputLabel htmlFor="password" value={__('Password')} />
                    <PasswordInput
                        id="password"
                        name="password"
                        value={data.password}
                        className="mt-1 block w-full"
                        autoComplete="new-password"
                        onChange={(e) => setData('password', e.target.value)}
                        required
                    />
                    <InputError message={errors.password} className="mt-2" />
                </div>

                <div className="mt-4">
                    <InputLabel htmlFor="password_confirmation" value={__('Confirm Password')} />
                    <PasswordInput
                        id="password_confirmation"
                        name="password_confirmation"
                        value={data.password_confirmation}
                        className="mt-1 block w-full"
                        autoComplete="new-password"
                        onChange={(e) => setData('password_confirmation', e.target.value)}
                        required
                    />
                    <InputError message={errors.password_confirmation} className="mt-2" />
                </div>

                <p className="mt-4 text-xs text-neutral-500">
                    By creating a vendor account, you agree to our{' '}
                    <Link href={route('legal.terms')} className="font-semibold text-orange-600 hover:underline">Terms &amp; Conditions</Link>
                    {' '}and{' '}
                    <Link href={route('legal.vendor-protection')} className="font-semibold text-orange-600 hover:underline">Vendor Protection</Link>{' '}
                    policy, including how escrow payouts work.
                </p>

                <div className="mt-4 flex items-center justify-end">
                    <Link
                        href={route('register')}
                        className="rounded-md text-sm text-gray-600 underline hover:text-gray-900 focus:outline-none focus:ring-2 focus:ring-orange-500 focus:ring-offset-2"
                    >
                        Shopping instead?
                    </Link>

                    <PrimaryButton className="ms-4 !bg-orange-600 hover:!bg-orange-700" disabled={processing}>
                        Create Vendor Account
                    </PrimaryButton>
                </div>
            </form>
        </GuestLayout>
    );
}
