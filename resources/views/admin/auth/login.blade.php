<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <title>{{ config('admin.title') }} | {{ trans('admin.login') }}</title>
  <meta content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no" name="viewport">
  <link rel="stylesheet" href="{{ admin_asset('/vendor/laravel-admin/AdminLTE/bootstrap/css/bootstrap.min.css') }}">
  <link rel="stylesheet" href="{{ admin_asset('/vendor/laravel-admin/font-awesome/css/font-awesome.min.css') }}">
  <link rel="stylesheet" href="{{ admin_asset('/vendor/laravel-admin/AdminLTE/dist/css/AdminLTE.min.css') }}">
  <link rel="stylesheet" href="{{ admin_asset('/vendor/laravel-admin/AdminLTE/plugins/iCheck/square/blue.css') }}">
</head>
<body class="hold-transition login-page" @if(config('admin.login_background_image'))style="background: url({{ config('admin.login_background_image') }}) no-repeat;background-size: cover;"@endif>
<div class="login-box">
  <div class="login-logo">
    <a href="{{ admin_base_path('/') }}"><b>{{ config('admin.name') }}</b></a>
  </div>
  <div class="login-box-body">
    <p class="login-box-msg">{{ trans('admin.login') }}</p>

    <form action="{{ admin_base_path('auth/login') }}" method="post">
      <div class="form-group has-feedback {!! !$errors->has('username') ?: 'has-error' !!}">
        @if($errors->has('username'))
          @foreach($errors->get('username') as $message)
            <label class="control-label"><i class="fa fa-times-circle-o"></i>{{ $message }}</label><br>
          @endforeach
        @endif
        <input type="text" class="form-control" placeholder="{{ trans('admin.username') }}" name="username" value="{{ old('username') }}" autocomplete="username">
        <span class="glyphicon glyphicon-envelope form-control-feedback"></span>
      </div>

      <div class="form-group has-feedback {!! !$errors->has('password') ?: 'has-error' !!}">
        @if($errors->has('password'))
          @foreach($errors->get('password') as $message)
            <label class="control-label"><i class="fa fa-times-circle-o"></i>{{ $message }}</label><br>
          @endforeach
        @endif
        <input type="password" class="form-control" placeholder="{{ trans('admin.password') }}" name="password" autocomplete="current-password">
        <span class="glyphicon glyphicon-lock form-control-feedback"></span>
      </div>

      @if(!empty($requiresCaptcha))
        <div class="form-group {!! !$errors->has('captcha') ?: 'has-error' !!}">
          @if($errors->has('captcha'))
            @foreach($errors->get('captcha') as $message)
              <label class="control-label"><i class="fa fa-times-circle-o"></i>{{ $message }}</label><br>
            @endforeach
          @endif
          <label class="text-muted" style="font-size:12px;">验证码：{{ $captchaQuestion }} = ?</label>
          <input type="text" class="form-control" name="captcha" placeholder="计算结果" autocomplete="off">
        </div>
      @endif

      <div class="row">
        <div class="col-xs-8">
          <div class="checkbox icheck">
            <label>
              <input type="checkbox" name="remember" value="1" {{ old('remember') ? 'checked' : '' }}> 记住我
            </label>
          </div>
        </div>
        <div class="col-xs-4">
          <input type="hidden" name="_token" value="{{ csrf_token() }}">
          <button type="submit" class="btn btn-primary btn-block btn-flat">{{ trans('admin.login') }}</button>
        </div>
      </div>
    </form>
  </div>
</div>

<script src="{{ admin_asset('/vendor/laravel-admin/AdminLTE/plugins/jQuery/jQuery-2.1.4.min.js') }}"></script>
<script src="{{ admin_asset('/vendor/laravel-admin/AdminLTE/bootstrap/js/bootstrap.min.js') }}"></script>
<script src="{{ admin_asset('/vendor/laravel-admin/AdminLTE/plugins/iCheck/icheck.min.js') }}"></script>
<script>
  $(function () {
    $('input').iCheck({
      checkboxClass: 'icheckbox_square-blue',
      radioClass: 'iradio_square-blue',
      increaseArea: '20%'
    });
  });
</script>
</body>
</html>
