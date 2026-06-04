<?php

namespace App\Admin\Concerns;

use App\Exceptions\InvalidRequestException;
use Illuminate\Http\Request;

trait RespondsWithAdminBatchJson
{
    protected function batchJsonOk(array $result)
    {
        return response()->json(array_merge(['status' => true], $result));
    }

    protected function batchJsonFail($message, $status = 422)
    {
        return response()->json(['status' => false, 'message' => $message], $status);
    }

    protected function batchIds(Request $request, $emptyLabel = '请先勾选记录')
    {
        $ids = array_values(array_filter(array_map('intval', (array) $request->input('ids', []))));

        if (count($ids) === 0) {
            throw new InvalidRequestException($emptyLabel);
        }

        return $ids;
    }

    protected function batchTry(callable $callback)
    {
        try {
            return $this->batchJsonOk($callback());
        } catch (InvalidRequestException $e) {
            return $this->batchJsonFail($e->getMessage());
        }
    }
}
