<?php

namespace App\Services;

use App\Models\SupportFeedback;
use Carbon\Carbon;
class SupportFeedbackAdminExportService
{
    public static function scopeOptions()
    {
        return [
            'all' => '全部反馈',
            'today' => '今日提交',
            'week' => '近7日提交',
            'pending' => '待处理',
            'handled' => '已回复',
        ];
    }

    public static function buildQuery($scope)
    {
        $query = SupportFeedback::query()
            ->with('user')
            ->orderBy('created_at', 'desc');

        $today = Carbon::today();

        switch ($scope) {
            case 'today':
                $query->whereDate('created_at', $today);
                break;
            case 'week':
                $query->where('created_at', '>=', $today->copy()->subDays(6)->startOfDay());
                break;
            case 'pending':
                $query->where('status', SupportFeedback::STATUS_PENDING);
                break;
            case 'handled':
                $query->where('status', SupportFeedback::STATUS_HANDLED);
                break;
            case 'all':
            default:
                break;
        }

        return $query;
    }

    public static function headers()
    {
        return [
            '编号',
            '用户昵称',
            '用户邮箱',
            '订单号',
            '联系人',
            '联系电话',
            '问题类型',
            '反馈内容',
            '处理状态',
            '管理员回复',
            '提交时间',
            '处理时间',
        ];
    }

    public static function row(SupportFeedback $feedback)
    {
        return [
            $feedback->id,
            optional($feedback->user)->name,
            optional($feedback->user)->email,
            $feedback->order_no,
            $feedback->contact_name,
            $feedback->contact_phone,
            self::questionTypeLabel($feedback->question_type),
            $feedback->message,
            $feedback->status === SupportFeedback::STATUS_HANDLED ? '已回复' : '待处理',
            $feedback->admin_reply,
            optional($feedback->created_at)->format('Y-m-d H:i:s'),
            $feedback->handled_at ? $feedback->handled_at->format('Y-m-d H:i:s') : '',
        ];
    }

    protected static function questionTypeLabel($type)
    {
        $map = [
            'order' => '订单问题',
            'payment' => '支付问题',
            'shipping' => '物流/发货',
            'refund' => '退款售后',
            'product' => '商品咨询',
            'account' => '账号问题',
            'other' => '其他',
        ];
        $key = strtolower((string) $type);

        return $map[$key] ?? (string) $type;
    }

    public static function filename($scope)
    {
        $label = self::scopeOptions()[$scope] ?? '客户反馈';
        $safe = preg_replace('/[^\x{4e00}-\x{9fa5}a-zA-Z0-9_-]+/u', '', $label);
        if ($safe === '') {
            $safe = 'feedbacks';
        }

        return $safe.'_'.date('Ymd_His').'.csv';
    }
}
