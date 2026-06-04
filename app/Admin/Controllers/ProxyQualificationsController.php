<?php

namespace App\Admin\Controllers;

use App\Models\ProxyQualification;
use App\Models\User;
use Encore\Admin\Facades\Admin;
use Encore\Admin\Form;
use Encore\Admin\Grid;
use Encore\Admin\Layout\Content;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
class ProxyQualificationsController extends Controller
{
    public function index()
    {
        return Admin::content(function (Content $content) {
            $content->header('代购资质审核');
            $content->description('审核代购人提交的资质材料');
            $content->body($this->grid());
        });
    }

    public function show($id)
    {
        $qualification = ProxyQualification::query()->with('user')->findOrFail((int) $id);

        return Admin::content(function (Content $content) use ($qualification) {
            $content->header('资质审核详情');
            $content->description('查看申请材料并作出审核决定');
            $content->body($this->buildDetailPanel($qualification));
        });
    }

    public function approve($id)
    {
        $qualification = ProxyQualification::query()->findOrFail((int) $id);

        if ((int) $qualification->status === ProxyQualification::STATUS_APPROVED) {
            admin_toastr('该申请已经通过，无需重复操作', 'warning');
            return redirect()->back();
        }

        $qualification->update([
            'status'      => ProxyQualification::STATUS_APPROVED,
            'reviewed_by' => Admin::user()->id,
            'reviewed_at' => now(),
            'reject_reason' => null,
        ]);

        admin_toastr('已通过该资质申请', 'success');
        return redirect()->back();
    }

    public function reject(Request $request, $id)
    {
        $qualification = ProxyQualification::query()->findOrFail((int) $id);

        $request->validate([
            'reject_reason' => 'required|string|max:500',
        ]);

        $qualification->update([
            'status'        => ProxyQualification::STATUS_REJECTED,
            'reviewed_by'   => Admin::user()->id,
            'reviewed_at'   => now(),
            'reject_reason' => $request->input('reject_reason'),
        ]);

        admin_toastr('已拒绝该资质申请', 'info');
        return redirect()->route('admin.proxy_qualifications.index');
    }

    // ── 私有方法 ──────────────────────────────────────────────────

    protected function grid()
    {
        return Admin::grid(ProxyQualification::class, function (Grid $grid) {
            $grid->model()
                ->with('user')
                ->orderByRaw("FIELD(status, 0, 2, 1)")  // 待审核优先
                ->orderBy('created_at', 'desc');

            // 快速状态筛选
            $grid->filter(function ($filter) {
                $filter->disableIdFilter();
                $filter->equal('status', '审核状态')->select([
                    '' => '全部',
                    ProxyQualification::STATUS_PENDING  => '待审核',
                    ProxyQualification::STATUS_APPROVED => '已通过',
                    ProxyQualification::STATUS_REJECTED => '已拒绝',
                ]);
                $filter->like('user.name', '申请人姓名');
                $filter->like('user.email', '申请人邮箱');
            });

            $grid->disableCreateButton();
            $grid->disableExport();
            $grid->disableRowSelector();

            $grid->column('id', 'ID')->sortable();
            $grid->column('user.name', '申请人')->display(function () {
                $user = $this->user;
                if (!$user) return '-';
                $url = route('admin.users.show', $user->id);
                return '<a href="' . $url . '">' . htmlspecialchars($user->name) . '</a>';
            });
            $grid->column('user.email', '邮箱')->display(function () {
                return $this->user ? htmlspecialchars($this->user->email) : '-';
            });
            $grid->column('status', '状态')->display(function ($value) {
                $map = [
                    ProxyQualification::STATUS_PENDING  => ['待审核', 'warning'],
                    ProxyQualification::STATUS_APPROVED => ['已通过', 'success'],
                    ProxyQualification::STATUS_REJECTED => ['已拒绝', 'danger'],
                ];
                [$text, $type] = $map[$value] ?? ['未知', 'default'];
                return '<span class="label label-' . $type . '">' . $text . '</span>';
            });
            $grid->column('created_at', '提交时间')->sortable()->display(function ($val) {
                return $val ? date('Y-m-d H:i', strtotime($val)) : '-';
            });
            $grid->column('reviewed_at', '审核时间')->display(function ($val) {
                return $val ? date('Y-m-d H:i', strtotime($val)) : '-';
            });

            // 操作列：查看详情
            $grid->actions(function ($actions) {
                $actions->disableDelete();
                $actions->disableEdit();
                $id = $actions->getKey();
                $actions->prepend('<a href="' . route('admin.proxy_qualifications.show', $id) . '" class="btn btn-xs btn-primary" style="margin-right:4px;"><i class="fa fa-eye"></i> 审核</a>');
            });
        });
    }

