<?php

namespace App\Http\Controllers\Admin;

use App\Exports\GoogleMerchantExport;
use App\Exports\InvoicesExport;
use App\Exports\OrdersExport;
use App\Exports\ProductsExport;
use App\Exports\ReportExport;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\HandlesExports;
use App\Services\Export\InvoiceExportQuery;
use App\Services\Export\OrderExportQuery;
use App\Services\Export\ProductExportQuery;
use App\Services\Export\ReportExportData;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Platform-wide counterpart to Vendor\ExportController — same underlying query/export classes,
 * called with $vendor = null so every vendor's rows are included and the Vendor column is shown.
 * Routed under the 'auth:admin' guard (see routes/web.php) and linked from Filament resource
 * header actions rather than a bespoke admin UI.
 */
class ExportController extends Controller
{
    use HandlesExports;

    public function orders(Request $request): BinaryFileResponse|Response
    {
        $orders = OrderExportQuery::build(null, $request);
        $format = $request->string('format', 'pdf')->toString();

        return $this->respondWithExport(
            $format,
            'exports.pdf.orders',
            [
                'orders' => $orders,
                'vendor' => null,
                'title' => 'Orders Report',
                'subtitle' => 'All Vendors',
                'preparedFor' => 'Platform Administration',
            ],
            new OrdersExport($orders, includeVendorColumn: true),
            'orders-'.now()->format('Y-m-d'),
        );
    }

    public function products(Request $request): BinaryFileResponse|Response
    {
        $products = ProductExportQuery::build(null, $request);
        $format = $request->string('format', 'pdf')->toString();

        return $this->respondWithExport(
            $format,
            'exports.pdf.products',
            [
                'products' => $products,
                'vendor' => null,
                'title' => 'Product Catalog Report',
                'subtitle' => 'All Vendors',
                'preparedFor' => 'Platform Administration',
            ],
            new ProductsExport($products, includeVendorColumn: true),
            'products-'.now()->format('Y-m-d'),
        );
    }

    public function invoices(Request $request): BinaryFileResponse|Response
    {
        $invoices = InvoiceExportQuery::build(null, $request);
        $format = $request->string('format', 'pdf')->toString();

        return $this->respondWithExport(
            $format,
            'exports.pdf.invoices',
            [
                'invoices' => $invoices,
                'vendor' => null,
                'title' => 'Invoices Report',
                'subtitle' => 'All Vendors',
                'preparedFor' => 'Platform Administration',
            ],
            new InvoicesExport($invoices, includeVendorColumn: true),
            'invoices-'.now()->format('Y-m-d'),
        );
    }

    /**
     * A Google Merchant Center-ready spreadsheet (see App\Exports\GoogleMerchantExport's class doc
     * comment) — upload this to a Google Sheet and connect it as a scheduled-fetch feed in
     * Merchant Center. Excel-only; there's no PDF equivalent of a product feed.
     */
    public function googleMerchant(): BinaryFileResponse
    {
        return Excel::download(new GoogleMerchantExport, 'google-merchant-feed-'.now()->format('Y-m-d').'.xlsx');
    }

    public function report(Request $request): BinaryFileResponse|Response
    {
        $data = ReportExportData::build(null);
        $format = $request->string('format', 'pdf')->toString();

        return $this->respondWithExport(
            $format,
            'exports.pdf.report',
            [
                'data' => $data,
                'title' => 'Marketplace Report',
                'subtitle' => 'All Vendors',
                'preparedFor' => 'Platform Administration',
            ],
            new ReportExport($data),
            'marketplace-report-'.now()->format('Y-m-d'),
        );
    }
}
