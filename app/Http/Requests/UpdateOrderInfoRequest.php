<?php

namespace App\Http\Requests;

class UpdateOrderInfoRequest extends Request
{
    public function rules()
    {
        return [
            'contact_name' => ['required', 'string', 'max:64'],
            'contact_phone' => ['required', 'string', 'max:32'],
            'zip' => ['required', 'string', 'max:16'],
            'address' => ['required', 'string', 'max:255'],
            'remark' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function attributes()
    {
        return [
            'contact_name' => '收货人',
            'contact_phone' => '联系电话',
            'zip' => '邮编',
            'address' => '详细地址',
            'remark' => '备注',
        ];
    }
}