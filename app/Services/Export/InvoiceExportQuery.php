<?php

namespace App\Services\Export;

use App\Models\Invoice;
use App\Models\Vendor;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;

class InvoiceExportQuery
{
    public static function build(?Vendor $vendor, Request $request): Collection
    {
        $query = Invoice::query()
            ->with(['vendor', 'items'])
            ->when($vendor, fn ($q) => $q->where('vendor_id', $vendor->id))
            ->when($request->string('q')->toString(), function ($q, $term) {
                $q->where('invoice_number', 'like', "%{$term}%")
                    ->orWhere('customer_name', 'like', "%{$term}%")
                    ->orWhere('customer_email', 'like', "%{$term}%");
            })
            ->when($request->string('status')->toString(), fn ($q, $status) => $q->where('status', $status))
            ->latest();

        return $query->get();
    }
}
