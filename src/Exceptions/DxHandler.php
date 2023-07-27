<?php

namespace Dx\Payroll\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\Request;
use Throwable;

class DxHandler extends ExceptionHandler
{
    /**
     * A list of exception types with their corresponding custom log levels.
     *
     * @var array<class-string<\Throwable>, \Psr\Log\LogLevel::*>
     */
    protected $levels = [
        //
    ];

    /**
     * A list of the exception types that are not reported.
     *
     * @var array<int, class-string<\Throwable>>
     */
    protected $dontReport = [
        //
    ];

    /**
     * A list of the inputs that are never flashed to the session on validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     *
     * @return void
     */
    public function register()
    {
        $this->renderable(function (\Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException $e, Request $request)
        {
            if ($request->is('api/*')) {
                if (str_contains($e->getMessage(), 'api/login')) {
                    return response()->json([
                        'message' => 'Bearer Token is required',
                    ], 404);
                }
                return response()->json([
                    'message' => $e->getMessage(),
                ], 404);
            }
        });

        $this->renderable(function (\Predis\Connection\ConnectionException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'message' => 'Error Occurred Server',
                ], 404);
            }
        });
    }
}
