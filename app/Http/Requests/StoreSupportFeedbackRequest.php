<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class StoreSupportFeedbackRequest extends Request
{
    protected function prepareForValidation()
    {
        $orderNo = trim((string) $this->input('order_no', ''));

        $this->merge([
            'order_no' => $orderNo,
        ]);
    }

    public function rules()
    {
        return [
            'order_no' => [
                'required',
                'string',
                'max:32',
                Rule::exists('orders', 'no')->where(function ($query) {
                    $query->where('user_id', $this->user()->id);
                }),
            ],
            'question_type' => ['required', Rule::in(['ORDER_DELIVERY', 'PAYMENT', 'AFTER_SALES', 'OTHER'])],
            'message' => 'required|string|min:10|max:1000',
            'locked_order_no' => 'nullable|in:0,1',
            'images' => 'nullable|array|max:5',
            'images.*' => 'image|max:5120',
        ];
    }

    public function messages()
    {
        return [
            'order_no.exists' => '订单编号不存在，或不属于当前登录账号。',
        ];
    }

    public function attributes()
    {
        return [
            'order_no' => '订单编号',
            'question_type' => '问题类型',
            'message' => '问题描述',
            'images' => '上传图片',
            'images.*' => '上传图片',
        ];
    }
}
