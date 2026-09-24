<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Services\Translation\AutoTranslateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TranslateController extends Controller
{
    public function __invoke(Request $request, AutoTranslateService $translator): JsonResponse
    {
        $validated = $request->validate([
            'text' => ['required', 'string', 'max:5000'],
            'from' => ['required', 'string', 'in:zh,en,fr'],
            'to' => ['required', 'array', 'min:1'],
            'to.*' => ['string', 'in:zh,en,fr', 'different:from'],
        ]);

        $result = $translator->translate(
            $validated['text'],
            $validated['from'],
            array_values(array_unique($validated['to'])),
        );

        return response()->json([
            'translations' => $result['translations'],
            'failed' => $result['failed'],
        ]);
    }
}
