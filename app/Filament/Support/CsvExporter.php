<?php

namespace App\Filament\Support;

use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Minimal CSV export used by the "Export CSV" header action on several admin list pages — avoids
 * pulling in a whole export package for what's just a header row plus rows of scalar values.
 */
class CsvExporter
{
    /**
     * @param  array<int, string>  $headers
     * @param  Collection<int, array<int, mixed>>  $rows
     */
    public static function stream(string $filename, array $headers, Collection $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($headers, $rows) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, $headers);

            foreach ($rows as $row) {
                fputcsv($handle, $row);
            }

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }
}
