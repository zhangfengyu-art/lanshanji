<?php

namespace App\Services\Admin;

use App\Exceptions\InvalidRequestException;
use App\Models\SupportFeedback;
use Encore\Admin\Facades\Admin;

class SupportFeedbackBatchService
{
    protected function feedbacksByIds(array $ids)
    {
        $ids = array_values(array_filter(array_map('intval', $ids)));
        if (count($ids) === 0) {
            throw new InvalidRequestException('请先勾选反馈');
        }

        return SupportFeedback::query()->whereIn('id', $ids)->get();
    }

    public function batchMarkHandled(array $ids, $adminReply = null)
    {
        $adminId = Admin::user() ? (int) Admin::user()->id : null;
        $now = now();
        $updated = 0;

        foreach ($this->feedbacksByIds($ids) as $feedback) {
            $payload = [
                'status' => SupportFeedback::STATUS_HANDLED,
                'handled_by' => $adminId,
                'handled_at' => $now,
            ];
            if ($adminReply !== null && trim((string) $adminReply) !== '') {
                $payload['admin_reply'] = trim((string) $adminReply);
            }
            $feedback->update($payload);
            $updated++;
        }

        return ['updated' => $updated, 'message' => '已标记 '.$updated.' 条反馈为已回复'];
    }

    public function batchMarkPending(array $ids)
    {
        $count = SupportFeedback::query()->whereIn('id', array_map('intval', $ids))->update([
            'status' => SupportFeedback::STATUS_PENDING,
            'admin_reply' => null,
            'handled_by' => null,
            'handled_at' => null,
        ]);

        return ['updated' => $count, 'message' => '已改回待处理 '.$count.' 条反馈'];
    }

    public function batchReply(array $ids, $reply)
    {
        $reply = trim((string) $reply);
        if ($reply === '') {
            throw new InvalidRequestException('请填写回复内容');
        }

        return $this->batchMarkHandled($ids, $reply);
    }
}
