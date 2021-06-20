<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class AdminController extends Controller
{
    public function getUsers(Request $request)
    {
        $limit = $request->limit;
        $users = User::where('role', 'member')->orderBy('created_at', 'ASC')->paginate($limit);
        return $this->successResponse($users);
    }

    public function getUserDetail($id)
    {
        $user = User::where('id', $id)->first();
        if (!$user || $user->role == 'admin') {
            return $this->errorResponse(__('api.error.not_found'), Response::HTTP_NOT_FOUND);
        }
        return $this->successResponse($user);
    }

    public function infoDashboard()
    {
        $totalUser = User::where('role', 'member')->count();
        $toTalStake = 0;
        $totalDelagateer = 0;
        $response['totalUser'] = $totalUser;
        $response['toTalStake'] = $toTalStake;
        $response['totalDelagateer'] = $totalDelagateer;
        return $this->successResponse($response);
    }

    public function getKYC($id) {
        $user = User::where('id', $id)->first();
        if (!$user || $user->role == 'admin') {
            return $this->errorResponse(__('api.error.not_found'), Response::HTTP_NOT_FOUND);
        }
        $response = $user->load(['profile']);
        return $this->successResponse($response);
    }
}
