<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Models\Banner;
use App\Services\Storage\SupabaseStorageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BannerController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('Vendor/Banners/Index', [
            'banners' => $request->user()->vendor->banners()->orderBy('sort_order')->get(),
        ]);
    }

    public function store(Request $request, SupabaseStorageService $storage): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['nullable', 'array'],
            'image' => ['required', 'image', 'max:10240'],
            'link_url' => ['nullable', 'url'],
            'sort_order' => ['nullable', 'integer'],
        ]);

        $uploaded = $storage->uploadPublic($validated['image']->getRealPath(), 'vendor-banners');

        $request->user()->vendor->banners()->create([
            'title' => $validated['title'] ?? null,
            'image_url' => $uploaded['url'],
            'storage_path' => $uploaded['path'],
            'link_url' => $validated['link_url'] ?? null,
            'placement' => 'store',
            'sort_order' => $validated['sort_order'] ?? 0,
        ]);

        return back()->with('success', 'Banner uploaded.');
    }

    public function destroy(Request $request, Banner $banner, SupabaseStorageService $storage): RedirectResponse
    {
        $this->authorize('delete', $banner);

        if ($banner->storage_path) {
            $storage->deletePublic($banner->storage_path);
        }

        $banner->delete();

        return back();
    }
}
