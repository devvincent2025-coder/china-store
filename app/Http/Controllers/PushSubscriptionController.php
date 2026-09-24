<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Stores/removes a browser's push subscription (endpoint + keys from the PushManager API) against
 * the logged-in user — see resources/js/utils/pushNotifications.js for the frontend half.
 */
class PushSubscriptionController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'endpoint' => ['required', 'string'],
            'keys.p256dh' => ['required', 'string'],
            'keys.auth' => ['required', 'string'],
        ]);

        $request->user()->updatePushSubscription(
            endpoint: $validated['endpoint'],
            key: $validated['keys']['p256dh'],
            token: $validated['keys']['auth'],
        );

        return back();
    }

    public function destroy(Request $request): RedirectResponse
    {
        $validated = $request->validate(['endpoint' => ['required', 'string']]);

        $request->user()->deletePushSubscription($validated['endpoint']);

        return back();
    }
}
