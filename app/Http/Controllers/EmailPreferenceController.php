<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The "manage email preferences / unsubscribe" link every email footer carries (see
 * resources/views/vendor/mail/html/footer.blade.php). Only the four Marketing\* notification
 * classes actually check this flag (via their via() methods) — transactional mail (orders, escrow,
 * security, support) keeps sending regardless, since a buyer/vendor can't opt out of updates about
 * their own account or money without contacting support.
 */
class EmailPreferenceController extends Controller
{
    public function edit(Request $request): Response
    {
        return Inertia::render('Account/EmailPreferences', [
            'marketingOptOut' => (bool) $request->user()->marketing_opt_out,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'marketing_opt_out' => ['required', 'boolean'],
        ]);

        $request->user()->update(['marketing_opt_out' => $validated['marketing_opt_out']]);

        return back()->with('success', 'Email preferences updated.');
    }
}
