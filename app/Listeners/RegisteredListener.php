<?php

namespace App\Listeners;

use App\Notifications\EmailVerificationNotification;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Log;

class RegisteredListener
{
    public function handle(Registered $event)
    {
        try {
            $event->user->notify(new EmailVerificationNotification());
        } catch (\Throwable $e) {
            Log::error('注册验证邮件发送失败', [
                'user_id' => $event->user->id,
                'email' => $event->user->email,
                'message' => $e->getMessage(),
            ]);
        }
    }
}
