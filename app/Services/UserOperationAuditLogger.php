<?php

namespace App\Services;

use App\Models\UserOperationAudit;

class UserOperationAuditLogger
{
    public static function log($action, $targetUserId = null, array $detail = [], $adminUserId = null)
    {
        UserOperationAudit::query()->create([
            'admin_user_id' => $adminUserId,
            'target_user_id' => $targetUserId,
            'action' => $action,
            'detail' => $detail ? json_encode($detail, JSON_UNESCAPED_UNICODE) : null,
            'ip' => request()->ip(),
            'user_agent' => (string) request()->userAgent(),
        ]);
    }
}
