/**
 * Variant selection helpers shared by the product page (Pages/Products/Show.jsx) and the
 * product-card quick-add modal (Components/AddToCartModal.jsx), so both resolve a buyer's
 * option picks to the same variant the same way.
 */

/**
 * Given the buyer's current { [optionId]: selectedValueId } selection and the product's full
 * variant list, finds the variant whose optionValues set exactly matches the selection.
 */
export function resolveVariant(variants, options, selection) {
    if (!options || options.length === 0) return null;

    const selectedIds = options.map((opt) => selection[opt.id]).filter((id) => id != null);
    if (selectedIds.length !== options.length) return null;

    return (
        (variants ?? []).find((variant) => {
            // Eloquent serializes relation keys as snake_case regardless of the PHP method's
            // camelCase name, so the loaded `optionValues` relation arrives as `option_values`.
            const variantIds = (variant.option_values ?? []).map((ov) => ov.id);
            if (variantIds.length !== selectedIds.length) return false;
            return selectedIds.every((id) => variantIds.includes(id));
        }) ?? null
    );
}

export function defaultSelection(options) {
    const selection = {};
    (options ?? []).forEach((option) => {
        if (option.values?.length > 0) {
            selection[option.id] = option.values[0].id;
        }
    });
    return selection;
}
