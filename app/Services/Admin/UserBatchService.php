<?php

namespace App\Services\Admin;

use App\Exceptions\InvalidRequestException;
use App\Models\User;

class UserBatchService
{
    protected function usersByIds(array $ids)
    {
        $ids = array_values(array_filter(array_map('intval', $ids)));
        if (count($ids) === 0) {
            throw new InvalidRequestException('请先勾选用户');
        }

        return User::query()->whereIn('id', $ids)->get();
    }

    public function batchSetEnabled(array $ids, $enabled)
    {
        $updated = 0;
        foreach ($this->usersByIds($ids) as $user) {
            $user->update([
                'is_enabled' => $enabled ? 1 : 0,
                'session_version' => ((int) $user->session_version) + 1,
            ]);
            $updated++;
        }

        $label = $enabled ? '已解封' : '已封禁';

        return ['updated' => $updated, 'message' => $label.' '.$updated.' 个用户'];
    }

    public function batchResetSession(array $ids)
    {
        $updated = 0;
        foreach ($this->usersByIds($ids) as $user) {
            $user->update([
                'session_version' => ((int) $user->session_version) + 1,
            ]);
            $updated++;
        }

        return ['updated' => $updated, 'message' => '已重置 '.$updated.' 个用户的登录态'];
    }

    public function batchVerifyEmail(array $ids)
    {
        $count = User::query()->whereIn('id', array_map('intval', $ids))->update(['email_verified' => 1]);

        return ['updated' => $count, 'message' => '已标记 '.$count.' 个用户邮箱为已验证'];
    }
}
