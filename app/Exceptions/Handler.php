<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * The list of the inputs that are never flashed to the session on validation exceptions.
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
     */
    public function register(): void
    {
        $this->renderable(function (\Illuminate\Http\Exceptions\ThrottleRequestsException $e, $request) {
            if ($request->is('api/*') || $request->wantsJson()) {
                $retryAfter = $e->getHeaders()['Retry-After'] ?? 60;
                return response()->json([
                    'message' => "Terlalu banyak permintaan! Demi stabilitas server, silakan tunggu {$retryAfter} detik sebelum mencoba lagi.",
                    'retry_after_seconds' => (int) $retryAfter,
                    'error_code' => 'TOO_MANY_REQUESTS'
                ], 429, $e->getHeaders());
            }
        });

        $this->reportable(function (Throwable $e) {
            //
        });
    }
}
