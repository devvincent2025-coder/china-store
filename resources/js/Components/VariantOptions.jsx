import { t } from '@/utils/translatable';
import { isColorOption, resolveColorSwatch } from '@/utils/colorSwatch';

const LIGHT_SWATCHES = ['#ffffff', '#e8d5b7', '#eab308', '#c0c0c0'];

/**
 * Option pickers for a product's variants: colour options render as swatches, everything else
 * as pill chips. Shared by the product page and the product-card quick-add modal.
 */
export default function VariantOptions({ options = [], selection = {}, onSelect }) {
    return (
        <div className="space-y-4">
            {options.map((option) => {
                const asSwatches = isColorOption(option.name);
                const selectedValue = option.values.find((v) => v.id === selection[option.id]);

                return (
                    <div key={option.id}>
                        <p className="mb-2 flex items-baseline gap-1.5 text-[13px] font-semibold text-tb-ink">
                            {t(option.name)}
                            {selectedValue && <span className="font-normal text-neutral-500">· {t(selectedValue.value)}</span>}
                        </p>
                        <div className="flex flex-wrap items-center gap-2">
                            {option.values.map((value) => {
                                const selected = selection[option.id] === value.id;
                                const swatch = asSwatches ? resolveColorSwatch(value.value) : null;
                                const label = t(value.value);

                                if (swatch) {
                                    return (
                                        <button
                                            type="button"
                                            key={value.id}
                                            onClick={() => onSelect(option.id, value.id)}
                                            title={label}
                                            aria-label={label}
                                            aria-pressed={selected}
                                            className={`relative h-9 w-9 rounded-full transition ${
                                                selected ? 'scale-110 ring-2 ring-tb ring-offset-2' : 'ring-1 ring-black/10 hover:ring-tb/60'
                                            }`}
                                            style={{ backgroundColor: swatch }}
                                        >
                                            {LIGHT_SWATCHES.includes(swatch.toLowerCase()) && (
                                                <span className="pointer-events-none absolute inset-0 rounded-full border border-neutral-200" />
                                            )}
                                        </button>
                                    );
                                }

                                return (
                                    <button
                                        type="button"
                                        key={value.id}
                                        onClick={() => onSelect(option.id, value.id)}
                                        aria-pressed={selected}
                                        className={`rounded-full px-4 py-2 text-[13px] font-medium transition ${
                                            selected
                                                ? 'bg-tb-ink text-white shadow-soft'
                                                : 'bg-neutral-100 text-tb-text hover:bg-tb-soft hover:text-tb'
                                        }`}
                                    >
                                        {label}
                                    </button>
                                );
                            })}
                        </div>
                    </div>
                );
            })}
        </div>
    );
}
