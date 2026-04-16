<?php

namespace App\Admin\Controllers;

use App\Http\Controllers\Controller;
use App\Models\PaymentSetting;
use Encore\Admin\Facades\Admin;
use Encore\Admin\Layout\Content;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;

class PaymentSettingsController extends Controller
{
    public function edit()
    {
        $setting = PaymentSetting::query()->firstOrCreate(['id' => 1]);

        return Admin::content(function (Content $content) use ($setting) {
            $content->header('支付二维码设置');
            $content->description('上传后将用于订单详情页手动扫码弹窗');
            $content->body(view('admin.payment_settings.edit', ['setting' => $setting]));
        });
    }

    public function update(Request $request)
    {
        $this->validate($request, [
            'alipay_qr' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
            'wechat_qr' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
        ]);

        $setting = PaymentSetting::query()->firstOrCreate(['id' => 1]);

        if ($request->hasFile('alipay_qr')) {
            $setting->alipay_qr = $this->storeQrImage($request->file('alipay_qr'), 'alipay');
        }

        if ($request->hasFile('wechat_qr')) {
            $setting->wechat_qr = $this->storeQrImage($request->file('wechat_qr'), 'wechat');
        }

        $setting->save();

        admin_toastr('支付二维码已更新', 'success');

        return redirect()->route('admin.payment_settings.edit');
    }

    private function storeQrImage($file, $prefix)
    {
        $ext = $file->getClientOriginalExtension() ?: 'png';
        $filename = sprintf('%s-qr-%s-%s.%s', $prefix, date('YmdHis'), Str::random(6), $ext);
        Storage::disk('public')->putFileAs('images', $file, $filename);

        return 'images/' . $filename;
    }
}
