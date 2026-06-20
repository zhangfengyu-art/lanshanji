<?php

namespace App\Services;

/**
 * 将 HTML 表格导出为 PDF（备货表等）。
 */
class AdminOrderPdfExport
{
    public static function download($filename, array $headers, callable $rowProducer, array $options = [])
    {
        $binary = static::renderBinary($filename, $headers, $rowProducer, $options);
        $pdfFilename = pathinfo($filename, PATHINFO_EXTENSION) === 'pdf'
            ? $filename
            : pathinfo($filename, PATHINFO_FILENAME).'.pdf';
        $asciiFallback = preg_replace('/[^a-zA-Z0-9._-]+/', '_', pathinfo($pdfFilename, PATHINFO_FILENAME)).'.pdf';

        $disposition = 'attachment; filename="'.$asciiFallback.'"; filename*=UTF-8\'\''.rawurlencode($pdfFilename);

        return response($binary, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => $disposition,
            'Content-Length' => strlen($binary),
        ]);
    }

    /**
     * @return string PDF binary
     */
    public static function renderBinary($filename, array $headers, callable $rowProducer, array $options = [])
    {
        if (!class_exists(\Mpdf\Mpdf::class)) {
            throw new \RuntimeException('PDF 导出需要安装 mpdf/mpdf，请在服务器执行 composer install --no-dev --no-plugins');
        }

        $pdfOptions = array_merge($options, [
            'image_embed_mode' => 'file',
            'image_src_absolute' => true,
            'return_chunks' => true,
            'enable_print_css' => false,
            'style_mode' => $options['style_mode'] ?? 'pdf',
        ]);

        $built = AdminOrderTableHtmlBuilder::build($headers, $rowProducer, $pdfOptions);
        $chunks = $built['html_chunks'] ?? [$built['html']];
        $exportTempDir = $built['temp_dir'] ?? null;

        $tempDir = storage_path('app/mpdf-tmp');
        if (!is_dir($tempDir) && !@mkdir($tempDir, 0775, true) && !is_dir($tempDir)) {
            $tempDir = sys_get_temp_dir();
        }

        try {
            @ini_set('pcre.backtrack_limit', '10000000');

            $mpdf = new \Mpdf\Mpdf([
                'mode' => 'utf-8',
                'format' => (string) ($options['pdf_format'] ?? 'A4-L'),
                'margin_left' => 6,
                'margin_right' => 6,
                'margin_top' => 8,
                'margin_bottom' => 10,
                'tempDir' => $tempDir,
                'dpi' => 150,
                'img_dpi' => 150,
                'autoScriptToLang' => true,
                'autoLangToFont' => true,
            ]);

            $mpdf->SetTitle((string) ($options['pdf_title'] ?? '导出'));
            foreach ($chunks as $chunk) {
                if ($chunk !== '') {
                    $mpdf->WriteHTML($chunk);
                }
            }

            return $mpdf->Output('', 'S');
        } finally {
            static::removeDir($exportTempDir);
        }
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
