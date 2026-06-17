<?php

namespace App\Services;

/**
 * 将 HTML 表格导出为 PDF（备货表等）。
 */
class AdminOrderPdfExport
{
    public static function download($filename, array $headers, callable $rowProducer, array $options = [])
    {
        if (!class_exists(\Mpdf\Mpdf::class)) {
            throw new \RuntimeException('PDF 导出需要安装 mpdf/mpdf，请在服务器执行 composer require mpdf/mpdf:^7.1');
        }

        $pdfOptions = array_merge($options, [
            'image_embed_mode' => 'base64',
            'enable_print_css' => false,
        ]);

        $built = AdminOrderTableHtmlBuilder::build($headers, $rowProducer, $pdfOptions);
        $html = $built['html'];

        $pdfFilename = pathinfo($filename, PATHINFO_EXTENSION) === 'pdf'
            ? $filename
            : pathinfo($filename, PATHINFO_FILENAME).'.pdf';
        $asciiFallback = preg_replace('/[^a-zA-Z0-9._-]+/', '_', pathinfo($pdfFilename, PATHINFO_FILENAME)).'.pdf';

        $tempDir = storage_path('app/mpdf-tmp');
        if (!is_dir($tempDir) && !@mkdir($tempDir, 0775, true) && !is_dir($tempDir)) {
            $tempDir = sys_get_temp_dir();
        }

        $mpdf = new \Mpdf\Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4-L',
            'margin_left' => 8,
            'margin_right' => 8,
            'margin_top' => 10,
            'margin_bottom' => 12,
            'tempDir' => $tempDir,
            'autoScriptToLang' => true,
            'autoLangToFont' => true,
        ]);

        $mpdf->SetTitle((string) ($options['pdf_title'] ?? '导出'));
        $mpdf->WriteHTML($html);

        $binary = $mpdf->Output('', 'S');
        $disposition = 'attachment; filename="'.$asciiFallback.'"; filename*=UTF-8\'\''.rawurlencode($pdfFilename);

        return response($binary, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => $disposition,
            'Content-Length' => strlen($binary),
        ]);
    }
}
