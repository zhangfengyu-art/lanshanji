<?php

namespace App\Admin\Controllers;

use App\Http\Controllers\Controller;
use App\Models\SupportFeedback;
use App\Services\AdminCsvExport;
use App\Services\SupportFeedbackAdminExportService;
use Encore\Admin\Facades\Admin;
use Encore\Admin\Form;
use Encore\Admin\Grid;
use Encore\Admin\Layout\Content;
use Illuminate\Http\Request;

class SupportFeedbacksController extends Controller
{
    public function index()
    {
        return Admin::content(function (Content $content) {
            $content->header('客户反馈');
            $content->description('查看并回复用户提交的问题');
            $content->body($this->grid());
        });
    }

    public function edit($id)
    {
        return Admin::content(function (Content $content) use ($id) {
            $content->header('处理客户反馈');
            $content->body($this->form()->edit($id));
        });
    }

    public function update($id)
    {
        return $this->form()->update($id);
    }

    public function export(Request $request)
    {
        $scope = (string) $request->query('scope', 'all');
        $options = SupportFeedbackAdminExportService::scopeOptions();
        if (!array_key_exists($scope, $options)) {
            $scope = 'all';
        }

        $rows = [];
        SupportFeedbackAdminExportService::buildQuery($scope)->chunk(200, function ($items) use (&$rows) {
            foreach ($items as $feedback) {
                $rows[] = SupportFeedbackAdminExportService::row($feedback);
            }
        });

        return AdminCsvExport::download(
            SupportFeedbackAdminExportService::filename($scope),
            SupportFeedbackAdminExportService::headers(),
            $rows
        );
    }

    protected function grid()
    {
        return Admin::grid(SupportFeedback::class, function (Grid $grid) {
            $grid->model()->with('user')->orderBy('created_at', 'desc');

            $grid->disableCreateButton();
            $grid->disableExport();

            $grid->tools(function ($tools) {
                $tools->append(view('admin.support_feedbacks._batch_tools'));
                $tools->append(view('admin.partials.export_dropdown', [
                    'exportBaseUrl' => route('admin.support_feedbacks.export'),
                    'scopeOptions' => SupportFeedbackAdminExportService::scopeOptions(),
                    'dropdownLabel' => '导出反馈',
                ]));
                $tools->batch(function ($batch) {
                    $batch->disableDelete();
                });
            });

            Admin::script(view('admin.partials._batch_helper_script')->render());
            Admin::script(view('admin.support_feedbacks._batch_tools_script')->render());

            $grid->filter(function ($filter) {
                $filter->disableIdFilter();
                $filter->equal('status', '状态')->select([
                    '' => '全部',
                    SupportFeedback::STATUS_PENDING => '待处理',
                    SupportFeedback::STATUS_HANDLED => '已回复',
                ]);
            });

            $grid->column('id', 'ID')->sortable();
            $grid->column('contact_name', '用户')->display(function ($contactName) {
                // laravel-admin 用 newFromBuilder 渲染行时，预加载的 user 常为数组而非模型
                $userName = data_get($this->user, 'name');
                $userEmail = data_get($this->user, 'email');
                if ($userName !== null && $userName !== '') {
                    $label = trim((string) $userName);
                    if ($userEmail) {
                        $label .= ' ('.$userEmail.')';
                    }

                    return htmlspecialchars($label);
                }

                $label = trim((string) $contactName);
                if ($label === '' && $this->contact_phone) {
                    $label = (string) $this->contact_phone;
                }

                return htmlspecialchars($label !== '' ? $label : '-');
            });
            $grid->column('order_no', '订单号');
            $grid->column('question_type', '类型')->display(function ($value) {
                return SupportFeedback::questionTypeOptions()[$value] ?? $value;
            });
            $grid->column('message', '反馈内容')->limit(60);
            $grid->column('status', '状态')->display(function ($value) {
                if ($value === SupportFeedback::STATUS_HANDLED) {
                    return '<span class="label label-success">已回复</span>';
                }

                return '<span class="label label-warning">待处理</span>';
            });
            $grid->column('created_at', '提交时间')->sortable();

            $grid->actions(function ($actions) {
                $actions->disableDelete();
                $actions->disableView();
                $actions->prepend(
                    '<a href="'.admin_url('support-feedbacks/'.$actions->getKey().'/edit').'" class="btn btn-xs btn-primary" style="margin-right:4px;">'
                    .'<i class="fa fa-reply"></i> 回复</a>'
                );
            });
        });
    }

    protected function form()
    {
        return Admin::form(SupportFeedback::class, function (Form $form) {
            $form->display('id', 'ID');
            $form->display('contact_name', '联系人');
            $form->display('contact_phone', '联系电话');
            $form->display('order_no', '订单号');
            $form->display('question_type', '问题类型');
            $form->display('message', '用户反馈');

            $form->textarea('admin_reply', '管理员回复')->rules('required');
            $form->select('status', '状态')->options([
                SupportFeedback::STATUS_PENDING => '待处理',
                SupportFeedback::STATUS_HANDLED => '已回复',
            ])->default(SupportFeedback::STATUS_HANDLED);

            $form->saving(function (Form $form) {
                if ($form->status === SupportFeedback::STATUS_HANDLED) {
                    $form->model()->handled_by = Admin::user()->id;
                    $form->model()->handled_at = now();
                }
            });
        });
    }
}
