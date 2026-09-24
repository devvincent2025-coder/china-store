<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use App\Notifications\NewProductReport;
use App\Services\Product\ProductTakedownService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;

class ProductReportController extends Controller
{
    public function store(Request $request, Product $product, ProductTakedownService $takedown): RedirectResponse
    {
        $validated = $request->validate([
            'reason' => ['required', 'in:counterfeit,prohibited,misleading,offensive,other'],
            'description' => ['required', 'string', 'max:2000'],
        ]);

        $report = $product->reports()->create([
            'user_id' => $request->user()?->id,
            'reason' => $validated['reason'],
            'description' => $validated['description'],
        ]);

        $admins = User::role(['admin', 'super_admin'])->get();
        Notification::send($admins, new NewProductReport($report));

        // Configurable via Filament\Pages\Settings ('product_report_flag_threshold', default 5) —
        // once an active product crosses this many OPEN reports, pull it down immediately for
        // buyer safety and alert admins to investigate. Never touches the vendor's account itself
        // — see ProductTakedownService::autoFlagForReports()'s doc comment for why that stays a
        // manual admin decision.
        $threshold = (int) Setting::get('product_report_flag_threshold', 5);
        $openReportsCount = $product->reports()->where('status', 'open')->count();

        if ($openReportsCount >= $threshold && $product->taken_down_at === null) {
            $takedown->autoFlagForReports($product, $openReportsCount);
        }

        return back()->with('success', 'Thanks for reporting this — our team will review it.');
    }
}
