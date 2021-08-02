<?php

namespace App\Http\Middleware;

use App\Models\Permission;
use App\Traits\ApiResponser;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

class CheckPermission
{
    use ApiResponser;
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next, $permission)
    {
        $user = Auth::user();
        if ($user->role == 'admin') {
            return $next($request);
        }
        if ($user->role = 'sub-admin') {
            $permission = Permission::where('user_id', $user->id)->where('name', $permission)->first();
            if ($permission && $permission->is_permission == 1) {
                return $next($request);
            }
        }
        return $this->errorResponse(__('api.error.forbidden'), Response::HTTP_FORBIDDEN);
    }
}
