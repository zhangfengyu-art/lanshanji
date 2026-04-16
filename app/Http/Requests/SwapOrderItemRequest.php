<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SwapOrderItemRequest extends FormRequest
{
    public function rules()
    {
        return [
            'sku_code' => ['required', 'string', 'max:64'],
        ];
    }

    public function authorize()
    {
        return true;
    }

    public function messages()
    {
        return [
            'sku_code.required' => '请输入新商品货号',
        ];
    }
}