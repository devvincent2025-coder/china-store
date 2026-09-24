<?php

namespace App\Http\Controllers;

use App\Services\ImageSearch\VisualProductSearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ImageSearchController extends Controller
{
    public function __invoke(Request $request, VisualProductSearchService $search): JsonResponse
    {
        $validated = $request->validate([
            'image' => ['required', 'image', 'max:5120'],
        ]);

        $file = $validated['image'];
        $path = $file->getRealPath();

        $products = $search->search($path, 24);

        if ($products->isEmpty()) {
            return response()->json([
                'message' => 'No similar products found. Try another photo.',
                'product_ids' => [],
                'redirect' => route('products.index'),
            ], 422);
        }

        $ids = $products->pluck('id')->all();
        $request->session()->put('visual_search_ids', $ids);
        $request->session()->put('visual_search_at', now()->toIso8601String());

        return response()->json([
            'message' => 'Found '.$products->count().' similar products.',
            'product_ids' => $ids,
            'redirect' => route('products.index', ['visual' => 1]),
        ]);
    }
}
