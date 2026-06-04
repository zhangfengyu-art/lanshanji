<?php

namespace App\Admin\Controllers;

use App\Admin\Concerns\RespondsWithAdminBatchJson;
use App\Http\Controllers\Controller;
use App\Services\Admin\SupportFeedbackBatchService;
use Illuminate\Http\Request;

class SupportFeedbackBatchController extends Controller
{
    use RespondsWithAdminBatchJson;

    public function markHandled(Request $request, SupportFeedbackBatchService $batch)
    {
        return $this->batchTry(function () use ($request, $batch) {
            return $batch->batchMarkHandled($this->batchIds($request, '请先勾选反馈'));
        });
    }

    public function markPending(Request $request, SupportFeedbackBatchService $batch)
    {
        return $this->batchTry(function () use ($request, $batch) {
            return $batch->batchMarkPending($this->batchIds($request, '请先勾选反馈'));
        });
    }

    public function reply(Request $request, SupportFeedbackBatchService $batch)
    {
        return $this->batchTry(function () use ($request, $batch) {
            return $batch->batchReply(
                $this->batchIds($request, '请先勾选反馈'),
                $request->input('admin_reply')
            );
        });
    }
}
