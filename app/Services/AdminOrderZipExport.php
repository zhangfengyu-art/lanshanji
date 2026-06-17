<?php

namespace App\Services;

use Illuminate\Support\Str;
use ZipArchive;

/**
 * 导出 ZIP：内含可用 Excel/WPS 打开的 HTML 表 + images 目录（相对路径图片，兼容性最好）。
 */
class AdminOrderZipExport
{
    public static function download($filename, array $headers, callable $rowProducer, array $options = [])
    {
        $textColumns = (array) ($options['text_columns'] ?? []);
        $imageColumns = (array) ($options['image_columns'] ?? []);
        $htmlBasename = (string) ($options['html_basename'] ?? '订单表.html');
        $footerNote = (string) ($options['footer_note'] ?? '请解压本 ZIP 后，用 Excel 或 WPS 打开「'.$htmlBasename.'」查看商品图片。');

        $basename = pathinfo($filename, PATHINFO_FILENAME);
        if ($basename === '' || $basename === '.') {
            $basename = 'orders_export';
        }

        $zipFilename = $basename.'.zip';
        $asciiFallback = preg_replace('/[^a-zA-Z0-9._-]+/', '_', $basename).'.zip';

        $tmpdir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'ord_export_'.uniqid('', true);
        $imageDir = $tmpdir.DIRECTORY_SEPARATOR.'images';
        if (!@mkdir($imageDir, 0777, true) && !is_dir($imageDir)) {
            throw new \RuntimeException('无法创建临时导出目录');
        }

        $imageCache = [];
        $zipFiles = [];
        $htmlPath = $tmpdir.DIRECTORY_SEPARATOR.$htmlBasename;
        $fp = fopen($htmlPath, 'wb');
        if (!$fp) {
            static::removeDir($tmpdir);

            throw new \RuntimeException('无法写入导出文件');
        }

        fwrite($fp, '<html><head><meta charset="UTF-8"></head><body>');
        fwrite($fp, '<table border="1" cellspacing="0" cellpadding="0" style="border-collapse:collapse;font-size:13px;">');
        fwrite($fp, '<tr>');
        foreach ($headers as $header) {
            fwrite($fp, '<th style="background:#4472C4;color:#fff;padding:8px 10px;">'
                .htmlspecialchars((string) $header, ENT_QUOTES, 'UTF-8')
                .'</th>');
        }
        fwrite($fp, '</tr>');

        $emitRow = function (array $row) use ($fp, $textColumns, $imageColumns, $imageDir, &$imageCache, &$zipFiles) {
            fwrite($fp, '<tr>');
            foreach ($row as $index => $cell) {
                $style = 'padding:6px 8px;vertical-align:middle;border:1px solid #d0d0d0;';
                if (in_array($index, $textColumns, true)) {
                    $style .= 'mso-number-format:\@;';
                }

                if (in_array($index, $imageColumns, true)) {
                    $localPath = trim((string) $cell);
                    fwrite($fp, '<td style="'.$style.'text-align:center;width:88px;">');
                    if ($localPath !== '' && is_file($localPath)) {
                        $rel = static::cacheImageForZip($localPath, $imageDir, $imageCache, $zipFiles);
                        if ($rel !== '') {
                            fwrite($fp, '<img src="'.htmlspecialchars($rel, ENT_QUOTES, 'UTF-8')
                                .'" width="72" height="72" style="object-fit:contain;" alt=""/>');
                        } else {
                            fwrite($fp, '—');
                        }
                    } else {
                        fwrite($fp, '—');
                    }
                    fwrite($fp, '</td>');
                } else {
                    $text = (string) $cell;
                    if (strpos($text, "\n") !== false) {
                        $style .= 'white-space:pre-wrap;';
                    }
                    fwrite($fp, '<td style="'.$style.'">'.htmlspecialchars($text, ENT_QUOTES, 'UTF-8').'</td>');
                }
            }
            fwrite($fp, '</tr>');
        };

        $rowProducer($emitRow);

        fwrite($fp, '</table>');
        fwrite($fp, '<p style="font-size:12px;color:#666;margin-top:12px;">'.htmlspecialchars($footerNote, ENT_QUOTES, 'UTF-8').'</p>');
        fwrite($fp, '</body></html>');
        fclose($fp);

        $zipPath = sys_get_temp_dir().DIRECTORY_SEPARATOR.$asciiFallback;
        if (is_file($zipPath)) {
            @unlink($zipPath);
        }

        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            static::removeDir($tmpdir);

            throw new \RuntimeException('无法创建 ZIP 文件');
        }

        $zip->addFile($htmlPath, $htmlBasename);
        foreach ($zipFiles as $rel => $abs) {
            $zip->addFile($abs, $rel);
        }
        $zip->close();
        static::removeDir($tmpdir);

        $disposition = 'attachment; filename="'.$asciiFallback.'"; filename*=UTF-8\'\''.rawurlencode($zipFilename);

        return response()->download($zipPath, $zipFilename, [
            'Content-Type' => 'application/zip',
            'Content-Disposition' => $disposition,
        ])->deleteFileAfterSend(true);
    }

    protected static function cacheImageForZip($sourcePath, $imageDir, array &$cache, array &$zipFiles)
    {
        $key = md5($sourcePath);
        if (isset($cache[$key])) {
            return $cache[$key];
        }

        $thumbPath = OrderAdminExportService::writeThumbnailFile($sourcePath, $imageDir, $key);
        if ($thumbPath === '') {
            return '';
        }

        $rel = 'images/'.basename($thumbPath);
        $cache[$key] = $rel;
        $zipFiles[$rel] = $thumbPath;

        return $rel;
    }

    protected static function removeDir($dir)
    {
        if (!is_dir($dir)) {
            return;
        }

        foreach (scandir($dir) as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir.DIRECTORY_SEPARATOR.$item;
            if (is_dir($path)) {
                static::removeDir($path);
            } else {
                @unlink($path);
            }
        }

        @rmdir($dir);
    }
}
