<?php

namespace App\Http\Controllers\Concerns;

use Barryvdh\DomPDF\Facade\Pdf;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Shared by Vendor\ExportController (one vendor's own data) and Admin\ExportController
 * (platform-wide) — both build the same underlying data via App\Services\Export\*Query /
 * ReportExportData, then just need "give it to me as a PDF or an Excel file", which is what this
 * trait actually does.
 */
trait HandlesExports
{
    protected function respondWithExport(
        string $format,
        string $pdfView,
        array $pdfData,
        object $excelExport,
        string $filenameBase,
    ): BinaryFileResponse|Response {
        if ($format === 'xlsx') {
            return Excel::download($excelExport, "{$filenameBase}.xlsx");
        }

        return Pdf::loadView($pdfView, $pdfData)
            ->setPaper('a4')
            ->download("{$filenameBase}.pdf");
    }
}
