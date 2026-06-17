<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\MessageBag;

class NormalizeAdminFlashMessages
{
    public function handle($request, Closure $next)
    {
        foreach (['success', 'error'] as $key) {
            if (!session()->has($key)) {
                continue;
            }

            $value = session()->get($key);
            if (!is_string($value)) {
                continue;
            }

            session()->flash($key, new MessageBag([
                'title' => $key === 'success' ? '操作成功' : '操作失败',
                'message' => $value,
            ]));
        }

        return $next($request);
    }
}
