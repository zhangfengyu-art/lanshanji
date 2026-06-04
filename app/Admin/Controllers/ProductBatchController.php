<?php

namespace App\Admin\Controllers;

use App\Http\Controllers\Controller;
use App\Services\AdminCsvExport;
use App\Services\ProductBatchService;
use App\Services\ProductLogisticsExportService;
use Illuminate\Http\Request;

class ProductBatchController extends Controller
{
    protected function jsonOk(array $result)
    {
        return response()->json(array_merge(['status' => true], $result));
    }

    protected function jsonFail($message)
    {
        return response()->json(['status' => false, 'message' => $message], 422);
    }

    protected function ids(Request $request)
    {
        $ids = array_values(array_filter(array_map('intval', (array) $request->input('ids', []))));

        if (count($ids) === 0) {
            throw new \App\Exceptions\InvalidRequestException('请先勾选商品');
        }

        return $ids;
    }

    public function setCategory(Request $request, ProductBatchService $batch)
    {
        try {
            return $this->jsonOk($batch->batchSetCategory($this->ids($request), $request->input('category_id')));
        } catch (\App\Exceptions\InvalidRequestException $e) {
            return $this->jsonFail($e->getMessage());
        }
    }

    public function setShippingMode(Request $request, ProductBatchService $batch)
    {
        try {
            return $this->jsonOk($batch->batchSetShippingMode($this->ids($request), $request->input('shipping_mode')));
        } catch (\App\Exceptions\InvalidRequestException $e) {
            return $this->jsonFail($e->getMessage());
        }
    }

    public function setTobaccoType(Request $request, ProductBatchService $batch)
    {
        try {
            return $this->jsonOk($batch->batchSetTobaccoType($this->ids($request), $request->input('tobacco_type')));
        } catch (\App\Exceptions\InvalidRequestException $e) {
            return $this->jsonFail($e->getMessage());
        }
    }

    public function setSaleStatus(Request $request, ProductBatchService $batch)
    {
        try {
            return $this->jsonOk($batch->batchSetSaleStatus(
                $this->ids($request),
                $request->input('sale_status'),
                $request->input('purchase_limit')
            ));
        } catch (\App\Exceptions\InvalidRequestException $e) {
            return $this->jsonFail($e->getMessage());
        }
    }

    public function setOnSale(Request $request, ProductBatchService $batch)
    {
        try {
            return $this->jsonOk($batch->batchSetOnSale($this->ids($request), (bool) $request->input('on_sale', 1)));
        } catch (\App\Exceptions\InvalidRequestException $e) {
            return $this->jsonFail($e->getMessage());
        }
    }

    public function setLogistics(Request $request, ProductBatchService $batch)
    {
        try {
            return $this->jsonOk($batch->batchSetLogistics(
                $this->ids($request),
                $request->input('unit_weight_grams'),
                $request->input('unit_sticks'),
                (bool) $request->input('only_empty', false)
            ));
        } catch (\App\Exceptions\InvalidRequestException $e) {
            return $this->jsonFail($e->getMessage());
        }
    }

    public function setPurchaseLimit(Request $request, ProductBatchService $batch)
    {
        try {
            return $this->jsonOk($batch->batchSetPurchaseLimit($this->ids($request), $request->input('purchase_limit')));
        } catch (\App\Exceptions\InvalidRequestException $e) {
            return $this->jsonFail($e->getMessage());
        }
    }

    public function inheritCategoryDefaults(Request $request, ProductBatchService $batch)
    {
        try {
            return $this->jsonOk($batch->batchInheritCategoryDefaults($this->ids($request)));
        } catch (\App\Exceptions\InvalidRequestException $e) {
            return $this->jsonFail($e->getMessage());
        }
    }

    public function adjustPrice(Request $request, ProductBatchService $batch)
    {
        try {
            return $this->jsonOk($batch->batchAdjustPrice(
                $this->ids($request),
                $request->input('mode'),
                $request->input('value')
            ));
        } catch (\App\Exceptions\InvalidRequestException $e) {
            return $this->jsonFail($e->getMessage());
        }
    }

    public function exportIncompleteLogistics()
    {
        $rows = [];
        ProductLogisticsExportService::buildQuery()->chunk(200, function ($products) use (&$rows) {
            foreach ($products as $product) {
                if (ProductLogisticsExportService::isIncomplete($product)) {
                    $rows[] = ProductLogisticsExportService::row($product);
                }
            }
        });

        return AdminCsvExport::download(
            ProductLogisticsExportService::filename(),
            ProductLogisticsExportService::headers(),
            $rows
        );
    }
}
