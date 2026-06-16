<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Request;

class ExecuteOrderRefundRequest extends Request
{
    public function rules()
    {
        $reasonKeys = array_keys(config('order_refund.admin_reasons', []));

        return [
            'reason_code' => ['required', 'string', 'in:'.implode(',', $reasonKeys)],
            'reason_note' => ['nullable', 'string', 'max:500'],
            'supplier_cannot_supply' => ['sometimes', 'boolean'],
            's4_special_approval' => ['sometimes', 'boolean'],
            's4_refund_ratio' => ['nullable', 'numeric'],
        ];
    }

    public function attributes()
    {
        return [
            'reason_code' => '退款原因',
            'reason_note' => '备注',
            'supplier_cannot_supply' => '供应商无法正常供货',
            's4_special_approval' => '已发货特批退款',
            's4_refund_ratio' => '特批退款比例',
        ];
    }
}
