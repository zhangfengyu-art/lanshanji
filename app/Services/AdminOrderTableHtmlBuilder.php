<?php

namespace App\Services;

/**
 * 构建订单/备货表导出的 HTML 表格（ZIP 与 PDF 共用）。
 */
class AdminOrderTableHtmlBuilder
{
    /**
     * @return array{html:string,temp_dir:?string,zip_files:array<string,string>}
     */
    public static function build(array $headers, callable $rowProducer, array $options = [])
    {
        $textColumns = (array) ($options['text_columns'] ?? []);
        $imageColumns = (array) ($options['image_columns'] ?? []);
        $checkboxColumns = (array) ($options['checkbox_columns'] ?? []);
        $footerNote = (string) ($options['footer_note'] ?? '');
        $imageMaxSize = max(32, (int) ($options['image_max_size'] ?? 96));
        $imageDisplaySize = max(32, (int) ($options['image_display_size'] ?? 72));
        $imageJpegQuality = min(100, max(50, (int) ($options['image_jpeg_quality'] ?? 82)));
        $tableFontSize = max(10, (int) ($options['table_font_size'] ?? 13));
        $checkboxCellSize = max(24, (int) ($options['checkbox_cell_size'] ?? 40));
        $enablePrintCss = (bool) ($options['enable_print_css'] ?? false);
        $titleNote = trim((string) ($options['title_note'] ?? ''));
        $embedImages = (($options['image_embed_mode'] ?? 'file') === 'base64');

        $tempDir = null;
        $imageDir = null;
        $zipFiles = [];
        $imageCache = [];

        if (!$embedImages) {
            $tempDir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'ord_export_'.uniqid('', true);
            $imageDir = $tempDir.DIRECTORY_SEPARATOR.'images';
            if (!@mkdir($imageDir, 0777, true) && !is_dir($imageDir)) {
                throw new \RuntimeException('无法创建临时导出目录');
            }
        }

        $html = '<html><head><meta charset="UTF-8">';
        if ($enablePrintCss) {
            $html .= '<style>'
                .'@media print{body{margin:12px;} table{font-size:'.($tableFontSize - 1).'px;} '
                .'img{max-width:'.($imageDisplaySize + 20).'px;max-height:'.($imageDisplaySize + 20).'px;} '
                .'th,td{padding:8px!important;}}'
                .'</style>';
        }
        if ($embedImages) {
            $html .= '<style>'
                .'body{font-family:sans-serif;} '
                .'table{border-collapse:collapse;width:100%;} '
                .'th,td{border:1px solid #d0d0d0;padding:6px 8px;vertical-align:middle;} '
                .'th{background:#4472C4;color:#fff;}'
                .'</style>';
        }
        $html .= '</head><body>';
        if ($titleNote !== '') {
            $html .= '<p style="font-size:'.($tableFontSize + 1).'px;font-weight:bold;margin:0 0 10px;">'
                .htmlspecialchars($titleNote, ENT_QUOTES, 'UTF-8')
                .'</p>';
        }
        $html .= '<table border="1" cellspacing="0" cellpadding="0" style="border-collapse:collapse;font-size:'.$tableFontSize.'px;">';
        $html .= '<tr>';
        foreach ($headers as $header) {
            $html .= '<th style="background:#4472C4;color:#fff;padding:8px 10px;">'
                .htmlspecialchars((string) $header, ENT_QUOTES, 'UTF-8')
                .'</th>';
        }
        $html .= '</tr>';

        $emitRow = function (array $row) use (
            &$html,
            $textColumns,
            $imageColumns,
            $checkboxColumns,
            $imageDir,
            $imageMaxSize,
            $imageDisplaySize,
            $imageJpegQuality,
            $checkboxCellSize,
            $embedImages,
            &$imageCache,
            &$zipFiles
        ) {
            $html .= '<tr>';
            foreach ($row as $index => $cell) {
                $style = 'padding:6px 8px;vertical-align:middle;border:1px solid #d0d0d0;';
                if (in_array($index, $textColumns, true)) {
                    $style .= 'mso-number-format:\@;';
                }

                if (in_array($index, $checkboxColumns, true)) {
                    $boxSize = max(16, $checkboxCellSize - 10);
                    $html .= '<td style="'.$style.'text-align:center;width:'.$checkboxCellSize.'px;min-width:'.$checkboxCellSize.'px;">';
                    $html .= '<div style="width:'.$boxSize.'px;height:'.$boxSize.'px;border:2px solid #333;margin:0 auto;"></div>';
                    $html .= '</td>';

                    continue;
                }

                if (in_array($index, $imageColumns, true)) {
                    $localPath = trim((string) $cell);
                    $cellWidth = $imageDisplaySize + 16;
                    $html .= '<td style="'.$style.'text-align:center;width:'.$cellWidth.'px;min-width:'.$cellWidth.'px;">';
                    if ($localPath !== '' && is_file($localPath)) {
                        $src = static::imageSrcForExport(
                            $localPath,
                            $imageDir,
                            $imageCache,
                            $zipFiles,
                            $imageMaxSize,
                            $imageJpegQuality,
                            $embedImages
                        );
                        if ($src !== '') {
                            $html .= '<img src="'.htmlspecialchars($src, ENT_QUOTES, 'UTF-8')
                                .'" width="'.$imageDisplaySize.'" height="'.$imageDisplaySize.'" style="object-fit:contain;" alt=""/>';
                        } else {
                            $html .= '—';
                        }
                    } else {
                        $html .= '—';
                    }
                    $html .= '</td>';
                } else {
                    $text = (string) $cell;
                    if (strpos($text, "\n") !== false) {
                        $style .= 'white-space:pre-wrap;';
                    }
                    $html .= '<td style="'.$style.'">'.htmlspecialchars($text, ENT_QUOTES, 'UTF-8').'</td>';
                }
            }
            $html .= '</tr>';
        };

        $rowProducer($emitRow);

        $html .= '</table>';
        if ($footerNote !== '') {
            $html .= '<p style="font-size:12px;color:#666;margin-top:12px;">'
                .htmlspecialchars($footerNote, ENT_QUOTES, 'UTF-8')
                .'</p>';
        }
        $html .= '</body></html>';

        return [
            'html' => $html,
            'temp_dir' => $tempDir,
            'zip_files' => $zipFiles,
        ];
    }

    protected static function imageSrcForExport(
        $localPath,
        $imageDir,
        array &$cache,
        array &$zipFiles,
        $maxSize,
        $jpegQuality,
        $embedImages
    ) {
        $key = md5($localPath.'|'.$maxSize.'|'.$jpegQuality.'|'.($embedImages ? 'b64' : 'file'));
        if (isset($cache[$key])) {
            return $cache[$key];
        }

        if ($embedImages) {
            $thumbPath = OrderAdminExportService::writeThumbnailFile(
                $localPath,
                sys_get_temp_dir(),
                $key,
                $maxSize,
                $jpegQuality
            );
            if ($thumbPath === '' || !is_file($thumbPath)) {
                return '';
            }

            $raw = @file_get_contents($thumbPath);
            @unlink($thumbPath);
            if ($raw === false || $raw === '') {
                return '';
            }

            $src = 'data:image/jpeg;base64,'.base64_encode($raw);
            $cache[$key] = $src;

            return $src;
        }

        $thumbPath = OrderAdminExportService::writeThumbnailFile($localPath, $imageDir, $key, $maxSize, $jpegQuality);
        if ($thumbPath === '') {
            return '';
        }

        $rel = 'images/'.basename($thumbPath);
        $cache[$key] = $rel;
        $zipFiles[$rel] = $thumbPath;

        return $rel;
    }
}
