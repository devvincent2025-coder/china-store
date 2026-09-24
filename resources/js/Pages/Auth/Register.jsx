import ApplicationLogo from '@/Components/ApplicationLogo';
import InputError from '@/Components/InputError';
import EyeToggleButton from '@/Components/EyeToggleButton';
import { useTranslation } from '@/hooks/useTranslation';
import { Head, Link, useForm } from '@inertiajs/react';
import { useState } from 'react';

/**
 * Buyer signup, step 1 of 4 — just email + password. Name and phone are collected later, once the
 * email is confirmed (see Auth\RegisteredUserController's class doc comment for the full flow).
 */
export default function Register() {
    const { __ } = useTranslation();
    const [passwordVisible, setPasswordVisible] = useState(false);
    const [confirmVisible, setConfirmVisible] = useState(false);

    const { data, setData, post, processing, errors, reset } = useForm({
        email: '',
        password: '',
        password_confirmation: '',
    });

    const submit = (e) => {
        e.preventDefault();

        post(route('register'), {
            onFinish: () => reset('password', 'password_confirmation'),
        });
    };

    return (
        <div className="flex min-h-screen flex-col items-center bg-neutral-50 px-4 py-10 sm:justify-center sm:py-16">
            <Head title="Register" />

            <Link href="/" className="mb-8">
                <ApplicationLogo className="h-20 w-auto sm:h-24 md:h-28" />
            </Link>

            <div className="w-full max-w-md rounded-2xl bg-white p-6 shadow-xl sm:p-8">
                <h1 className="font-display mb-1 text-center text-xl font-bold text-gray-900">
                    {__('Create your account')}
                </h1>
                <p className="mb-6 text-center text-sm text-gray-500">
                    {__("We'll email you a code to confirm it's really you.")}
                </p>

                <form onSubmit={submit}>
                    <div>
                        <label htmlFor="email" className="mb-1 block text-sm font-medium text-gray-700">
                            {__('Email')}
                        </label>

                        <input
                            id="email"
                            type="email"
                            name="email"
                            value={data.email}
                            autoComplete="username"
                            autoFocus
                            placeholder="you@example.com"
                            className="block w-full rounded-lg border-0 bg-neutral-100 px-4 py-3 text-sm text-gray-900 placeholder:text-gray-400 focus:bg-white focus:ring-2 focus:ring-orange-500"
                            onChange={(e) => setData('email', e.target.value)}
                            required
                        />

                        <InputError message={errors.email} className="mt-2" />
                    </div>

                    <div className="mt-4">
                        <label htmlFor="password" className="mb-1 block text-sm font-medium text-gray-700">
                            {__('Password')}
                        </label>

                        <div className="relative">
                            <input
                                id="password"
                                type={passwordVisible ? 'text' : 'password'}
                                name="password"
                                value={data.password}
                                autoComplete="new-password"
                                className="block w-full rounded-lg border-0 bg-neutral-100 px-4 py-3 pr-11 text-base text-gray-900 focus:bg-white focus:ring-2 focus:ring-orange-500"
                                onChange={(e) => setData('password', e.target.value)}
                                required
                            />
                            <EyeToggleButton visible={passwordVisible} onToggle={() => setPasswordVisible((v) => !v)} />
                        </div>

                        <InputError message={errors.password} className="mt-2" />
                    </div>

                    <div className="mt-4">
                        <label htmlFor="password_confirmation" className="mb-1 block text-sm font-medium text-gray-700">
                            {__('Confirm Password')}
                        </label>

                        <div className="relative">
                            <input
                                id="password_confirmation"
                                type={confirmVisible ? 'text' : 'password'}
                                name="password_confirmation"
                                value={data.password_confirmation}
                                autoComplete="new-password"
                                className="block w-full rounded-lg border-0 bg-neutral-100 px-4 py-3 pr-11 text-base text-gray-900 focus:bg-white focus:ring-2 focus:ring-orange-500"
                                onChange={(e) => setData('password_confirmation', e.target.value)}
                                required
                            />
                            <EyeToggleButton visible={confirmVisible} onToggle={() => setConfirmVisible((v) => !v)} />
                        </div>

                        <InputError message={errors.password_confirmation} className="mt-2" />
                    </div>

                    <p className="mt-4 text-center text-xs text-gray-500">
                        {__('By creating an account, you agree to our')}{' '}
                        <Link href={route('legal.terms')} className="font-semibold text-orange-600 hover:underline">{__('Terms & Conditions')}</Link>
                        {' '}{__('and')}{' '}
                        <Link href={route('legal.privacy')} className="font-semibold text-orange-600 hover:underline">{__('Privacy Policy')}</Link>.
                    </p>

                    <button
                        type="submit"
                        disabled={processing}
                        className="mt-4 w-full rounded-lg bg-orange-600 px-4 py-3.5 text-base font-bold text-white transition hover:bg-orange-700 focus:outline-none focus:ring-2 focus:ring-orange-500 focus:ring-offset-2 disabled:opacity-50"
                    >
                        {__('Create Account')}
                    </button>
                </form>

                <p className="mt-6 text-center text-sm text-gray-600">
                    {__('Already have an account?')}{' '}
                    <Link href={route('login')} className="font-bold text-orange-600 hover:text-orange-700">
                        {__('Log in')}
                    </Link>
                </p>
            </div>
        </div>
    );
}
