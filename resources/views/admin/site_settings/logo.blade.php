<div class="box box-primary">
    <div class="box-header with-border">
        <h3 class="box-title">站点品牌信息</h3>
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
                <p class="help-block">保存后将优先作为全站 site_mode 判定来源（高于 .env 的 SITE_MODE）。</p>
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
                <p class="help-block">开启后，登录用户可直接访问原本要求邮箱已验证的页面；关闭后恢复正常验证。</p>
                @if($errors->has('disable_email_verification_for_testing'))
                    <span class="help-block">{{ $errors->first('disable_email_verification_for_testing') }}</span>
                @endif
            </div>

            @if($setting->value)
                <div class="form-group">
                    <label>当前站点 Logo</label>
                    <div>
                        <img src="{{ asset('storage/'.ltrim(str_replace('\\', '/', $setting->value), '/')) }}" alt="当前站点 Logo" style="max-height: 64px; border: 1px solid #eeeeee; padding: 4px; background: #ffffff;">
                    </div>
                </div>
            @endif

            <div class="form-group {{ $errors->has('logo') ? 'has-error' : '' }}">
                <label for="logo">上传站点 Logo（建议透明 PNG）<span class="text-danger">*</span></label>
                <input id="logo" type="file" name="logo" class="form-control" accept="image/*"{{ $setting->value ? '' : ' required' }}>
                <p class="help-block">保存后首页左上角将显示此图片，不再显示中文品牌文字。上传后会自动压缩并缩放（最长边不超过 640px）。若当前未显示 Logo，请在此选择图片后点击保存。</p>
                @if($errors->has('logo'))
                    <span class="help-block">{{ $errors->first('logo') }}</span>
                @endif
            </div>
        </div>
        <div class="box-footer">
            <button type="submit" class="btn btn-primary">保存设置</button>
        </div>
    </form>
</div>
