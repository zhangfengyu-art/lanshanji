<?php

namespace App\Admin\Controllers;

use App\Exceptions\InvalidRequestException;
use App\Models\Order;
use App\Models\SupportFeedback;
use App\Models\UserAddress;
use Carbon\Carbon;
use Encore\Admin\Controllers\ModelForm;
use Encore\Admin\Facades\Admin;
use Encore\Admin\Form;
use Encore\Admin\Grid;
use Encore\Admin\Layout\Content;
use App\Http\Controllers\Controller;

class SupportFeedbacksController extends Controller
{
    use ModelForm;

    public function index()
    {
        return Admin::content(function (Content $content) {
            $content->header('客服反馈管理');
            $content->description('查看用户咨询并推进处理状态');
            $content->body($this->grid());
        });
    }

    public function show($id)
    {
        return $this->edit($id);
    }

    public function edit($id)
    {
        return Admin::content(function (Content $content) use ($id) {
            $content->header('处理客服反馈');
            $content->body($this->form()->edit($id));
        });
    }

    protected function grid()
    {
        return Admin::grid(SupportFeedback::class, function (Grid $grid) {
            $quickStatus = $this->getQuickStatusFromRequest();

            $grid->model()->orderBy('created_at', 'desc');
            if ($quickStatus !== null) {
                $grid->model()->where('status', $quickStatus);
            }

            $grid->id('编号')->sortable();
            $grid->order_no('订单编号');
            $grid->contact_name('联系人');
            $grid->contact_phone('联系方式')->display(function ($value) {
                $phone = trim((string) $value);
                if ($phone !== '' && strtoupper($phone) !== 'N/A') {
                    return $phone;
                }

                $fallbackPhone = trim((string) UserAddress::query()
                    ->where('user_id', $this->user_id)
                    ->orderByDesc('is_default')
                    ->orderByDesc('last_used_at')
                    ->orderByDesc('updated_at')
                    ->orderByDesc('id')
                    ->value('contact_phone'));

                if ($fallbackPhone !== '' && strtoupper($fallbackPhone) !== 'N/A') {
                    return $fallbackPhone;
                }

                $order = Order::query()
                    ->where('no', (string) $this->order_no)
                    ->where('user_id', $this->user_id)
                    ->first(['address']);
                $snapshotPhone = trim((string) data_get(optional($order)->address, 'contact_phone', ''));

                return $snapshotPhone !== '' ? $snapshotPhone : 'N/A';
            });
            $grid->question_type('问题类型')->display(function ($value) {
                return SupportFeedback::$questionTypeMap[$value] ?? $value;
            });
            $grid->status('处理状态')->display(function ($value) {
                $text = SupportFeedback::$statusMap[$value] ?? $value;
                $classMap = [
                    SupportFeedback::STATUS_PENDING_REVIEW => 'warning',
                    SupportFeedback::STATUS_UNDER_INVESTIGATION => 'info',
                    SupportFeedback::STATUS_OFFICIALLY_RESOLVED => 'success',
                ];
                $class = $classMap[$value] ?? 'default';

                return '<span class="label label-'.$class.'">'.$text.'</span>';
            });
            $grid->created_at('提交时间')->sortable();

            $grid->filter(function ($filter) {
                $filter->disableIdFilter();
                $filter->like('order_no', '订单编号');
                $filter->like('contact_name', '联系人');
                $filter->like('contact_phone', '联系方式');
                $filter->equal('status', '处理状态')->select(SupportFeedback::$statusMap);
                $filter->between('created_at', '提交时间')->datetime();
            });

            $grid->disableCreateButton();
            $grid->actions(function ($actions) {
                $actions->disableDelete();
            });
            $grid->tools(function ($tools) use ($quickStatus) {
                $tools->append($this->renderStatusQuickTools($quickStatus));
                $tools->batch(function ($batch) {
                    $batch->disableDelete();
                });
            });
        });
    }

    protected function getQuickStatusFromRequest()
    {
        $status = request()->query('status_quick');
        $allowed = [
            SupportFeedback::STATUS_PENDING_REVIEW,
            SupportFeedback::STATUS_UNDER_INVESTIGATION,
            SupportFeedback::STATUS_OFFICIALLY_RESOLVED,
        ];

        return in_array($status, $allowed, true) ? $status : null;
    }

