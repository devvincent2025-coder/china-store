<?php

namespace App\Http\Controllers\Vendor;

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
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * A vendor's own PDF/Excel exports — always scoped to their own data only (never platform-wide;
 * see Admin\ExportController for the equivalent that omits the vendor scope entirely). Every
 * export is fully custom: branded PDF layout (resources/views/exports/pdf/*) with every field
 * relevant to that vendor's own decision-making, or a complete-detail spreadsheet.
 */
class ExportController extends Controller
{
    use HandlesExports;

    public function orders(Request $request): BinaryFileResponse|Response
    {
        $vendor = $request->user()->vendor;
        $orders = OrderExportQuery::build($vendor, $request);
        $format = $request->string('format', 'pdf')->toString();

        return $this->respondWithExport(
            $format,
            'exports.pdf.orders',
            [
                'orders' => $orders,
                'vendor' => $vendor,
                'title' => 'Orders Report',
                'subtitle' => $vendor->business_name,
                'preparedFor' => $vendor->business_name,
            ],
            new OrdersExport($orders, includeVendorColumn: false),
            'orders-'.now()->format('Y-m-d'),
        );
    }

    public function products(Request $request): BinaryFileResponse|Response
    {
        $vendor = $request->user()->vendor;
        $products = ProductExportQuery::build($vendor, $request);
        $format = $request->string('format', 'pdf')->toString();

        return $this->respondWithExport(
            $format,
            'exports.pdf.products',
            [
                'products' => $products,
                'vendor' => $vendor,
                'title' => 'Product Catalog Report',
                'subtitle' => $vendor->business_name,
                'preparedFor' => $vendor->business_name,
            ],
            new ProductsExport($products, includeVendorColumn: false),
            'products-'.now()->format('Y-m-d'),
        );
    }

    public function invoices(Request $request): BinaryFileResponse|Response
    {
        $vendor = $request->user()->vendor;
        $invoices = InvoiceExportQuery::build($vendor, $request);
        $format = $request->string('format', 'pdf')->toString();

        return $this->respondWithExport(
            $format,
            'exports.pdf.invoices',
            [
                'invoices' => $invoices,
                'vendor' => $vendor,
                'title' => 'Invoices Report',
                'subtitle' => $vendor->business_name,
                'preparedFor' => $vendor->business_name,
            ],
            new InvoicesExport($invoices, includeVendorColumn: false),
            'invoices-'.now()->format('Y-m-d'),
        );
    }

    public function report(Request $request): BinaryFileResponse|Response
    {
        $vendor = $request->user()->vendor;
        $data = ReportExportData::build($vendor);
        $format = $request->string('format', 'pdf')->toString();

        return $this->respondWithExport(
            $format,
            'exports.pdf.report',
            [
                'data' => $data,
                'title' => 'Business Report',
                'subtitle' => $vendor->business_name,
                'preparedFor' => $vendor->business_name,
            ],
            new ReportExport($data),
            'business-report-'.now()->format('Y-m-d'),
        );
    }
}
