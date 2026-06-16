<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;

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
     * This is a great spot to send exceptions to Sentry, Bugsnag, etc.
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

            return redirect()
                ->back()
                ->withInput()
                ->withErrors(['message' => $exception->getMessage()]);
        }

        if ($exception instanceof InternalException) {
            return $exception->render($request);
        }

        $adminPrefix = trim((string) config('admin.route.prefix', 'admin'), '/');
        if ($adminPrefix !== '' && $request->is($adminPrefix.'/*') && !$request->expectsJson()) {
            \Log::error('后台请求异常', [
                'path' => $request->path(),
                'message' => $exception->getMessage(),
            ]);

            return redirect()
                ->back()
                ->with('error', '操作失败：'.$exception->getMessage());
        }

        return parent::render($request, $exception);
    }
}
