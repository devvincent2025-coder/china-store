import { useForm } from '@inertiajs/react';
import { Transition } from '@headlessui/react';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import TextInput from '@/Components/TextInput';
import { useTranslation } from '@/hooks/useTranslation';

/**
 * Quick-edit shortcut for the default entry in the buyer's address book (see ShippingAddress) —
 * the full book (multiple saved addresses, add a new one) lives on the checkout page itself; this
 * form just lets a buyer fix their default without going through checkout to do it.
 */
export default function DefaultAddressForm({ defaultShippingAddress, className = '' }) {
    const { __ } = useTranslation();
    const { data, setData, patch, errors, processing, recentlySuccessful } = useForm({
        name: defaultShippingAddress?.name ?? '',
        phone: defaultShippingAddress?.phone ?? '',
        line1: defaultShippingAddress?.line1 ?? '',
        city: defaultShippingAddress?.city ?? '',
        state: defaultShippingAddress?.state ?? '',
    });

    const submit = (e) => {
        e.preventDefault();
        patch(route('profile.default-address.update'));
    };

    return (
        <section className={className}>
            <header>
                <h2 className="text-sm font-semibold text-neutral-800">{__('Default Shipping Address')}</h2>
                <p className="mt-1 text-xs text-neutral-500">
                    {__("Saved and prefilled automatically at checkout — you can still edit it on any order.")}
                </p>
            </header>

            <form onSubmit={submit} className="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <InputLabel htmlFor="address_name" value={__('Full Name')} />
                    <TextInput id="address_name" className="mt-1 block w-full" value={data.name} onChange={(e) => setData('name', e.target.value)} />
                    <InputError message={errors.name} className="mt-1" />
                </div>
                <div>
                    <InputLabel htmlFor="address_phone" value={__('Phone')} />
                    <TextInput id="address_phone" className="mt-1 block w-full" value={data.phone} onChange={(e) => setData('phone', e.target.value)} />
                    <InputError message={errors.phone} className="mt-1" />
                </div>
                <div className="sm:col-span-2">
                    <InputLabel htmlFor="address_line1" value={__('Address')} />
                    <TextInput id="address_line1" className="mt-1 block w-full" value={data.line1} onChange={(e) => setData('line1', e.target.value)} />
                    <InputError message={errors.line1} className="mt-1" />
                </div>
                <div>
                    <InputLabel htmlFor="address_city" value={__('City')} />
                    <TextInput id="address_city" className="mt-1 block w-full" value={data.city} onChange={(e) => setData('city', e.target.value)} />
                    <InputError message={errors.city} className="mt-1" />
                </div>
                <div>
                    <InputLabel htmlFor="address_state" value={__('State')} />
                    <TextInput id="address_state" className="mt-1 block w-full" value={data.state} onChange={(e) => setData('state', e.target.value)} />
                    <InputError message={errors.state} className="mt-1" />
                </div>

                <div className="flex items-center gap-4 sm:col-span-2">
                    <button
                        type="submit"
                        disabled={processing}
                        className="rounded-md bg-orange-600 px-5 py-2 text-sm font-semibold text-white hover:bg-orange-700 disabled:opacity-50"
                    >
                        {__('Save Address')}
                    </button>
                    <Transition show={recentlySuccessful} enter="transition ease-in-out" enterFrom="opacity-0" leave="transition ease-in-out" leaveTo="opacity-0">
                        <p className="text-sm text-green-600">{__('Saved.')}</p>
                    </Transition>
                </div>
            </form>
        </section>
    );
}
