<div class="box box-primary">
    <div class="box-header with-border">
        <h3 class="box-title">Header Logo</h3>
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

            <div class="form-group">
                <label>当前运行模式（只读）</label>
                <input type="text" class="form-control" value="{{ ($resolvedSiteMode ?? 'A') === 'B' ? 'B模式 (C2C大厅)' : 'A模式 (选物主站)' }}" readonly>
                <p class="help-block">运行模式已改为由启动脚本与环境文件统一控制，此处仅展示当前生效状态。</p>
                <p class="help-block" style="margin-top: 4px;">历史配置值：{{ strtoupper((string) $activeSiteMode->value) === 'B' ? 'B' : 'A' }}（仅用于排障参考，不再作为控制开关）</p>
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
                    <label>当前 Logo</label>
                    <div>
                        <img src="{{ Storage::disk('public')->url($setting->value) }}" alt="Current Logo" style="max-height: 64px; border: 1px solid #eeeeee; padding: 4px; background: #ffffff;">
                    </div>
                </div>
            @endif

            <div class="form-group {{ $errors->has('logo') ? 'has-error' : '' }}">
                <label for="logo">上传新 Logo（建议透明 PNG 或 SVG）</label>
                <input id="logo" type="file" name="logo" class="form-control" accept="image/*">
                <p class="help-block">上传后会自动压缩并缩放到适合头部展示的尺寸（最长边不超过 320px）。</p>
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
