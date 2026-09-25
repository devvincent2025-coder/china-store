import { Head, Link, usePage } from '@inertiajs/react';
import StorefrontLayout from '@/Layouts/StorefrontLayout';
import AvatarUploader from '@/Components/AvatarUploader';
import DeleteUserForm from './Partials/DeleteUserForm';
import TwoFactorAuthenticationForm from './Partials/TwoFactorAuthenticationForm';
import UpdatePasswordForm from './Partials/UpdatePasswordForm';
import UpdateProfileInformationForm from './Partials/UpdateProfileInformationForm';
import DefaultAddressForm from './Partials/DefaultAddressForm';
import ConnectedAppsPanel from './Partials/ConnectedAppsPanel';
import { useTranslation } from '@/hooks/useTranslation';

function Card({ children }) {
    return <div className="rounded-lg border border-neutral-200 bg-white p-5 sm:p-6">{children}</div>;
}

export default function Edit({ mustVerifyEmail, status, twoFactor, defaultShippingAddress, connectedApps }) {
    const { __ } = useTranslation();
    const { auth } = usePage().props;
    const user = auth?.user;

    return (
        <StorefrontLayout>
            <Head title={__('My Account')} />

            <div className="mx-auto max-w-3xl px-4 py-8 sm:px-6 lg:px-8">
                <h1 className="mb-6 text-xl font-bold text-neutral-900">{__('My Account')}</h1>

                <div className="space-y-6">
                    <Card>
                        <h2 className="mb-4 text-sm font-semibold text-neutral-800">{__('Profile Photo')}</h2>
                        <AvatarUploader user={user} />
                    </Card>

                    <Card>
                        <UpdateProfileInformationForm mustVerifyEmail={mustVerifyEmail} status={status} />
                    </Card>

                    <Card>
                        <DefaultAddressForm defaultShippingAddress={defaultShippingAddress} />
                    </Card>

                    <Card>
                        <div className="flex items-center justify-between gap-4">
                            <div>
                                <h2 className="text-sm font-semibold text-neutral-800">{__('Email Preferences')}</h2>
                                <p className="mt-1 text-xs text-neutral-500">{__('Choose which marketing emails you receive.')}</p>
                            </div>
                            <Link
                                href={route('email-preferences.edit')}
                                className="shrink-0 rounded-md border border-neutral-300 px-4 py-2 text-sm font-semibold text-neutral-700 hover:bg-neutral-50"
                            >
                                {__('Manage')}
                            </Link>
                        </div>
                    </Card>

                    <Card>
                        <ConnectedAppsPanel connectedApps={connectedApps} />
                    </Card>

                    <Card>
                        <UpdatePasswordForm />
                    </Card>

                    <Card>
                        <TwoFactorAuthenticationForm twoFactor={twoFactor} />
                    </Card>

                    <Card>
                        <div className="flex items-center justify-between gap-4">
                            <div>
                                <h2 className="text-sm font-semibold text-neutral-800">{__('Log Out')}</h2>
                                <p className="mt-1 text-xs text-neutral-500">{__('Sign out of your account on this device.')}</p>
                            </div>
                            <Link
                                href={route('logout')}
                                method="post"
                                as="button"
                                className="shrink-0 rounded-md border border-neutral-300 px-4 py-2 text-sm font-semibold text-neutral-700 hover:bg-neutral-50"
                            >
                                {__('Log Out')}
                            </Link>
                        </div>
                    </Card>

                    <Card>
                        <DeleteUserForm />
                    </Card>
                </div>
            </div>
        </StorefrontLayout>
    );
}
