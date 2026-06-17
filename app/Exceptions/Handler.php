<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\Request;

class Handler extends ExceptionHandler
{
    /**
     * A list of the exception types that are not reported.
     *
     * @var array
     */
    protected $dontReport = [
        InvalidRequestException::class,
        CouponCodeUnavailableException::class,
    ];

    /**
     * A list of the inputs that are never flashed for validation exceptions.
     *
     * @var array
     */
    protected $dontFlash = [
        'password',
        'password_confirmation',
    ];

    /**
     * Report or log an exception.
     *
     * @param  \Exception  $exception
     * @return void
     */
    public function report(Exception $exception)
    {
        parent::report($exception);
    }

    /**
     * Render an exception into an HTTP response.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Exception  $exception
     * @return \Illuminate\Http\Response
     */
    public function render($request, Exception $exception)
    {
        if ($exception instanceof InvalidRequestException) {
            if ($request->expectsJson()) {
                return response()->json(['message' => $exception->getMessage()], 422);
            }

            if ($this->isAdminRequest($request)) {
                return $this->adminErrorResponse($request, $exception->getMessage());
            }

            return redirect()
                ->back()
                ->withInput()
                ->withErrors(['message' => $exception->getMessage()]);
        }

        if ($exception instanceof InternalException) {
            return $exception->render($request);
        }

        if ($this->isAdminRequest($request) && !$request->expectsJson()) {
            \Log::error('后台请求异常', [
                'path' => $request->path(),
                'message' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString(),
            ]);

            return $this->adminErrorResponse($request, '操作失败：'.$exception->getMessage());
        }

        return parent::render($request, $exception);
    }

    protected function isAdminRequest(Request $request)
    {
        $adminPrefix = trim((string) config('admin.route.prefix', 'admin'), '/');

        return $adminPrefix !== '' && $request->is($adminPrefix, $adminPrefix.'/*');
    }

    protected function adminErrorResponse(Request $request, $message)
    {
        $message = trim((string) $message);
        if ($message === '') {
            $message = '未知错误';
        }

        if ($request->header('X-PJAX') || $request->pjax()) {
            return response(
                '<div class="alert alert-danger" style="margin:16px;">'
                .e($message)
                .' <a href="'.e(admin_url('/')).'">返回首页</a></div>',
                500
            );
        }

        return response()->view('admin.errors.request_failed', [
            'message' => $message,
            'homeUrl' => admin_url('/'),
        ], 500);
    }
}
