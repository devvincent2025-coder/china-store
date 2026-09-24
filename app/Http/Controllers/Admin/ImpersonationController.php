<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminActivityLog;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Lets an admin log in AS a buyer or vendor to see exactly what they see and reproduce/resolve
 * their issue — the alternative (asking the customer to screen-share) doesn't scale for a
 * marketplace helpdesk.
 *
 * Because the admin panel authenticates on its own 'admin' guard (see AdminPanelProvider and
 * config/auth.php) while the storefront/vendor dashboard authenticate on 'web', logging the target
 * user into the 'web' guard here never touches the admin's own 'admin' guard session — the admin
 * was never "logged out" to begin with, so there's no session state to restore on the way back.
 * "Impersonating" is simply: both guards happen to have a user at once.
 */
class ImpersonationController extends Controller
{
    /**
     * Guarded at the route level by 'auth:admin' (see routes/web.php) — this checks the ADMIN
     * guard's currently-authenticated user, completely independent of whatever the 'web' guard
     * session (if any) on this same browser currently holds.
     */
    public function start(Request $request, User $user): RedirectResponse
    {
        $admin = Auth::guard('admin')->user();

        // Explicit 'web' guard on every check here — Laravel's own auth:admin middleware calls
        // Auth::shouldUse('admin') as soon as it authenticates this request, which rewrites
        // config('auth.defaults.guard') for the rest of it; Spatie's ambient hasRole()/can() resolve
        // their guard from that same config value, so an unqualified call here would silently look
        // for guard_name='admin' rows and find none (every role/permission is seeded as 'web').
        if (! $admin->hasRole('super_admin', 'web') && ! $admin->checkPermissionTo('impersonate_user', 'web')) {
            abort(403);
        }

        if ($user->hasRole('super_admin', 'web') || $user->checkPermissionTo('access_admin_panel', 'web')) {
            abort(403, 'Cannot impersonate another admin account.');
        }

        AdminActivityLog::record('user.impersonated', $user, "Started impersonating {$user->email}");

        Auth::guard('web')->login($user);
        $request->session()->migrate();

        return redirect()->to($user->postLoginRoute())->with('success', "You're now viewing as {$user->name}.");
    }

    /**
     * Ends impersonation by logging the 'web' guard out — the 'admin' guard session (the admin's
     * own login) was never touched, so this alone is enough to hand control back.
     */
    public function stop(Request $request): RedirectResponse
    {
        if (! Auth::guard('admin')->check()) {
            return redirect()->route('home');
        }

        Auth::guard('web')->logout();
        $request->session()->migrate();

        return redirect('/admin')->with('success', 'Back to your admin account.');
    }
}
