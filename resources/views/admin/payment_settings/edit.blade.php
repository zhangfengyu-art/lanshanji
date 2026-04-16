<div class="box box-primary">
    <div class="box-header with-border">
        <h3 class="box-title">手动支付二维码</h3>
    </div>
    <form role="form" method="POST" action="{{ route('admin.payment_settings.update') }}" enctype="multipart/form-data">
        <div class="box-body">
            {{ csrf_field() }}

            <div class="form-group">
                <label>支付宝二维码</label>
                @if($setting->alipay_qr)
                    <div style="margin-bottom: 10px;">
                        <img src="{{ asset($setting->alipay_qr) }}" alt="支付宝二维码" style="max-width: 220px; border: 1px solid #ddd;">
                    </div>
                @endif
                <input type="file" name="alipay_qr" accept="image/*">
                <p class="help-block">建议上传正方形 PNG/JPG，清晰度高。</p>
            </div>

            <div class="form-group">
                <label>微信二维码</label>
                @if($setting->wechat_qr)
                    <div style="margin-bottom: 10px;">
                        <img src="{{ asset($setting->wechat_qr) }}" alt="微信二维码" style="max-width: 220px; border: 1px solid #ddd;">
                    </div>
                @endif
                <input type="file" name="wechat_qr" accept="image/*">
                <p class="help-block">建议上传正方形 PNG/JPG，清晰度高。</p>
            </div>
        </div>

        <div class="box-footer">
            <button type="submit" class="btn btn-primary">保存</button>
        </div>
    </form>
</div>
