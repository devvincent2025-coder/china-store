<?php

namespace App\Services\Product;

use App\Models\Product;
use App\Models\ProductOptionValue;
use App\Models\ProductVariant;
use Illuminate\Support\Facades\DB;

/**
 * Replaces a product's options/values wholesale with what the vendor submitted, then regenerates
 * the variant matrix (the cartesian product of every option's values) — creating new variants for
 * newly-possible combinations, preserving price/stock/sku on combinations that already existed, and
 * deleting variants whose combination no longer exists (e.g. a "Bale of 100" value was removed).
 *
 * A product with zero options has zero variants and is sold directly off its own price/stock — this
 * only runs when a vendor opts into packaging/color/etc selection for a product.
 */
class ProductOptionSyncService
{
    /**
     * @param  array<int, array{name: array, values: array<int, array>}>  $optionsPayload
     */
    public function sync(Product $product, array $optionsPayload): void
    {
        DB::transaction(function () use ($product, $optionsPayload) {
            // Options/values are wiped and recreated below, so IDs can't survive the round trip —
            // snapshot each existing variant's combination as ENGLISH label content instead, since
            // that's stable across the delete+recreate even if a vendor keeps the same wording.
            $existingVariantsByContentKey = $product->variants()
                ->with('optionValues.option')
                ->get()
                ->keyBy(fn (ProductVariant $variant) => $this->contentKey(
                    $variant->optionValues->map(fn (ProductOptionValue $v) => [
                        $v->option->getTranslation('name', 'en'),
                        $v->getTranslation('value', 'en'),
                    ])->all()
                ));

            $product->options()->delete(); // Cascades to product_option_values and the pivot table.

            if (empty($optionsPayload)) {
                $product->variants()->delete();

                return;
            }

            $valueIdsPerOption = [];

            foreach ($optionsPayload as $optionIndex => $optionData) {
                $option = $product->options()->create([
                    'name' => $optionData['name'],
                    'sort_order' => $optionIndex,
                ]);

                $optionNameEn = $option->getTranslation('name', 'en');
                $valueIds = [];

                foreach ($optionData['values'] as $valueIndex => $valueData) {
                    $value = $option->values()->create([
                        'value' => $valueData,
                        'sort_order' => $valueIndex,
                    ]);
                    // Pair each new value ID with its (option, value) English content for matching.
                    $valueIds[] = ['id' => $value->id, 'content' => [$optionNameEn, $value->getTranslation('value', 'en')]];
                }

                $valueIdsPerOption[] = $valueIds;
            }

            $combinations = $this->cartesianProduct($valueIdsPerOption);
            $keptVariantIds = [];

            foreach ($combinations as $combo) {
                $valueIds = array_column($combo, 'id');
                $contentKey = $this->contentKey(array_column($combo, 'content'));
                $existing = $existingVariantsByContentKey->get($contentKey);

                if ($existing) {
                    $existing->optionValues()->sync($valueIds);
                    $keptVariantIds[] = $existing->id;
                } else {
                    $variant = $product->variants()->create([
                        'price' => null,
                        'stock' => 0,
                    ]);
                    $variant->optionValues()->sync($valueIds);
                    $keptVariantIds[] = $variant->id;
                }
            }

            // Delete variants whose combination is no longer reachable (a value/option was removed).
            $product->variants()->whereNotIn('id', $keptVariantIds)->delete();
        });
    }

    /**
     * Order-independent key built from (option, value) English content pairs, so the same
     * combination matches regardless of the order options/values were submitted in.
     */
    private function contentKey(array $pairs): string
    {
        $normalized = array_map(fn ($pair) => strtolower(trim($pair[0])).'='.strtolower(trim($pair[1])), $pairs);
        sort($normalized);

        return implode('|', $normalized);
    }

    /**
     * @param  array<int, array<int, array{id: int, content: array}>>  $sets
     * @return array<int, array<int, array{id: int, content: array}>>
     */
    private function cartesianProduct(array $sets): array
    {
        return array_reduce($sets, function (array $carry, array $set) {
            $result = [];
            foreach ($carry as $combo) {
                foreach ($set as $value) {
                    $result[] = [...$combo, $value];
                }
            }

            return $result;
        }, [[]]);
    }
}
