<?php

namespace App\Http\Requests;

class UpdateOrderAddressRequest extends Request
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'province' => 'required|string|max:30',
            'city' => 'required|string|max:30',
            'district' => 'required|string|max:30',
            'address' => 'required|string|min:5|max:500',
            'zip' => 'nullable|digits_between:1,10',
            'contact_name' => 'required|string|max:60',
            'contact_phone' => ['required', 'regex:/^1[3-9]\d{9}$/'],
        ];
    }

    public function attributes()
    {
        return [
            'province' => '省',
            'city' => '城市',
            'district' => '地区',
            'address' => '详细地址',
            'zip' => '邮编',
            'contact_name' => '收件人',
            'contact_phone' => '手机号',
        ];
    }
}
