<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;
use App\Models\ProductSku;

class OrderRequest extends Request
{
    public function authorize()
    {
        \Log::error('OrderRequest::authorize() called');
        return true;
    }
    
    public function rules()
    {
        $items = $this->input('items', []);
        $rules = [
            'address_id'     => ['required', Rule::exists('user_addresses', 'id')->where('user_id', $this->user()->id)],
            'items'          => ['required', 'array'],
        ];
        
        // 检查是否是原生求购单
        $isNativeProcurement = false;
        if (!empty($items[0])) {
            $isNativeProcurement = isset($items[0]['is_native_procurement']) && $items[0]['is_native_procurement'];
        }
        
        \Log::info('OrderRequest::rules() called', [
            'is_native_procurement' => $isNativeProcurement,
            'items_count' => count($items),
            'first_item' => json_encode($items[0] ?? null),
            'first_item_is_native' => json_encode($items[0]['is_native_procurement'] ?? 'NOT SET'),
            'first_item_is_native_type' => gettype($items[0]['is_native_procurement'] ?? null),
        ]);
        
        if ($isNativeProcurement) {
            \Log::info('Using native procurement validation rules');
            // 原生求购单：只需要基本的格式验证，跳过 SKU 存在性验证
            foreach ($items as $index => $item) {
                $rules["items.{$index}.sku_id"] = ['required', 'integer'];
                $rules["items.{$index}.amount"] = ['required', 'integer', 'min:1'];
            }
        } else {
            \Log::info('Using normal product validation rules');
            // 普通商品订单：需要验证 SKU 存在性
            foreach ($items as $index => $item) {
                $rules["items.{$index}.sku_id"] = [
                    'required',
                    function ($attribute, $value, $fail) use ($index, $items) {
                        \Log::info("Validating SKU for item {$index}", [
                            'sku_id' => $value,
                            'item' => json_encode($items[$index] ?? null),
                        ]);
                        
                        if (!$sku = ProductSku::find($value)) {
                            $fail('该商品不存在');
                            return;
                        }
                        if (!$sku->product->on_sale) {
                            $fail('该商品未上架');
                            return;
                        }
                        if ($sku->isDepleted()) {
                            $fail('该商品已售罄');
                            return;
                        }

                        $amount = (int) data_get($items, "{$index}.amount", 0);
                        $maxAllowed = $sku->getOrderMaxQty();
                        if ($amount > 0 && $maxAllowed > 0 && $amount > $maxAllowed) {
                            $fail('该商品限购，单笔最多购买 ' . $maxAllowed . ' 件');
                            return;
                        }
                    },
                ];
                
                $rules["items.{$index}.amount"] = ['required', 'integer', 'min:1'];
            }
        }
        
        return $rules;
    }

    public function attributes()
    {
        return [
            'address_id' => '收货地址',
        ];
    }

    public function messages()
    {
        return [
            'address_id.required' => '请先选择或添加收货地址。',
            'address_id.exists' => '收货地址无效，请重新选择或新建。',
        ];
    }
}
