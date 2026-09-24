import InputError from '@/Components/InputError';
import TextInput from '@/Components/TextInput';
import { useTranslation } from '@/hooks/useTranslation';

/**
 * Repeater for a vendor's bulk-pricing quantity breaks — "buy 10-49 at ₦900, 50+ at ₦800". Used
 * both at the product level (Create.jsx, and Edit.jsx for a product with no variants) and per
 * variant (Edit.jsx's VariantsTable, one editor per row). The lowest tier's "From qty" IS this
 * product/variant's minimum order quantity — leaving it blank/empty entirely means no MOQ, no
 * bulk pricing, exactly like today.
 *
 * Validation (no gaps/overlaps, only the last tier may leave "To qty" blank) happens server-side
 * in ProductPriceTierSyncService — this only handles shape, matching the "staged, validate on
 * submit" pattern already used for options/variants elsewhere in these forms.
 */
export default function TierPricingEditor({ tiers, onChange, errors = {}, errorKeyPrefix = 'price_tiers' }) {
    const { __ } = useTranslation();

    const updateTier = (index, next) => {
        onChange(tiers.map((tier, i) => (i === index ? next : tier)));
    };

    const addTier = () => {
        const last = tiers[tiers.length - 1];
        onChange([
            ...tiers,
            { min_quantity: last?.max_quantity ? Number(last.max_quantity) + 1 : '', max_quantity: '', price: '' },
        ]);
    };

    const removeTier = (index) => onChange(tiers.filter((_, i) => i !== index));

    return (
        <div>
            {tiers.length > 0 && (
                <div className="space-y-2">
                    <div className="hidden grid-cols-[1fr_1fr_1fr_auto] gap-2 text-xs font-semibold uppercase text-neutral-500 sm:grid">
                        <span>{__('From qty')}</span>
                        <span>{__('To qty (blank = no limit)')}</span>
                        <span>{__('Price per unit (₦)')}</span>
                        <span />
                    </div>
                    {tiers.map((tier, index) => (
                        <div key={index} className="grid grid-cols-2 gap-2 sm:grid-cols-[1fr_1fr_1fr_auto] sm:items-start">
                            <div>
                                <TextInput
                                    type="number"
                                    min={1}
                                    value={tier.min_quantity}
                                    onChange={(e) => updateTier(index, { ...tier, min_quantity: e.target.value })}
                                    className="w-full"
                                />
                                <InputError message={errors[`${errorKeyPrefix}.${index}.min_quantity`]} className="mt-1" />
                            </div>
                            <div>
                                <TextInput
                                    type="number"
                                    min={1}
                                    value={tier.max_quantity}
                                    onChange={(e) => updateTier(index, { ...tier, max_quantity: e.target.value })}
                                    placeholder={__('No limit')}
                                    className="w-full"
                                />
                                <InputError message={errors[`${errorKeyPrefix}.${index}.max_quantity`]} className="mt-1" />
                            </div>
                            <div>
                                <TextInput
                                    type="number"
                                    step="0.01"
                                    min={0}
                                    value={tier.price}
                                    onChange={(e) => updateTier(index, { ...tier, price: e.target.value })}
                                    className="w-full"
                                />
                                <InputError message={errors[`${errorKeyPrefix}.${index}.price`]} className="mt-1" />
                            </div>
                            <button
                                type="button"
                                onClick={() => removeTier(index)}
                                className="justify-self-start text-sm font-medium text-red-500 hover:underline sm:mt-2.5"
                            >
                                {__('Remove')}
                            </button>
                        </div>
                    ))}
                </div>
            )}

            {errors[errorKeyPrefix] && <p className="mt-2 text-sm text-red-600">{errors[errorKeyPrefix]}</p>}

            <button
                type="button"
                onClick={addTier}
                className="mt-3 rounded-md border border-neutral-300 px-3 py-1.5 text-sm font-semibold text-neutral-700 hover:bg-neutral-50"
            >
                {__('+ Add Tier')}
            </button>
            {tiers.length === 0 && (
                <span className="ml-3 text-xs text-neutral-400">
                    {__('Optional — skip this to sell at one flat price with no minimum order quantity.')}
                </span>
            )}
        </div>
    );
}
