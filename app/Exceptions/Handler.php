<?php

namespace App\Exceptions;

use App\Traits\ApiResponser;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

class Handler extends ExceptionHandler
{
    use ApiResponser;
    /**
     * A list of the exception types that are not reported.
     *
     * @var array
     */
    protected $dontReport = [
        //
    ];

    /**
     * A list of the inputs that are never flashed for validation exceptions.
     *
     * @var array
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
        $this->renderable(function (NotFoundHttpException $exception, $request) {
            return $this->errorResponse(__('api.error.not_found'), Response::HTTP_NOT_FOUND);
        });
        
        $this->renderable(function (\Illuminate\Auth\AuthenticationException $exception, $request) {
            return $this->errorResponse(__('api.error.unauthorized'), 401);
        });

        $this->renderable(function (ThrottleRequestsException $exception, $request) {
            return $this->errorResponse(__('api.error.too_many_requests'), Response::HTTP_TOO_MANY_REQUESTS);
        });

        $this->renderable(function (\Illuminate\Validation\ValidationException $exception, $request) {
            return $this->validateResponse($exception->errors());
        });

        $this->renderable(function (\Exception $exception, $request) {
            return $this->errorResponse(__('api.error.internal_error'), Response::HTTP_INTERNAL_SERVER_ERROR, $exception->getMessage());
        });
    }
}
