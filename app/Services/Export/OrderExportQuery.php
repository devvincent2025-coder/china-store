<?php

namespace App\Services\Export;

use App\Models\Vendor;
use App\Models\VendorOrder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;

/**
 * One query, reused by both the Excel and PDF renderers (App\Exports\OrdersExport and the
 * exports.pdf.orders Blade view) and by both audiences — pass a Vendor to scope to just their own
 * orders (Vendor\ExportController), or null for the platform-wide admin export
 * (Http\Controllers\Admin\ExportController). Filters mirror Vendor\OrderController::index()'s own
 * so "export what I'm currently looking at" and "export everything" both work from the same params.
 */
class OrderExportQuery
{
    public static function build(?Vendor $vendor, Request $request): Collection
    {
        $query = VendorOrder::query()
            ->with(['order.user', 'vendor', 'coupon', 'items'])
            ->when($vendor, fn ($q) => $q->where('vendor_id', $vendor->id))
            ->when($request->string('q')->toString(), function ($q, $term) {
                $q->whereHas('order', function ($q) use ($term) {
                    $q->where('order_number', 'like', "%{$term}%")
                        ->orWhereHas('user', function ($q) use ($term) {
                            $q->where('name', 'like', "%{$term}%")
                                ->orWhere('email', 'like', "%{$term}%");
                        });
                });
            })
            ->when($request->string('fulfillment_status')->toString(), fn ($q, $status) => $q->where('fulfillment_status', $status))
            ->when($request->date('date_from'), fn ($q, $date) => $q->whereDate('created_at', '>=', $date))
            ->when($request->date('date_to'), fn ($q, $date) => $q->whereDate('created_at', '<=', $date))
            ->when($request->boolean('has_coupon'), fn ($q) => $q->whereNotNull('coupon_id'))
            ->latest();

        return $query->get();
    }
}
