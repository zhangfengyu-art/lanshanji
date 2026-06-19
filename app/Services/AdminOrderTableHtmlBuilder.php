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
        $numericColumns = (array) ($options['numeric_columns'] ?? []);
        $centerColumns = (array) ($options['center_columns'] ?? []);
        $qtyColumns = (array) ($options['qty_columns'] ?? []);
        $badgeColumns = (array) ($options['badge_columns'] ?? [2]);
        $wrapColumns = (array) ($options['wrap_columns'] ?? []);
        $columnWidths = (array) ($options['column_widths'] ?? []);
        $columnWidthsMm = (array) ($options['column_widths_mm'] ?? []);
        $footerNote = (string) ($options['footer_note'] ?? '');
        $imageMaxSize = max(32, (int) ($options['image_max_size'] ?? 96));
        $imageDisplayWidth = max(32, (int) ($options['image_display_width'] ?? ($options['image_display_size'] ?? 72)));
        $imageDisplayHeight = max(32, (int) ($options['image_display_height'] ?? $imageDisplayWidth));
        $imageJpegQuality = min(100, max(50, (int) ($options['image_jpeg_quality'] ?? 82)));
        $tableFontSize = max(9, (int) ($options['table_font_size'] ?? 13));
        $checkboxCellSize = max(24, (int) ($options['checkbox_cell_size'] ?? 40));
        $enablePrintCss = (bool) ($options['enable_print_css'] ?? false);
        $styleMode = (string) ($options['style_mode'] ?? 'default');
        $titleNote = trim((string) ($options['title_note'] ?? ''));
        $embedImages = (($options['image_embed_mode'] ?? 'file') === 'base64');
        $useAbsoluteImagePaths = (bool) ($options['image_src_absolute'] ?? false);
        $returnChunks = (bool) ($options['return_chunks'] ?? false);
        $isPdfStyle = $styleMode === 'pdf';

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
        $html .= static::buildHeadStyles($isPdfStyle, $enablePrintCss, $tableFontSize, $imageDisplayWidth, $imageDisplayHeight, $columnWidthsMm, $wrapColumns);
        $html .= '</head><body class="'.($isPdfStyle ? 'export-pdf' : 'export-default').'">';

        if ($titleNote !== '') {
            $html .= static::buildTitleBlock($titleNote, $tableFontSize, $isPdfStyle);
        }

        $html .= '<table class="export-table" border="1" cellspacing="0" cellpadding="0" style="border-collapse:collapse;font-size:'.$tableFontSize.'px;width:100%;table-layout:fixed;">';
        $widthsForColgroup = $columnWidthsMm !== [] ? $columnWidthsMm : $columnWidths;
        if ($widthsForColgroup !== []) {
            $html .= '<colgroup>';
            foreach ($widthsForColgroup as $width) {
                $html .= '<col width="'.htmlspecialchars((string) $width, ENT_QUOTES, 'UTF-8').'"/>';
            }
            $html .= '</colgroup>';
        }
        $html .= '<thead><tr>';
        foreach ($headers as $index => $header) {
            $widthStyle = '';
            if (isset($columnWidthsMm[$index])) {
                $w = $columnWidthsMm[$index];
                $widthStyle = ' style="width:'.$w.';max-width:'.$w.';"';
            }
            $html .= '<th'.$widthStyle.'>'.htmlspecialchars((string) $header, ENT_QUOTES, 'UTF-8').'</th>';
        }
        $html .= '</tr></thead><tbody>';

        $rowIndex = 0;
        $rowChunks = [];
        $emitRow = function (array $row) use (
            &$html,
            &$rowChunks,
            &$rowIndex,
            $textColumns,
            $imageColumns,
            $checkboxColumns,
            $numericColumns,
            $centerColumns,
            $qtyColumns,
            $badgeColumns,
            $wrapColumns,
            $columnWidthsMm,
            $imageDir,
            $imageMaxSize,
            $imageDisplayWidth,
            $imageDisplayHeight,
            $imageJpegQuality,
            $checkboxCellSize,
            $embedImages,
            $useAbsoluteImagePaths,
            $returnChunks,
            $isPdfStyle,
            &$imageCache,
            &$zipFiles
        ) {
            $isTotalRow = isset($row[0]) && (string) $row[0] === '合计';
            $rowClass = $isTotalRow ? 'row-total' : ($rowIndex % 2 === 1 ? 'row-alt' : 'row-even');
            $rowHtml = '<tr class="'.$rowClass.'">';
            $rowIndex++;

            foreach ($row as $index => $cell) {
                $classes = [];
                if (in_array($index, $numericColumns, true)) {
                    $classes[] = 'cell-num';
                }
                if (in_array($index, $centerColumns, true)) {
                    $classes[] = 'cell-center';
                }
                if (in_array($index, $textColumns, true)) {
                    $classes[] = 'cell-text';
                }
                if (in_array($index, $imageColumns, true)) {
                    $classes[] = 'cell-image';
                }
                if (in_array($index, $checkboxColumns, true)) {
                    $classes[] = 'cell-check';
                }
                if (in_array($index, $qtyColumns, true)) {
                    $classes[] = 'cell-qty';
                }
                if (in_array($index, $wrapColumns, true)) {
                    $classes[] = 'cell-wrap';
                }

                $classAttr = $classes !== [] ? ' class="'.implode(' ', $classes).'"' : '';
                $style = in_array($index, $textColumns, true) ? ' mso-number-format:\@;' : '';
                if (isset($columnWidthsMm[$index])) {
                    $w = $columnWidthsMm[$index];
                    $style .= ' width:'.$w.';max-width:'.$w.';';
                }

                if (in_array($index, $checkboxColumns, true)) {
                    $boxSize = max(14, $checkboxCellSize - 8);
                    $rowHtml .= '<td'.$classAttr.' style="text-align:center;'.$style.'">';
                    $rowHtml .= '<div class="check-box" style="width:'.$boxSize.'px;height:'.$boxSize.'px;"></div>';
                    $rowHtml .= '</td>';

                    continue;
                }

                if (in_array($index, $imageColumns, true)) {
                    $localPath = trim((string) $cell);
                    $rowHtml .= '<td'.$classAttr.' style="text-align:center;'.$style.'">';
                    if ($localPath !== '' && is_file($localPath)) {
                        $src = static::imageSrcForExport(
                            $localPath,
                            $imageDir,
                            $imageCache,
                            $zipFiles,
                            $imageMaxSize,
                            $imageJpegQuality,
                            $embedImages,
                            $useAbsoluteImagePaths
                        );
                        if ($src !== '') {
                            $rowHtml .= '<div class="img-frame">';
                            $rowHtml .= '<img src="'.htmlspecialchars($src, ENT_QUOTES, 'UTF-8')
                                .'" class="product-img" width="'.$imageDisplayWidth.'"'
                                .' style="max-width:'.$imageDisplayWidth.'px;max-height:'.$imageDisplayHeight.'px;width:auto;height:auto;display:block;margin:0 auto;" alt=""/>';
                            $rowHtml .= '</div>';
                        } else {
                            $rowHtml .= '—';
                        }
                    } else {
                        $rowHtml .= $isTotalRow ? '' : '—';
                    }
                    $rowHtml .= '</td>';

                    continue;
                }

                $text = (string) $cell;
                if ($isPdfStyle && in_array($index, $badgeColumns, true) && !$isTotalRow) {
                    $text = static::formatTypeBadge($text);
                    $rowHtml .= '<td'.$classAttr.' style="'.$style.'">'.$text.'</td>';
                } else {
                    if (strpos($text, "\n") !== false) {
                        $style .= ' white-space:pre-wrap;';
                    }
                    $rowHtml .= '<td'.$classAttr.' style="'.$style.'">'.htmlspecialchars($text, ENT_QUOTES, 'UTF-8').'</td>';
                }
            }
            $rowHtml .= '</tr>';
            if ($returnChunks) {
                $rowChunks[] = $rowHtml;
            } else {
                $html .= $rowHtml;
            }
        };

        $rowProducer($emitRow);

        $footerHtml = '</tbody></table>';
        if ($footerNote !== '') {
            $footerHtml .= '<p class="export-footer">'.htmlspecialchars($footerNote, ENT_QUOTES, 'UTF-8').'</p>';
        }
        $footerHtml .= '</body></html>';

        if ($returnChunks) {
            $result = [
                'html' => $html.$footerHtml,
                'html_chunks' => array_merge([$html], $rowChunks, [$footerHtml]),
                'temp_dir' => $tempDir,
                'zip_files' => $zipFiles,
            ];

            return $result;
        }

        $html .= $footerHtml;

        return [
            'html' => $html,
            'temp_dir' => $tempDir,
            'zip_files' => $zipFiles,
        ];
    }

    protected static function buildTitleBlock($titleNote, $tableFontSize, $isPdfStyle)
    {
        if (!$isPdfStyle) {
            return '<p style="font-size:'.($tableFontSize + 1).'px;font-weight:bold;margin:0 0 10px;">'
                .htmlspecialchars($titleNote, ENT_QUOTES, 'UTF-8')
                .'</p>';
        }

        $parts = explode(' · ', $titleNote, 3);
        $mainTitle = $parts[0] ?? $titleNote;
        $meta = isset($parts[1]) ? implode(' · ', array_slice($parts, 1)) : '';

        $html = '<div class="export-header">';
        $html .= '<div class="export-header-title">'.htmlspecialchars($mainTitle, ENT_QUOTES, 'UTF-8').'</div>';
        if ($meta !== '') {
            $html .= '<div class="export-header-meta">'.htmlspecialchars($meta, ENT_QUOTES, 'UTF-8').'</div>';
        }
        $html .= '</div>';

        return $html;
    }

    protected static function formatTypeBadge($text)
    {
        $text = trim($text);
        if ($text === '香烟') {
            return '<span class="type-badge type-cigarette">香烟</span>';
        }
        if ($text === '加热烟') {
            return '<span class="type-badge type-heated">加热烟</span>';
        }
        if ($text === '手卷烟丝' || $text === '烟丝') {
            return '<span class="type-badge type-rolling">手卷烟丝</span>';
        }

        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }

    protected static function buildHeadStyles($isPdfStyle, $enablePrintCss, $tableFontSize, $imageDisplayWidth, $imageDisplayHeight, array $columnWidthsMm = [], array $wrapColumns = [])
    {
        $css = '<style>';
        if ($isPdfStyle) {
            $css .= 'body.export-pdf{margin:0;padding:0;font-family:"sans-serif";color:#1a1a1a;font-size:'.$tableFontSize.'px;}'
                .'.export-header{background:#2f5597;color:#fff;padding:10px 12px;margin:0 0 10px;}'
                .'.export-header-title{font-size:'.($tableFontSize + 5).'px;font-weight:bold;line-height:1.3;}'
                .'.export-header-meta{font-size:'.($tableFontSize - 1).'px;margin-top:4px;opacity:0.9;}'
                .'table.export-table{border-collapse:collapse;width:100%;table-layout:fixed;}'
                .'table.export-table th{background:#2f5597;color:#fff;padding:8px 6px;font-weight:bold;text-align:center;border:1px solid #244a82;font-size:'.($tableFontSize - 1).'px;line-height:1.3;}'
                .'table.export-table td{border:1px solid #c5ced8;padding:8px 6px;vertical-align:middle;line-height:1.4;word-wrap:break-word;font-size:'.$tableFontSize.'px;}'
                .'td.cell-wrap{word-break:break-all;overflow-wrap:anywhere;white-space:normal;padding:6px 4px;}'
                .'tr.row-even td{background:#ffffff;}'
                .'tr.row-alt td{background:#f7f9fc;}'
                .'tr.row-total td{background:#e8eef7;font-weight:bold;border-top:2px solid #2f5597;font-size:'.($tableFontSize + 1).'px;}'
                .'td.cell-num{text-align:center;font-variant-numeric:tabular-nums;}'
                .'td.cell-qty{text-align:center;font-size:'.($tableFontSize + 4).'px;font-weight:bold;color:#1f3f72;}'
                .'td.cell-center{text-align:center;}'
                .'td.cell-text{text-align:left;font-weight:500;}'
                .'td.cell-image{padding:6px 4px;text-align:center;}'
                .'td.cell-check{padding:6px 2px;text-align:center;width:14mm;max-width:14mm;}'
                .'.img-frame{display:inline-block;background:#f3f6fa;border:1px solid #d5dde8;border-radius:3px;padding:3px;line-height:0;}'
                .'.check-box{border:2px solid #222;margin:0 auto;background:#fff;}'
                .'.product-img{object-fit:contain;display:block;}'
                .'.type-badge{display:inline-block;padding:2px 8px;border-radius:10px;font-size:'.($tableFontSize - 1).'px;font-weight:bold;line-height:1.35;white-space:nowrap;}'
                .'.type-cigarette{background:#fff4e5;color:#9a5b00;border:1px solid #f0c987;}'
                .'.type-heated{background:#e8f7f3;color:#0d6b52;border:1px solid #9fdccc;}'
                .'.type-rolling{background:#f3ecff;color:#5b3d91;border:1px solid #d4bdf5;}'
                .'.export-footer{font-size:'.($tableFontSize - 1).'px;color:#666;margin-top:8px;line-height:1.4;}';

            foreach ($columnWidthsMm as $i => $width) {
                $n = (int) $i + 1;
                $css .= 'table.export-table col:nth-child('.$n.'){width:'.$width.';}';
                $css .= 'table.export-table th:nth-child('.$n.'),table.export-table td:nth-child('.$n.'){width:'.$width.';max-width:'.$width.';}';
            }
        } else {
            $css .= 'table.export-table th{background:#4472C4;color:#fff;padding:8px 10px;}'
                .'table.export-table td{padding:6px 8px;vertical-align:middle;border:1px solid #d0d0d0;}'
                .'.check-box{border:2px solid #333;margin:0 auto;}';
        }

        if ($enablePrintCss) {
            $css .= '@media print{body{margin:12px;}'
                .'table.export-table{font-size:'.($tableFontSize - 1).'px;}'
                .'img.product-img{max-width:'.($imageDisplayWidth + 16).'px;max-height:'.($imageDisplayHeight + 16).'px;}'
                .'th,td{padding:8px!important;}}';
        }

        $css .= '</style>';

        return $css;
    }

    protected static function imageSrcForExport(
        $localPath,
        $imageDir,
        array &$cache,
        array &$zipFiles,
        $maxSize,
        $jpegQuality,
        $embedImages,
        $useAbsoluteImagePaths = false
    ) {
        $key = md5($localPath.'|'.$maxSize.'|'.$jpegQuality.'|'.($embedImages ? 'b64' : 'file').'|'.($useAbsoluteImagePaths ? 'abs' : 'rel'));
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

        if ($useAbsoluteImagePaths) {
            $src = str_replace('\\', '/', $thumbPath);
            $cache[$key] = $src;

            return $src;
        }

        $rel = 'images/'.basename($thumbPath);
        $cache[$key] = $rel;
        $zipFiles[$rel] = $thumbPath;

        return $rel;
    }
}
