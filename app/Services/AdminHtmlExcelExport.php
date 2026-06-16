<?php

namespace App\Services;

use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * 生成 Excel 可打开的 HTML 表格（.xls），支持文本列防科学计数法、嵌入商品图。
 */
class AdminHtmlExcelExport
{
    /**
     * @param string   $filename
     * @param string[] $headers
     * @param iterable $rows
     * @param array    $options text_columns / image_columns：0 起始列索引
     *
     * @return StreamedResponse
     */
    public static function download($filename, array $headers, $rows, array $options = [])
    {
        $textColumns = (array) ($options['text_columns'] ?? []);
        $imageColumns = (array) ($options['image_columns'] ?? []);

        $basename = pathinfo($filename, PATHINFO_FILENAME);
        $asciiFallback = preg_replace('/[^a-zA-Z0-9._-]+/', '_', $basename);
        if ($asciiFallback === '' || $asciiFallback === '_') {
            $asciiFallback = 'export';
        }
        $asciiFallback .= '.xls';

        if (!Str::endsWith(strtolower($filename), '.xls')) {
            $filename = $basename.'.xls';
        }

        $disposition = 'attachment; filename="'.$asciiFallback.'"; filename*=UTF-8\'\''.rawurlencode($filename);

        $callback = function () use ($headers, $rows, $textColumns, $imageColumns) {
            echo '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns:v="urn:schemas-microsoft-com:vml">';
            echo '<head><meta charset="UTF-8">';
            echo '<!--[if gte mso 9]><xml><x:ExcelWorkbook><x:ExcelWorksheets><x:ExcelWorksheet><x:Name>订单</x:Name></x:ExcelWorksheet></x:ExcelWorksheets></x:ExcelWorkbook></xml><![endif]-->';
            echo '</head><body>';
            echo '<table border="1" cellspacing="0" cellpadding="0" style="border-collapse:collapse;font-size:13px;">';
            echo '<tr>';
            foreach ($headers as $header) {
                echo '<th style="background:#4472C4;color:#fff;font-weight:bold;padding:8px 10px;white-space:nowrap;">'
                    .htmlspecialchars((string) $header, ENT_QUOTES, 'UTF-8')
                    .'</th>';
            }
            echo '</tr>';

            foreach ($rows as $row) {
                echo '<tr>';
                foreach ($row as $index => $cell) {
                    $style = 'padding:6px 8px;vertical-align:middle;border:1px solid #d0d0d0;';
                    if (in_array($index, $textColumns, true)) {
                        $style .= 'mso-number-format:\@;white-space:pre-wrap;';
                    }

                    if (in_array($index, $imageColumns, true)) {
                        $src = trim((string) $cell);
                        echo '<td style="'.$style.'text-align:center;width:100px;height:90px;">';
                        if ($src !== '' && Str::startsWith($src, 'data:')) {
                            $safeSrc = str_replace('"', '&quot;', $src);
                            echo '<!--[if gte vml 1]>';
                            echo '<v:shape xmlns:v="urn:schemas-microsoft-com:vml" style="width:60pt;height:60pt;" stroked="f">';
                            echo '<v:imagedata src="'.$safeSrc.'" o:title="product"/>';
                            echo '</v:shape>';
                            echo '<![endif]-->';
                            echo '<!--[if !vml]>';
                            echo '<img src="'.$safeSrc.'" width="80" height="80" style="object-fit:contain;display:block;margin:auto;" alt=""/>';
                            echo '<![endif]-->';
                        } elseif ($src !== '') {
                            echo '<span style="color:#999;font-size:11px;">图片未找到</span>';
                        } else {
                            echo '—';
                        }
                        echo '</td>';
                    } else {
                        $text = (string) $cell;
                        if (strpos($text, "\n") !== false) {
                            $style .= 'white-space:pre-wrap;';
                        }
                        echo '<td style="'.$style.'">'.htmlspecialchars($text, ENT_QUOTES, 'UTF-8').'</td>';
                    }
                }
                echo '</tr>';
            }

            echo '</table></body></html>';
        };

        return response()->stream($callback, 200, [
            'Content-Type' => 'application/vnd.ms-excel; charset=UTF-8',
            'Content-Disposition' => $disposition,
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
        ]);
    }
}
