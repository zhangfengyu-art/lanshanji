<?php

namespace App\Services;

class DailyExportArtifactService
{
    /**
     * @return array{label:string,pdf:string,csv:string,row_count:int,pdf_filename:string,csv_filename:string}
     */
    public function buildOrdersPendingExport()
    {
        $scope = (string) config('daily_export.scopes.orders', 'pending');
        $scopeLabel = OrderAdminExportService::scopeOptions()[$scope] ?? '待处理';
        $statusCode = OrderAdminExportService::exportStatusCodes()[$scope] ?? '待处理';

        return $this->buildOrderExport($scope, $scopeLabel, $statusCode);
    }

    /**
     * @return array{label:string,pdf:string,csv:string,row_count:int,pdf_filename:string,csv_filename:string}
     */
    public function buildStockPrepPendingExport()
    {
        $scope = (string) config('daily_export.scopes.stock_prep', 'pending');
        $scopeLabel = OrderStockPrepExportService::scopeOptions()[$scope] ?? '待处理';
        $statusCode = OrderStockPrepExportService::exportStatusCodes()[$scope] ?? '待处理';

        return $this->buildStockPrepExport($scope, $scopeLabel, $statusCode);
    }

    protected function buildOrderExport($scope, $scopeLabel, $statusCode)
    {
        $headers = OrderAdminExportService::headers();
        $fulfillment = app(OrderFulfillmentService::class);
        $rows = $this->collectRows(function ($emitRow) use ($scope, $fulfillment) {
            OrderAdminExportService::exportRowsWithProducer($scope, $fulfillment, $emitRow);
        });

        $basename = $this->exportBasename('订单', $statusCode, $runAt);
        $pdfFilename = $basename.'.pdf';
        $csvFilename = $basename.'.csv';
        $tempDir = $this->makeTempDir('orders');
        $pdfPath = $tempDir.DIRECTORY_SEPARATOR.$pdfFilename;
        $csvPath = $tempDir.DIRECTORY_SEPARATOR.$csvFilename;

        $pdfBinary = AdminOrderPdfExport::renderBinary(
            $pdfFilename,
            $headers,
            $this->rowEmitter($rows),
            OrderAdminExportService::pdfExportOptions($scopeLabel)
        );
        file_put_contents($pdfPath, $pdfBinary);

        $this->writeCsv($csvPath, $headers, $rows, OrderAdminExportService::IMAGE_COLUMN_INDEXES);

        return [
            'label' => '订单 · '.$scopeLabel,
            'pdf' => $pdfPath,
            'csv' => $csvPath,
            'row_count' => count($rows),
            'pdf_filename' => $pdfFilename,
            'csv_filename' => $csvFilename,
        ];
    }

    protected function buildStockPrepExport($scope, $scopeLabel, $statusCode)
    {
        $headers = OrderStockPrepExportService::headers();
        $fulfillment = app(OrderFulfillmentService::class);
        $rows = $this->collectRows(function ($emitRow) use ($scope, $fulfillment) {
            OrderStockPrepExportService::exportRowsWithProducer($scope, $fulfillment, $emitRow);
        });

        $basename = $this->exportBasename('备货', $statusCode, $runAt);
        $pdfFilename = $basename.'.pdf';
        $csvFilename = $basename.'.csv';
        $tempDir = $this->makeTempDir('stock_prep');
        $pdfPath = $tempDir.DIRECTORY_SEPARATOR.$pdfFilename;
        $csvPath = $tempDir.DIRECTORY_SEPARATOR.$csvFilename;

        $pdfBinary = AdminOrderPdfExport::renderBinary(
            $pdfFilename,
            $headers,
            $this->rowEmitter($rows),
            OrderStockPrepExportService::pdfExportOptions($scope, $scopeLabel)
        );
        file_put_contents($pdfPath, $pdfBinary);

        $exclude = array_merge(
            OrderStockPrepExportService::IMAGE_COLUMN_INDEXES,
            OrderStockPrepExportService::CHECKBOX_COLUMN_INDEXES
        );
        $this->writeCsv($csvPath, $headers, $rows, $exclude);

        return [
            'label' => '备货 · '.$scopeLabel,
            'pdf' => $pdfPath,
            'csv' => $csvPath,
            'row_count' => count($rows),
            'pdf_filename' => $pdfFilename,
            'csv_filename' => $csvFilename,
        ];
    }

    protected function exportBasename($purpose, $statusCode, Carbon $runAt)
    {
        $pdfName = AdminExportFilenameBuilder::buildPdfFilename(
            $purpose,
            $statusCode,
            AdminExportFilenameBuilder::timeCodeForScope('pending')
        );

        return pathinfo($pdfName, PATHINFO_FILENAME);
    }

    protected function collectRows(callable $producer)
    {
        $rows = [];
        $producer(function (array $row) use (&$rows) {
            $rows[] = $row;
        });

        return $rows;
    }

    protected function rowEmitter(array $rows)
    {
        return function ($emitRow) use ($rows) {
            foreach ($rows as $row) {
                $emitRow($row);
            }
        };
    }

    protected function writeCsv($path, array $headers, array $rows, array $excludeIndexes)
    {
        $exclude = array_flip(array_map('intval', $excludeIndexes));
        $csvHeaders = [];
        foreach ($headers as $index => $header) {
            if (!isset($exclude[$index])) {
                $csvHeaders[] = $header;
            }
        }

        $handle = fopen($path, 'wb');
        if ($handle === false) {
            throw new \RuntimeException('无法写入 CSV：'.$path);
        }

        fwrite($handle, "\xEF\xBB\xBF");
        fputcsv($handle, $csvHeaders);

        foreach ($rows as $row) {
            $line = [];
            foreach ($row as $index => $cell) {
                if (isset($exclude[$index])) {
                    continue;
                }
                $line[] = $this->csvCellValue($cell);
            }
            fputcsv($handle, $line);
        }

        fclose($handle);
    }

    protected function csvCellValue($cell)
    {
        if (is_string($cell) && $cell !== '' && is_file($cell)) {
            return '';
        }

        return $cell;
    }

    protected function makeTempDir($prefix)
    {
        $dir = storage_path('app/daily-export/'.uniqid($prefix.'_', true));
        if (!@mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new \RuntimeException('无法创建临时导出目录：'.$dir);
        }

        return $dir;
    }
}
