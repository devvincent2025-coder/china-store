<?php

namespace App\Http\Controllers;

use App\Services\Product\PriceComparisonService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ComparePricesController extends Controller
{
    public function __construct(private readonly PriceComparisonService $comparisons)
    {
    }

    public function index(Request $request): Response
    {
        $term = trim((string) $request->string('q'));

        return Inertia::render('ComparePrices/Index', [
            'query' => $term,
            'groups' => $term !== '' ? $this->comparisons->searchGroups($term) : [],
        ]);
    }
}
