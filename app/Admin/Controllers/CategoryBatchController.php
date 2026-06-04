<?php

namespace App\Admin\Controllers;

use App\Admin\Concerns\RespondsWithAdminBatchJson;
use App\Http\Controllers\Controller;
use App\Services\Admin\CategoryBatchService;
use Illuminate\Http\Request;

class CategoryBatchController extends Controller
{
    use RespondsWithAdminBatchJson;

    public function setShippingMode(Request $request, CategoryBatchService $batch)
    {
        return $this->batchTry(function () use ($request, $batch) {
            return $batch->batchSetShippingMode(
                $this->batchIds($request, '请先勾选分类'),
                $request->input('shipping_mode')
            );
        });
    }

    public function setDirectory(Request $request, CategoryBatchService $batch)
    {
        return $this->batchTry(function () use ($request, $batch) {
            return $batch->batchSetDirectory(
                $this->batchIds($request, '请先勾选分类'),
                (bool) $request->input('is_directory', 1)
            );
        });
    }

    public function moveParent(Request $request, CategoryBatchService $batch)
    {
        return $this->batchTry(function () use ($request, $batch) {
            return $batch->batchMoveParent(
                $this->batchIds($request, '请先勾选分类'),
                $request->input('parent_id')
            );
        });
    }
}