    protected function renderStatusQuickTools($activeStatus)
    {
        $buttons = [
            '全部' => null,
            '待审核' => SupportFeedback::STATUS_PENDING_REVIEW,
            '调查中' => SupportFeedback::STATUS_UNDER_INVESTIGATION,
            '已结案' => SupportFeedback::STATUS_OFFICIALLY_RESOLVED,
        ];

        $baseQuery = request()->query();
        unset($baseQuery['page']);

        $html = '<div class="btn-group" style="margin-right:10px;">';
        foreach ($buttons as $label => $status) {
            $query = $baseQuery;
            if ($status === null) {
                unset($query['status_quick']);
            } else {
                $query['status_quick'] = $status;
            }

            $url = url()->current().(empty($query) ? '' : '?'.http_build_query($query));
            $isActive = ($status === null && $activeStatus === null) || ($status !== null && $activeStatus === $status);
            $class = $isActive ? 'btn btn-sm btn-primary' : 'btn btn-sm btn-default';
            $html .= '<a class="'.$class.'" href="'.$url.'">'.$label.'</a>';
        }
        $html .= '</div>';

        return $html;
    }

    protected function form()
    {
        return Admin::form(SupportFeedback::class, function (Form $form) {
            $form->display('id', '编号');
            $form->display('order_no', '订单编号');
            $form->display('contact_name', '联系人');
            $form->display('contact_phone', '联系方式');
            $form->display('question_type', '问题类型')->with(function ($value) {
                return SupportFeedback::$questionTypeMap[$value] ?? $value;
            });
            $form->display('message', '问题描述');
            $imagesField = $form->display('images', '凭证图片');
            $imagesField->with(function ($value) {
                $images = is_array($value) ? $value : [];
                if (empty($images)) {
                    return '未上传';
                }

                $html = '';
                foreach ($images as $image) {
                    $url = \Storage::disk('public')->url($image);
                    $html .= '<a href="'.$url.'" target="_blank" style="display:inline-block;margin-right:8px;margin-bottom:8px;">';
                    $html .= '<img src="'.$url.'" style="width:96px;height:96px;object-fit:cover;border:1px solid #ddd;" />';
                    $html .= '</a>';
                }

                return $html;
            });
            if (method_exists($imagesField, 'help')) {
                $imagesField->help('点击图片可查看大图。');
            }

            $form->select('status', '处理状态')
                ->options(SupportFeedback::$statusMap)
                ->rules('required');
            $adminReplyField = $form->textarea('admin_reply', '处理结论/回复');
            if (method_exists($adminReplyField, 'rows')) {
                $adminReplyField->rows(5);
            }
            if (method_exists($adminReplyField, 'help')) {
                $adminReplyField->help('结案时必须填写。');
            }
            $form->display('handled_by', '处理人编号');
            $form->display('handled_at', '处理时间');
            $form->display('created_at', '提交时间');

            $form->saving(function (Form $form) {
                $oldStatus = $form->model()->status;
                $newStatus = $form->status;

                $allowedTransitions = [
                    SupportFeedback::STATUS_PENDING_REVIEW => [
                        SupportFeedback::STATUS_PENDING_REVIEW,
                        SupportFeedback::STATUS_UNDER_INVESTIGATION,
                        SupportFeedback::STATUS_OFFICIALLY_RESOLVED,
                    ],
                    SupportFeedback::STATUS_UNDER_INVESTIGATION => [
                        SupportFeedback::STATUS_UNDER_INVESTIGATION,
                        SupportFeedback::STATUS_OFFICIALLY_RESOLVED,
                    ],
                    SupportFeedback::STATUS_OFFICIALLY_RESOLVED => [
                        SupportFeedback::STATUS_OFFICIALLY_RESOLVED,
                    ],
                ];

                if (!in_array($newStatus, $allowedTransitions[$oldStatus] ?? [], true)) {
                    throw new InvalidRequestException('状态流转不合法，不允许回退');
                }

                if ($newStatus === SupportFeedback::STATUS_OFFICIALLY_RESOLVED && !trim((string) $form->admin_reply)) {
                    throw new InvalidRequestException('已结案状态必须填写处理结论');
                }

                if ($newStatus !== $oldStatus || trim((string) $form->admin_reply) !== trim((string) $form->model()->admin_reply)) {
                    $form->handled_at = Carbon::now();
                    $form->handled_by = Admin::user()->id;
                }
            });
        });
    }
}
