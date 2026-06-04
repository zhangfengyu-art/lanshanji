<?php

namespace App\Admin\Controllers;

use App\Admin\Concerns\RespondsWithAdminBatchJson;
use App\Http\Controllers\Controller;
use App\Services\Admin\UserBatchService;
use Illuminate\Http\Request;

class UserBatchController extends Controller
{
    use RespondsWithAdminBatchJson;

    public function ban(Request $request, UserBatchService $batch)
    {
        return $this->batchTry(function () use ($request, $batch) {
            return $batch->batchSetEnabled($this->batchIds($request, '请先勾选用户'), false);
        });
    }

    public function unban(Request $request, UserBatchService $batch)
    {
        return $this->batchTry(function () use ($request, $batch) {
            return $batch->batchSetEnabled($this->batchIds($request, '请先勾选用户'), true);
        });
    }

    public function resetSession(Request $request, UserBatchService $batch)
    {
        return $this->batchTry(function () use ($request, $batch) {
            return $batch->batchResetSession($this->batchIds($request, '请先勾选用户'));
        });
    }

    public function verifyEmail(Request $request, UserBatchService $batch)
    {
        return $this->batchTry(function () use ($request, $batch) {
            return $batch->batchVerifyEmail($this->batchIds($request, '请先勾选用户'));
        });
    }
}
