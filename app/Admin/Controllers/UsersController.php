<?php

namespace App\Admin\Controllers;

use App\Models\Order;
use App\Models\SupportFeedback;
use App\Models\User;
use App\Models\UserAddress;
use Illuminate\Http\Request;

use Encore\Admin\Form;
use Encore\Admin\Grid;
use Encore\Admin\Facades\Admin;
use Encore\Admin\Layout\Content;
use App\Http\Controllers\Controller;
use Encore\Admin\Controllers\ModelForm;

class UsersController extends Controller
{
    use ModelForm;

    public function index()
    {
        return Admin::content(function (Content $content) {
            $content->header('用户列表');
            $content->body($this->grid());
        });
    }

    public function show($id)
    {
        $user = User::query()->findOrFail($id);

        $addresses = UserAddress::query()
            ->where('user_id', $user->id)
            ->orderByDesc('is_default')
            ->orderByDesc('last_used_at')
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->get();

        $recentOrders = Order::query()
            ->where('user_id', $user->id)
            ->with(['items.productSku', 'items.product'])
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();

        $recentFeedbacks = SupportFeedback::query()
            ->where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();

        return Admin::content(function (Content $content) use ($user, $addresses, $recentOrders, $recentFeedbacks) {
            $content->header('用户详情');
            $content->description('查看用户完整资料、地址、订单与客服咨询记录');
            $content->body(view('admin.users.show', compact('user', 'addresses', 'recentOrders', 'recentFeedbacks')));
        });
    }

    public function ban(Request $request, $id)
    {
        return $this->setUserEnabledState($id, false, '用户已封禁');
    }

    public function unban(Request $request, $id)
    {
        return $this->setUserEnabledState($id, true, '用户已解封');
    }

    public function resetSession(Request $request, $id)
    {
        $user = User::query()->findOrFail($id);
        $user->update([
            'session_version' => ((int) $user->session_version) + 1,
        ]);

        admin_toastr('用户登录态已重置', 'success');

        return redirect()->back();
    }

    protected function grid()
    {
        $controller = $this;

        return Admin::grid(User::class, function (Grid $grid) use ($controller) {
            $keyword = trim((string) request()->query('keyword', ''));
            if ($keyword !== '') {
                $grid->model()->where(function ($query) use ($keyword) {
                    if (is_numeric($keyword)) {
                        $query->orWhere('id', (int) $keyword);
                    }
                    $query->orWhere('name', 'like', '%' . $keyword . '%')
                        ->orWhere('email', 'like', '%' . $keyword . '%');
                });
            }

            $grid->id('ID')->sortable();
            $grid->name('用户名');
            $grid->email('邮箱');
            $grid->email_verified('已验证邮箱')->display(function ($value) {
                return $value ? '是' : '否';
            });
            $grid->is_enabled('账号状态')->display(function ($value) {
                return $value ? '<span class="label label-success">正常</span>' : '<span class="label label-danger">已封禁</span>';
            });
            $grid->created_at('注册时间');
            $grid->filter(function ($filter) {
                $filter->disableIdFilter();
                $filter->like('name', '用户名');
                $filter->like('email', '邮箱');
                $filter->equal('id', '用户ID');
                $filter->equal('is_enabled', '账号状态')->select([
                    1 => '正常',
                    0 => '已封禁',
                ]);
            });
            $grid->disableCreateButton();
            $grid->actions(function ($actions) use ($controller) {
                $user = $actions->row;
                $actions->disableView();
                $actions->disableDelete();
                $actions->disableEdit();
                $actions->append($controller->renderRowActions($user));
            });
            $grid->tools(function ($tools) use ($controller) {
                $keyword = trim((string) request()->query('keyword', ''));
                $tools->append($controller->renderUserSearchTool($keyword));
                $tools->append(view('admin.users._batch_tools'));
                $tools->batch(function ($batch) {
                    $batch->disableDelete();
                });
            });

            Admin::script(view('admin.partials._batch_helper_script')->render());
            Admin::script(view('admin.users._batch_tools_script')->render());
        });
    }

    public function renderUserSearchTool($keyword = '')
    {
        $keyword = trim((string) $keyword);

        return '<form method="GET" action="" class="form-inline" style="display:inline-block;margin-right:10px;">'
            .'<div class="input-group input-group-sm" style="width:280px;">'
            .'<input type="text" class="form-control" name="keyword" value="'.e($keyword).'" placeholder="搜索用户ID/用户名/邮箱">'
            .'<span class="input-group-btn">'
            .'<button type="submit" class="btn btn-primary">搜索</button>'
            .'</span>'
            .'</div>'
            .'</form>'
            .'<a class="btn btn-sm btn-default" href="'.url()->current().'">重置</a>';
    }

    public function renderRowActions(User $user)
    {
        $detailUrl = route('admin.users.show', ['id' => $user->id]);
        $toggleUrl = $user->is_enabled
            ? route('admin.users.ban', ['id' => $user->id])
            : route('admin.users.unban', ['id' => $user->id]);
        $toggleLabel = $user->is_enabled ? '封禁' : '解封';
        $toggleClass = $user->is_enabled ? 'btn-danger' : 'btn-success';

        return '<a class="btn btn-xs btn-info" style="margin-right:4px;" href="'.$detailUrl.'">查看</a>'
            .'<form action="'.$toggleUrl.'" method="post" style="display:inline-block;margin-right:4px;" onsubmit="return confirm(\'确认'.$toggleLabel.'该用户？\');">'
            .csrf_field()
            .'<button type="submit" class="btn btn-xs '.$toggleClass.'">'.$toggleLabel.'</button>'
            .'</form>'
            .'<form action="'.route('admin.users.reset_session', ['id' => $user->id]).'" method="post" style="display:inline-block;" onsubmit="return confirm(\'确认重置该用户的登录态？\');">'
            .csrf_field()
            .'<button type="submit" class="btn btn-xs btn-warning">重置登录态</button>'
            .'</form>';
    }

    protected function setUserEnabledState($id, $enabled, $message)
    {
        $user = User::query()->findOrFail($id);
        $user->update([
            'is_enabled' => $enabled ? 1 : 0,
            'session_version' => ((int) $user->session_version) + 1,
        ]);

        admin_toastr($message, 'success');

        return redirect()->back();
    }
}
