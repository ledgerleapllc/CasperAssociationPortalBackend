<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\PagePermission;

class BlockAccessController extends Controller
{
    public function updateBlockAccess(Request $request) {
        $params = $request->all();
        $userId = (int) data_get($params, 'userId', 0);
        $name = data_get($params, 'name');
        $blocked = (int) data_get($params, 'blocked', 0);
        if ($userId && $name) {
            $permission = PagePermission::where('user_id', $userId)->where('name', $name)->first();
            if (!$permission) $permission = new PagePermission;
            $permission->user_id = $userId;
            $permission->name = $name;
            $permission->is_permission = 1 - $blocked;
            $permission->save();
            return $this->successResponse($permission);
        }
        return $this->metaSuccess();
    }
}