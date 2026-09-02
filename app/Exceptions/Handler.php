<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Throwable;

class Handler extends ExceptionHandler
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
        $this->reportable(function (Throwable $e) {
            // Wave 3 — Sentry error reporting.
            //
            // Fully inert until BOTH conditions hold:
            //   1. sentry/sentry-laravel is installed (class_exists guard)
            //   2. SENTRY_LARAVEL_DSN is configured
            // This keeps local/dev/test environments silent by default.
            if (config('sentry.dsn') && class_exists(\Sentry\SentrySdk::class)) {
                \Sentry\SentrySdk::getCurrentHub()->captureException($e);
            }
        });
    }
}
