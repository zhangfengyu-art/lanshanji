<?php

namespace App\Admin\Controllers;

use Encore\Admin\Facades\Admin;
use Encore\Admin\Layout\Content;
use App\Http\Controllers\Controller;

class UserOperationAuditsController extends Controller
{
    // 审计模块当前未启用（无路由暴露），保留占位避免误接入旧逻辑。
    public function index()
    {
        return Admin::content(function (Content $content) {
            $content->header('用户操作审计日志（已停用）');
            $content->description('当前版本未启用审计日志模块。');
        });
    }
}