    /**
     * 构建详情+审核表单 HTML
     */
    protected function buildDetailPanel(ProxyQualification $q)
    {
        $user     = $q->user;
        $userName = $user ? htmlspecialchars($user->name) : '-';
        $email    = $user ? htmlspecialchars($user->email) : '-';

        $statusMap = [
            ProxyQualification::STATUS_PENDING  => ['待审核', 'warning'],
            ProxyQualification::STATUS_APPROVED => ['已通过', 'success'],
            ProxyQualification::STATUS_REJECTED => ['已拒绝', 'danger'],
        ];
        [$statusText, $statusType] = $statusMap[$q->status] ?? ['未知', 'default'];

        $frontUrl  = $this->publicAssetUrl($q->id_card_front);
        $backUrl   = $this->publicAssetUrl($q->id_card_back);
        $ticketUrl = $this->publicAssetUrl($q->flight_ticket);

        $approveUrl = route('admin.proxy_qualifications.approve', $q->id);
        $rejectUrl  = route('admin.proxy_qualifications.reject', $q->id);

        $rejectReason  = htmlspecialchars((string) $q->reject_reason);
        $submittedNote = htmlspecialchars((string) $q->applicant_note);
        $rejectRow = $q->reject_reason
            ? '<tr><th>拒绝原因</th><td style="color:#c0392b;">' . $rejectReason . '</td></tr>'
            : '';
        $csrf = csrf_token();
        $indexUrl = route('admin.proxy_qualifications.index');

        $html = <<<HTML
<div class="box box-primary">
  <div class="box-header with-border">
    <h3 class="box-title">申请信息</h3>
  </div>
  <div class="box-body">
    <table class="table table-bordered" style="max-width: 700px;">
      <tr><th style="width:140px;">申请人</th><td>{$userName}</td></tr>
      <tr><th>邮箱</th><td>{$email}</td></tr>
      <tr><th>提交时间</th><td>{$q->created_at}</td></tr>
      <tr><th>当前状态</th><td><span class="label label-{$statusType}">{$statusText}</span></td></tr>
      <tr><th>申请备注</th><td>{$submittedNote}</td></tr>
      {$rejectRow}
    </table>
  </div>
</div>

<div class="box box-default">
  <div class="box-header with-border">
    <h3 class="box-title">上传材料</h3>
  </div>
  <div class="box-body">
    <div style="display: flex; gap: 2rem; flex-wrap: wrap;">
      <div style="text-align:center;">
        <p style="font-weight:600; margin-bottom:0.5rem;">身份证正面</p>
        <a href="{$frontUrl}" target="_blank">
          <img src="{$frontUrl}" style="max-width:300px; max-height:200px; border:1px solid #ddd; border-radius:4px;" onerror="this.src='';this.alt='图片无法加载'">
        </a>
      </div>
      <div style="text-align:center;">
        <p style="font-weight:600; margin-bottom:0.5rem;">身份证背面</p>
        <a href="{$backUrl}" target="_blank">
          <img src="{$backUrl}" style="max-width:300px; max-height:200px; border:1px solid #ddd; border-radius:4px;" onerror="this.src='';this.alt='图片无法加载'">
        </a>
      </div>
      <div style="text-align:center;">
        <p style="font-weight:600; margin-bottom:0.5rem;">机票凭证</p>
        <a href="{$ticketUrl}" target="_blank">
          <img src="{$ticketUrl}" style="max-width:300px; max-height:200px; border:1px solid #ddd; border-radius:4px;" onerror="this.src='';this.alt='图片无法加载'">
        </a>
      </div>
    </div>
  </div>
</div>

<div class="box box-success">
  <div class="box-header with-border">
    <h3 class="box-title">审核操作</h3>
  </div>
  <div class="box-body">
    <form method="POST" action="{$approveUrl}" style="display:inline-block; margin-right:1rem;">
      <input type="hidden" name="_token" value="{$csrf}">
      <button type="submit" class="btn btn-success" onclick="return confirm('确认通过该资质申请？')">
        <i class="fa fa-check"></i> 通过申请
      </button>
    </form>

    <button type="button" class="btn btn-danger" data-toggle="modal" data-target="#rejectModal">
      <i class="fa fa-times"></i> 拒绝申请
    </button>

    <a href="{$indexUrl}" class="btn btn-default" style="margin-left:1rem;">
      <i class="fa fa-list"></i> 返回列表
    </a>
  </div>
</div>

<!-- 拒绝原因模态框 -->
<div class="modal fade" id="rejectModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h4 class="modal-title">填写拒绝原因</h4>
      </div>
      <form method="POST" action="{$rejectUrl}">
        <input type="hidden" name="_token" value="{$csrf}">
        <div class="modal-body">
          <div class="form-group">
            <label>拒绝原因 <span style="color:red;">*</span></label>
            <textarea name="reject_reason" class="form-control" rows="4" required placeholder="请填写拒绝原因，将展示给申请人…"></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-default" data-dismiss="modal">取消</button>
          <button type="submit" class="btn btn-danger">确认拒绝</button>
        </div>
      </form>
    </div>
  </div>
</div>
HTML;

        return $html;
    }

    protected function publicAssetUrl($path)
    {
        $path = ltrim((string) $path, '/');
        if ($path === '') {
            return '';
        }

        return asset('storage/' . $path);
    }
}
