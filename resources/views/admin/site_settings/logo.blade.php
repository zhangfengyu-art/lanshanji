@php
    $preview = function ($path) {
        if (!$path) {
            return null;
        }

        return site_setting_image_url($path);
    };
@endphp
<div class="box box-primary">
    <div class="box-header with-border">
        <h3 class="box-title">站点品牌与图标</h3>
    </div>
    @if(session('logo_upload_success'))
        <div class="alert alert-success" style="margin: 10px 15px 0;">
            {{ session('logo_upload_success') }}
        </div>
    @endif
    <form action="{{ route('admin.site_settings.logo.update') }}" method="POST" enctype="multipart/form-data">
        {{ csrf_field() }}
        <div class="box-body">
            <div class="form-group {{ $errors->has('brand_text_zh') ? 'has-error' : '' }}">
                <label for="brand_text_zh">品牌中文文字</label>
                <input id="brand_text_zh" type="text" name="brand_text_zh" class="form-control" value="{{ old('brand_text_zh', $brandTextZh->value) }}" maxlength="60">
                @if($errors->has('brand_text_zh'))
                    <span class="help-block">{{ $errors->first('brand_text_zh') }}</span>
                @endif
            </div>

            <div class="form-group {{ $errors->has('brand_text_en') ? 'has-error' : '' }}">
                <label for="brand_text_en">品牌英文文字</label>
                <input id="brand_text_en" type="text" name="brand_text_en" class="form-control" value="{{ old('brand_text_en', $brandTextEn->value) }}" maxlength="120">
                @if($errors->has('brand_text_en'))
                    <span class="help-block">{{ $errors->first('brand_text_en') }}</span>
                @endif
            </div>

            <div class="form-group {{ $errors->has('jpy_per_cny') ? 'has-error' : '' }}">
                <label for="jpy_per_cny">日元汇率（1 人民币 = ? 日元）</label>
                <input id="jpy_per_cny" type="number" step="0.000001" min="0.01" name="jpy_per_cny" class="form-control" value="{{ old('jpy_per_cny', $jpyPerCny->value) }}">
                <p class="help-block">A 站订单按日元核算，用户跳转 B 站支付时按此汇率换算为人民币收款。例如填 22 表示 2200 日元 ≈ 100 元人民币。</p>
                @if($errors->has('jpy_per_cny'))
                    <span class="help-block">{{ $errors->first('jpy_per_cny') }}</span>
                @endif
            </div>

            <div class="form-group {{ $errors->has('active_site_mode') ? 'has-error' : '' }}">
                <label for="active_site_mode">当前运行模式</label>
                <select id="active_site_mode" name="active_site_mode" class="form-control">
                    <option value="A" {{ old('active_site_mode', $activeSiteMode->value) === 'A' ? 'selected' : '' }}>A 模式（选品主站）</option>
                    <option value="B" {{ old('active_site_mode', $activeSiteMode->value) === 'B' ? 'selected' : '' }}>B 模式（互助代购大厅）</option>
                </select>
                <p class="help-block">保存后将优先作为全站 site_mode 判定来源（高于 .env 的 SITE_MODE）。生产双机部署时仍以各机 .env 的 SITE_MODE 为准。</p>
                @if($errors->has('active_site_mode'))
                    <span class="help-block">{{ $errors->first('active_site_mode') }}</span>
                @endif
            </div>

            <div class="form-group {{ $errors->has('disable_email_verification_for_testing') ? 'has-error' : '' }}">
                <label style="display: block;">测试开关</label>
                <label style="font-weight: normal;">
                    <input
                        type="checkbox"
                        name="disable_email_verification_for_testing"
                        value="1"
                        {{ old('disable_email_verification_for_testing', $disableEmailVerificationForTesting->value) ? 'checked' : '' }}
                    >
                    跳过前台邮箱验证（仅测试期使用）
                </label>
                @if($errors->has('disable_email_verification_for_testing'))
                    <span class="help-block">{{ $errors->first('disable_email_verification_for_testing') }}</span>
                @endif
            </div>

            <hr>
            <h4>A 站首页图标</h4>
            <p class="help-block">显示在 A 站（美国选品站）首页左上角，建议透明 PNG，宽不超过 640px。</p>

            @if($setting->value)
                <div class="form-group">
                    <label>当前 A 站首页 Logo</label>
                    <div>
                        <img src="{{ $preview($setting->value) }}" alt="A 站首页 Logo" style="max-height: 64px; border: 1px solid #eeeeee; padding: 4px; background: #ffffff;">
                    </div>
                </div>
            @endif

            <div class="form-group {{ $errors->has('logo') ? 'has-error' : '' }}">
                <label for="logo">上传 A 站首页 Logo</label>
                <input id="logo" type="file" name="logo" class="form-control" accept="image/*">
                @if($errors->has('logo'))
                    <span class="help-block">{{ $errors->first('logo') }}</span>
                @endif
            </div>

            <hr>
            <h4>标签页图标（Favicon）</h4>
            <p class="help-block">浏览器标签页上的小图标，建议 32×32 或更大的正方形 PNG。A/B 站可分别设置；未上传 A 站 favicon 时会暂时回退为首页 Logo。</p>

            @if($faviconA->value)
                <div class="form-group">
                    <label>当前 A 站标签页图标</label>
                    <div>
                        <img src="{{ $preview($faviconA->value) }}" alt="A 站 favicon" style="width: 32px; height: 32px; border: 1px solid #eeeeee; padding: 2px; background: #ffffff;">
                    </div>
                </div>
            @endif

            <div class="form-group {{ $errors->has('favicon_a') ? 'has-error' : '' }}">
                <label for="favicon_a">上传 A 站标签页图标</label>
                <input id="favicon_a" type="file" name="favicon_a" class="form-control" accept="image/*">
                @if($errors->has('favicon_a'))
                    <span class="help-block">{{ $errors->first('favicon_a') }}</span>
                @endif
            </div>

            @if($faviconB->value)
                <div class="form-group">
                    <label>当前 B 站标签页图标</label>
                    <div>
                        <img src="{{ $preview($faviconB->value) }}" alt="B 站 favicon" style="width: 32px; height: 32px; border: 1px solid #eeeeee; padding: 2px; background: #ffffff;">
                    </div>
                </div>
            @endif

            <div class="form-group {{ $errors->has('favicon_b') ? 'has-error' : '' }}">
                <label for="favicon_b">上传 B 站标签页图标</label>
                <input id="favicon_b" type="file" name="favicon_b" class="form-control" accept="image/*">
                @if($errors->has('favicon_b'))
                    <span class="help-block">{{ $errors->first('favicon_b') }}</span>
                @endif
            </div>

            <hr>
            <h4>海淘售后群二维码</h4>
            <p class="help-block">付款成功后的订单详情页会展示此二维码，便于客户扫码入群。上传新图将自动替换旧图，可随时更换微信群二维码。</p>

            <div class="form-group {{ $errors->has('after_sale_group_notice') ? 'has-error' : '' }}">
                <label for="after_sale_group_notice">展示标题</label>
                <input id="after_sale_group_notice" type="text" name="after_sale_group_notice" class="form-control" value="{{ old('after_sale_group_notice', $afterSaleGroupNotice->value) }}" maxlength="120" placeholder="扫码加入海淘售后群">
                <p class="help-block">显示在二维码上方的简短说明，例如「扫码加入海淘售后群」。</p>
                @if($errors->has('after_sale_group_notice'))
                    <span class="help-block">{{ $errors->first('after_sale_group_notice') }}</span>
                @endif
            </div>

            @if($afterSaleGroupQr->value)
                <div class="form-group">
                    <label>当前售后群二维码</label>
                    <div>
                        <img src="{{ $preview($afterSaleGroupQr->value) }}" alt="海淘售后群二维码" style="max-width: 180px; border: 1px solid #eeeeee; padding: 6px; background: #ffffff;">
                    </div>
                </div>
            @endif

            <div class="form-group {{ $errors->has('after_sale_group_qr') ? 'has-error' : '' }}">
                <label for="after_sale_group_qr">上传售后群二维码</label>
                <input id="after_sale_group_qr" type="file" name="after_sale_group_qr" class="form-control" accept="image/*">
                @if($errors->has('after_sale_group_qr'))
                    <span class="help-block">{{ $errors->first('after_sale_group_qr') }}</span>
                @endif
            </div>
        </div>
        <div class="box-footer">
            <button type="submit" class="btn btn-primary">保存设置</button>
        </div>
    </form>
</div>
