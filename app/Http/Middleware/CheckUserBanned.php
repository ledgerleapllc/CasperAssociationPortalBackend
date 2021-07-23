<?php

namespace App\Http\Middleware;

use App\Traits\ApiResponser;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

class CheckUserBanned
{
    use ApiResponser;

    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        $user = Auth::user();
        if ($user->banned == 1) {
            return $this->errorResponse(__('api.error.forbidden'), Response::HTTP_FORBIDDEN, 'User banned');
        }
        if($request->path() != 'api/v1/users/resend-2fa' && $request->path() != 'api/v1/users/check-login-2fa'){
            if ($user->twoFA_login == 1 && $user->twoFA_login_active == 1) {
                return $this->errorResponse(__('Please login again with 2Fa code.'), Response::HTTP_FORBIDDEN, '');
            }
        }
        
        return $next($request);
    }
}
