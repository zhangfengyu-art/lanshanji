<?php

namespace App\Admin\Controllers;

use App\Models\Admin\Administrator;
use App\Rules\StrongAdminPassword;
use App\Services\Admin\AdminLoginGuardService;
use Encore\Admin\Facades\Admin;
use Encore\Admin\Form;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Validator;

class AuthController extends \Encore\Admin\Controllers\AuthController
{
    /** @var AdminLoginGuardService */
    protected $loginGuard;

    public function __construct(AdminLoginGuardService $loginGuard)
    {
        $this->loginGuard = $loginGuard;
    }

    public function getLogin()
    {
        if ($this->guard()->check()) {
            // 避免 url.intended 指向异常页面后与登录页来回重定向
            session()->forget('url.intended');

            return redirect(admin_url('/'));
        }

        $username = (string) old('username', '');
        $requiresCaptcha = $this->loginGuard->requiresCaptcha(request(), $username);
        if ($requiresCaptcha) {
            $this->loginGuard->refreshCaptchaQuestion();
        }

        return view('admin.auth.login', [
            'requiresCaptcha' => $requiresCaptcha,
            'captchaQuestion' => $this->loginGuard->captchaQuestion(),
        ]);
    }

    public function postLogin(Request $request)
    {
        $username = trim((string) $request->input($this->username(), ''));
        $password = (string) $request->input('password', '');

        if ($this->loginGuard->isLocked($request, $username)) {
            $seconds = $this->loginGuard->lockoutSecondsRemaining($request, $username);
            $minutes = max(1, (int) ceil($seconds / 60));

            return back()->withInput($request->only($this->username()))->withErrors([
                $this->username() => '登录失败次数过多，请 '.$minutes.' 分钟后再试。',
            ]);
        }

        $rules = [
            $this->username() => 'required',
            'password' => 'required',
        ];

        $requiresCaptcha = $this->loginGuard->requiresCaptcha($request, $username);
        if ($requiresCaptcha) {
            $rules['captcha'] = 'required';
        }

        $validator = Validator::make($request->all(), $rules, [
            'captcha.required' => '请输入验证码',
        ]);

        if ($validator->fails()) {
            if ($requiresCaptcha) {
                $this->loginGuard->refreshCaptchaQuestion();
            }

            return back()->withInput($request->only($this->username(), 'remember'))->withErrors($validator);
        }

        if ($requiresCaptcha && !$this->loginGuard->captchaValid($request->input('captcha'))) {
            $this->loginGuard->recordFailure($request, $username);
            $this->loginGuard->refreshCaptchaQuestion();

            return back()->withInput($request->only($this->username(), 'remember'))->withErrors([
                'captcha' => '验证码错误',
            ]);
        }

        $credentials = $request->only([$this->username(), 'password']);

        if ($this->guard()->attempt($credentials, (bool) $request->input('remember'))) {
            $this->loginGuard->clearFailures($request, $username);

            return $this->sendLoginResponse($request);
        }

        $this->loginGuard->recordFailure($request, $username);
        if ($this->loginGuard->requiresCaptcha($request, $username)) {
            $this->loginGuard->refreshCaptchaQuestion();
        }

        return back()->withInput($request->only($this->username(), 'remember'))->withErrors([
            $this->username() => $this->getFailedLoginMessage(),
        ]);
    }

  protected function getFailedLoginMessage()
    {
        return Lang::has('auth.failed')
            ? trans('auth.failed')
            : '用户名或密码错误';
    }

    public function putSetting()
    {
        return $this->settingForm()->update(Admin::user()->id);
    }

    protected function settingForm()
    {
        return Administrator::form(function (Form $form) {
            $form->display('username', trans('admin.username'));
            $form->text('name', trans('admin.name'))->rules('required');
            $form->image('avatar', trans('admin.avatar'));
            $form->password('password', trans('admin.password'))->rules('nullable|confirmed')
                ->help('留空表示不修改。修改时须至少 '.(int) config('admin_security.password_min_length', 16).' 位，含大小写字母与数字。');
            $form->password('password_confirmation', trans('admin.password_confirmation'))->rules('nullable');

            $form->setAction(admin_base_path('auth/setting'));
            $form->ignore(['password_confirmation']);

            $form->saving(function (Form $form) {
                if ($form->password && $form->model()->password != $form->password) {
                    $validator = Validator::make(
                        ['password' => $form->password],
                        ['password' => [new StrongAdminPassword()]]
                    );
                    if ($validator->fails()) {
                        throw new \Exception($validator->errors()->first());
                    }
                    $form->password = bcrypt($form->password);
                } elseif (!$form->password) {
                    $form->password = $form->model()->password;
                }
            });

            $form->saved(function () {
                if (request()->filled('password')) {
                    DB::table(config('admin.database.users_table', 'admin_users'))
                        ->where('id', Admin::user()->id)
                        ->update(['password_changed_at' => now()]);
                }
                admin_toastr(trans('admin.update_succeeded'));

                return redirect(admin_base_path('auth/setting'));
            });
        });
    }
}
