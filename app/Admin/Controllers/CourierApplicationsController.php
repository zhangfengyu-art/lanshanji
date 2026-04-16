<?php

namespace App\Admin\Controllers;

use App\Models\CourierApplication;
use Encore\Admin\Controllers\ModelForm;
use Encore\Admin\Facades\Admin;
use Encore\Admin\Form;
use Encore\Admin\Grid;
use Encore\Admin\Layout\Content;
use App\Http\Controllers\Controller;

class CourierApplicationsController extends Controller
{
    use ModelForm;

    public function index()
    {
        return Admin::content(function (Content $content) {
            $content->header('代购资质审核');
            $content->description('优先处理待审核申请，确保承接者资质真实可追溯');
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
            $content->header('审核代购资质');
            $content->description('申请ID: ' . (int) $id);
            $content->body($this->form()->edit($id));
        });
    }

    protected function grid()
    {
        return Admin::grid(CourierApplication::class, function (Grid $grid) {
            $quickStatus = request()->query('status_quick');

            $grid->model()
                ->orderByRaw("CASE WHEN status = 'pending' THEN 0 ELSE 1 END")
                ->orderBy('created_at', 'desc');

            if (in_array($quickStatus, [
                CourierApplication::STATUS_PENDING,
                CourierApplication::STATUS_APPROVED,
                CourierApplication::STATUS_REJECTED,
            ], true)) {
                $grid->model()->where('status', $quickStatus);
            }

            $grid->id('ID')->sortable();
            $grid->user_id('用户ID')->sortable();
            $grid->column('user.name', '账号昵称');
            $grid->real_name('真实姓名');
            $grid->phone('手机号');
            $grid->status('审核状态')->display(function ($value) {
                $text = CourierApplication::$statusMap[$value] ?? $value;
                $classMap = [
                    CourierApplication::STATUS_PENDING => 'warning',
                    CourierApplication::STATUS_APPROVED => 'success',
                    CourierApplication::STATUS_REJECTED => 'danger',
                ];
                $class = $classMap[$value] ?? 'default';

                return '<span class="label label-' . $class . '">' . $text . '</span>';
            });
            $grid->created_at('申请时间')->sortable();

            $grid->filter(function (Grid\Filter $filter) {
                $filter->disableIdFilter();
                $filter->equal('status', '审核状态')->select(CourierApplication::$statusMap);
                $filter->like('real_name', '真实姓名');
                $filter->like('phone', '手机号');
                $filter->equal('user_id', '用户ID');
                $filter->between('created_at', '申请时间')->datetime();
            });

            $grid->disableCreateButton();
            $grid->actions(function ($actions) {
                $actions->disableDelete();
            });
            $grid->tools(function ($tools) use ($quickStatus) {
                $buttons = [
                    '全部' => null,
                    '待审核' => CourierApplication::STATUS_PENDING,
                    '已通过' => CourierApplication::STATUS_APPROVED,
                    '已拒绝' => CourierApplication::STATUS_REJECTED,
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

                    $url = url()->current() . (empty($query) ? '' : ('?' . http_build_query($query)));
                    $isActive = ($status === null && !$quickStatus) || ($status !== null && $quickStatus === $status);
                    $class = $isActive ? 'btn btn-sm btn-primary' : 'btn btn-sm btn-default';
                    $html .= '<a class="' . $class . '" href="' . $url . '">' . $label . '</a>';
                }
                $html .= '</div>';

                $tools->append($html);
                $tools->batch(function ($batch) {
                    $batch->disableDelete();
                });
            });
        });
    }

    protected function form()
    {
        return Admin::form(CourierApplication::class, function (Form $form) {
            $form->display('id', 'ID');
            $form->display('user_id', '用户ID');
            $form->display('real_name', '真实姓名');
            $form->display('phone', '手机号');
            $form->display('id_card_number', '身份证号');

            $ticketField = $form->display('flight_ticket_path', '机票凭证');
            $ticketField->with(function ($value) {
                if (!$value) {
                    return '未上传';
                }
                $url = \Storage::disk('public')->url($value);
                return '<a href="' . $url . '" target="_blank"><img src="' . $url . '" style="width:220px;max-width:100%;border:1px solid #ddd;"/></a>';
            });

            $idCardField = $form->display('id_card_photo_path', '证件照片');
            $idCardField->with(function ($value) {
                if (!$value) {
                    return '未上传';
                }
                $url = \Storage::disk('public')->url($value);
                return '<a href="' . $url . '" target="_blank"><img src="' . $url . '" style="width:220px;max-width:100%;border:1px solid #ddd;"/></a>';
            });

            $form->select('status', '审核结论')
                ->options(CourierApplication::$statusMap)
                ->rules('required|in:pending,approved,rejected');
            $note = $form->textarea('admin_note', '审核备注/拒绝原因')->rules('nullable|string|max:1000');
            if (method_exists($note, 'rows')) {
                $note->rows(4);
            }

            $form->display('created_at', '申请时间');
            $form->display('updated_at', '更新时间');

            $form->saving(function (Form $form) {
                $status = (string) $form->status;
                $note = trim((string) $form->admin_note);

                if ($status === CourierApplication::STATUS_REJECTED && $note === '') {
                    throw new \InvalidArgumentException('拒绝申请时必须填写审核备注');
                }
            });
        });
    }
}
