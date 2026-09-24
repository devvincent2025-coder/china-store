<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Models\LedgerEntry;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Read-only view onto this vendor's own slice of the platform-wide reconciliation ledger — see
 * App\Services\Ledger\LedgerService for how rows are written, and
 * App\Filament\Resources\LedgerEntryResource for the admin-wide equivalent.
 */
class PayoutController extends Controller
{
    public function index(Request $request): Response
    {
        $vendor = $request->user()->vendor;

        $query = LedgerEntry::query()->where('vendor_id', $vendor->id)->with('vendorOrder.order')->latest();

        if ($type = $request->string('type')->value()) {
            $query->where('type', $type);
        }

        if ($status = $request->string('status')->value()) {
            $query->where('status', $status);
        }

        $entries = $query->paginate(20)->withQueryString();

        $totals = LedgerEntry::query()
            ->where('vendor_id', $vendor->id)
            ->where('type', 'payout')
            ->selectRaw("status, count(*) as count, sum(abs(amount)) as total")
            ->groupBy('status')
            ->get()
            ->keyBy('status');

        $balance = LedgerEntry::query()->where('vendor_id', $vendor->id)->where('status', 'success')->sum('amount');

        return Inertia::render('Vendor/Payouts/Index', [
            'vendor' => $vendor,
            'entries' => $entries,
            'filters' => $request->only(['type', 'status']),
            'payoutTotals' => [
                'pending' => (int) ($totals['pending']->total ?? 0),
                'pendingCount' => (int) ($totals['pending']->count ?? 0),
                'success' => (int) ($totals['success']->total ?? 0),
                'successCount' => (int) ($totals['success']->count ?? 0),
                'failed' => (int) ($totals['failed']->total ?? 0),
                'failedCount' => (int) ($totals['failed']->count ?? 0),
            ],
            'balance' => (int) $balance,
        ]);
    }
}
