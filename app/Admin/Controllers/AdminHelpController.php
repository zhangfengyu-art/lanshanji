<?php

namespace App\Admin\Controllers;

use Encore\Admin\Layout\Content;
use Encore\Admin\Facades\Admin;
use App\Http\Controllers\Controller;

class AdminHelpController extends Controller
{
    public function userOps()
    {
        return Admin::content(function (Content $content) {
            $content->header('用户管理操作说明');
            $content->description('第二阶段与第三阶段功能使用指引');
            $content->body(view('admin.help.user_ops'));
        });
    }
}
