import ApplicationLogo from '@/Components/ApplicationLogo';
import Checkbox from '@/Components/Checkbox';
import InputError from '@/Components/InputError';
import PhoneInput from '@/Components/PhoneInput';
import EyeToggleButton from '@/Components/EyeToggleButton';
import { useTranslation } from '@/hooks/useTranslation';
import { Head, Link, useForm } from '@inertiajs/react';
import { useState } from 'react';

export default function Login({ status, canResetPassword }) {
    const { __ } = useTranslation();
    const [loginMethod, setLoginMethod] = useState('email');
    const [passwordVisible, setPasswordVisible] = useState(false);

    const { data, setData, post, processing, errors, reset } = useForm({
        login_method: 'email',
        identifier: '',
        password: '',
        remember: false,
    });

    const switchMethod = (method) => {
        setLoginMethod(method);
        setData({
            login_method: method,
            identifier: '',
            password: data.password,
            remember: data.remember,
        });
    };

    const submit = (e) => {
        e.preventDefault();

        post(route('login'), {
            onFinish: () => reset('password'),
        });
    };

    return (
        <div className="flex min-h-screen flex-col items-center bg-neutral-50 px-4 py-10 sm:justify-center sm:py-16">
            <Head title="Log in" />

            <Link href="/" className="mb-8">
                <ApplicationLogo className="h-20 w-auto sm:h-24 md:h-28" />
            </Link>

            <div className="w-full max-w-md rounded-2xl bg-white p-6 shadow-xl sm:p-8">
                {status && (
                    <div className="mb-4 rounded-lg bg-green-50 px-3 py-2 text-sm font-medium text-green-600">
                        {status}
                    </div>
                )}

                {/* Method tabs */}
                <div className="mb-6 grid grid-cols-2 gap-1 rounded-lg bg-neutral-100 p-1">
                    <button
                        type="button"
                        onClick={() => switchMethod('email')}
                        className={`rounded-md py-2 text-sm font-semibold transition ${
                            loginMethod === 'email'
                                ? 'bg-white text-orange-600 shadow-sm'
                                : 'text-gray-500 hover:text-gray-700'
                        }`}
                    >
                        {__('Email')}
                    </button>
                    <button
                        type="button"
                        onClick={() => switchMethod('phone')}
                        className={`rounded-md py-2 text-sm font-semibold transition ${
                            loginMethod === 'phone'
                                ? 'bg-white text-orange-600 shadow-sm'
                                : 'text-gray-500 hover:text-gray-700'
                        }`}
                    >
                        {__('Phone Number')}
                    </button>
                </div>

                <form onSubmit={submit}>
                    <div>
                        <label
                            htmlFor="identifier"
                            className="mb-1 block text-sm font-medium text-gray-700"
                        >
                            {loginMethod === 'email'
                                ? __('Email')
                                : __('Phone Number')}
                        </label>

                        {loginMethod === 'email' ? (
                            <input
                                id="identifier"
                                type="email"
                                name="identifier"
                                value={data.identifier}
                                autoComplete="username"
                                autoFocus
                                placeholder="you@example.com"
                                className="block w-full rounded-lg border-0 bg-neutral-100 px-4 py-3 text-sm text-gray-900 placeholder:text-gray-400 focus:bg-white focus:ring-2 focus:ring-orange-500"
                                onChange={(e) =>
                                    setData('identifier', e.target.value)
                                }
                            />
                        ) : (
                            <PhoneInput
                                id="identifier"
                                value={data.identifier}
                                autoFocus
                                onChange={(phone) => setData('identifier', phone)}
                            />
                        )}

                        <InputError
                            message={errors.identifier}
                            className="mt-2"
                        />
                    </div>

                    <div className="mt-4">
                        <label
                            htmlFor="password"
                            className="mb-1 block text-sm font-medium text-gray-700"
                        >
                            {__('Password')}
                        </label>

                        <div className="relative">
                            <input
                                id="password"
                                type={passwordVisible ? 'text' : 'password'}
                                name="password"
                                value={data.password}
                                autoComplete="current-password"
                                className="block w-full rounded-lg border-0 bg-neutral-100 px-4 py-3 pr-11 text-base text-gray-900 focus:bg-white focus:ring-2 focus:ring-orange-500"
                                onChange={(e) =>
                                    setData('password', e.target.value)
                                }
                            />
                            <EyeToggleButton visible={passwordVisible} onToggle={() => setPasswordVisible((v) => !v)} />
                        </div>

                        <InputError
                            message={errors.password}
                            className="mt-2"
                        />
                    </div>

                    <div className="mt-4 flex items-center justify-between">
                        <label className="flex items-center">
                            <Checkbox
                                name="remember"
                                checked={data.remember}
                                onChange={(e) =>
                                    setData('remember', e.target.checked)
                                }
                            />
                            <span className="ms-2 text-sm text-gray-600">
                                Remember me
                            </span>
                        </label>

                        {canResetPassword && (
                            <Link
                                href={route('password.request')}
                                className="text-sm text-gray-500 underline hover:text-gray-800"
                            >
                                Forgot your password?
                            </Link>
                        )}
                    </div>

                    <button
                        type="submit"
                        disabled={processing}
                        className="mt-6 w-full rounded-lg bg-orange-600 px-4 py-3.5 text-base font-bold text-white transition hover:bg-orange-700 focus:outline-none focus:ring-2 focus:ring-orange-500 focus:ring-offset-2 disabled:opacity-50"
                    >
                        {__('Login')}
                    </button>
                </form>

                <p className="mt-6 text-center text-sm text-gray-600">
                    New here?{' '}
                    <Link
                        href={route('register')}
                        className="font-bold text-orange-600 hover:text-orange-700"
                    >
                        Free registration
                    </Link>
                </p>

                <p className="mt-2 text-center text-xs text-gray-400">
                    Want to sell instead?{' '}
                    <Link
                        href={route('vendor.register')}
                        className="font-medium text-gray-500 underline hover:text-gray-700"
                    >
                        Register as a vendor
                    </Link>
                </p>
            </div>
        </div>
    );
}
