<?php

namespace App\Http\Requests;

class UserAddressRequest extends Request
{
    public function rules()
    {
        return [
            'province'      => 'required|string',
            'city'          => 'required|string',
            'district'      => 'required|string',
            'address'       => 'required|string|min:5',
            'zip'           => 'nullable|digits_between:1,10',
            'contact_name'  => 'required|string|max:30',
            'contact_phone' => ['required', 'regex:/^1[3-9]\d{9}$/'],
            'id_card'       => ['required', 'regex:/^\d{17}[\dXx]$/'],
            'is_default'    => 'nullable|in:0,1',
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $idCard = strtoupper((string) $this->input('id_card', ''));
            if (!$idCard) {
                return;
            }

            if (!$this->isValidMainlandIdCard($idCard)) {
                $validator->errors()->add('id_card', '身份证号格式不正确');
            }
        });
    }

    protected function isValidMainlandIdCard($idCard)
    {
        if (!preg_match('/^\d{17}[\dX]$/', $idCard)) {
            return false;
        }

        $weights = [7, 9, 10, 5, 8, 4, 2, 1, 6, 3, 7, 9, 10, 5, 8, 4, 2];
        $verifyCodes = ['1', '0', 'X', '9', '8', '7', '6', '5', '4', '3', '2'];
        $sum = 0;

        for ($i = 0; $i < 17; $i++) {
            $sum += intval($idCard[$i]) * $weights[$i];
        }

        $mod = $sum % 11;

        return $verifyCodes[$mod] === $idCard[17];
    }

    public function attributes()
    {
        return [
            'province'      => '省',
            'city'          => '城市',
            'district'      => '地区',
            'address'       => '详细地址',
            'zip'           => '邮编',
            'contact_name'  => '姓名',
            'contact_phone' => '手机号',
            'id_card'       => '身份证号',
            'is_default'    => '默认地址',
        ];
    }
}
