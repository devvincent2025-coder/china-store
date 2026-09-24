<?php

namespace App\Http\Controllers;

use App\Services\Storage\SupabaseStorageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Shared avatar upload for any authenticated account — buyer, vendor, or admin. Stored in the same
 * public Supabase bucket product photos use, since a profile picture is just as safe to serve
 * publicly. Accepts either a picked file or a camera capture (CameraCaptureModal produces a real
 * File either way, so the backend doesn't need to know which).
 */
class AvatarController extends Controller
{
    public function store(Request $request, SupabaseStorageService $storage): RedirectResponse
    {
        $validated = $request->validate([
            'avatar' => ['required', 'image', 'max:5120'],
        ]);

        $user = $request->user();

        if ($user->avatar_path) {
            $storage->deletePublic($user->avatar_path);
        }

        $uploaded = $storage->uploadPublic($validated['avatar']->getRealPath(), 'avatars');

        $user->update([
            'avatar_path' => $uploaded['path'],
            'avatar_url' => $uploaded['url'],
        ]);

        return back()->with('success', 'Avatar updated.');
    }

    public function destroy(Request $request, SupabaseStorageService $storage): RedirectResponse
    {
        $user = $request->user();

        if ($user->avatar_path) {
            $storage->deletePublic($user->avatar_path);
        }

        $user->update(['avatar_path' => null, 'avatar_url' => null]);

        return back()->with('success', 'Avatar removed.');
    }
}
