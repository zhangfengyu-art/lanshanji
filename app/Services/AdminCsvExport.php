<?php

namespace App\Services;

use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminCsvExport
{
    /**
     * @param string   $filename 下载文件名（建议 .csv，可含中文）
     * @param string[] $headers  中文表头
     * @param iterable $rows     每行与表头同序的单元格数组
     *
     * @return StreamedResponse
     */
    public static function download($filename, array $headers, $rows)
    {
        $asciiFallback = preg_replace('/[^a-zA-Z0-9._-]+/', '_', pathinfo($filename, PATHINFO_FILENAME));
        if ($asciiFallback === '' || $asciiFallback === '_') {
            $asciiFallback = 'export';
        }
        $asciiFallback .= '.'.(pathinfo($filename, PATHINFO_EXTENSION) ?: 'csv');

        $disposition = 'attachment; filename="'.$asciiFallback.'"; filename*=UTF-8\'\''.rawurlencode($filename);

        $callback = function () use ($headers, $rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, $headers);
            foreach ($rows as $row) {
                fputcsv($out, $row);
            }
            fclose($out);
        };

        return response()->stream($callback, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => $disposition,
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
        ]);
    }
}
