<?php

namespace App\Services;

use ZipArchive;

/**
 * 导出 ZIP：内含可用 Excel/WPS 打开的 HTML 表 + images 目录（相对路径图片，兼容性最好）。
 */
class AdminOrderZipExport
{
    public static function download($filename, array $headers, callable $rowProducer, array $options = [])
    {
        $htmlBasename = (string) ($options['html_basename'] ?? '订单表.html');
        $footerNote = (string) ($options['footer_note'] ?? '请解压本 ZIP 后，用 Excel 或 WPS 打开「'.$htmlBasename.'」查看商品图片。');
        $options['footer_note'] = $footerNote;
        $options['image_embed_mode'] = 'file';

        $built = AdminOrderTableHtmlBuilder::build($headers, $rowProducer, $options);
        $tmpdir = $built['temp_dir'];
        $zipFiles = $built['zip_files'];

        $basename = pathinfo($filename, PATHINFO_FILENAME);
        if ($basename === '' || $basename === '.') {
            $basename = 'orders_export';
        }

        $zipFilename = $basename.'.zip';
        $asciiFallback = preg_replace('/[^a-zA-Z0-9._-]+/', '_', $basename).'.zip';

        $htmlPath = $tmpdir.DIRECTORY_SEPARATOR.$htmlBasename;
        if (@file_put_contents($htmlPath, $built['html']) === false) {
            static::removeDir($tmpdir);

            throw new \RuntimeException('无法写入导出文件');
        }

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
