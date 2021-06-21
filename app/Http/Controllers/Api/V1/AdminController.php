<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Mail\ResetKYC;
use App\Models\Shuftipro;
use App\Models\ShuftiproTemp;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Mail;

class AdminController extends Controller
{
    public function getUsers(Request $request)
    {
        $limit = $request->limit ?? 15;
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

    public function getKYC($id)
    {
        $user = User::with(['shuftipro', 'profile'])->where('id', $id)->first();
        if (!$user || $user->role == 'admin') {
            return $this->errorResponse(__('api.error.not_found'), Response::HTTP_NOT_FOUND);
        }
        $response = $user->load(['profile', 'shuftipro']);
        return $this->successResponse($response);
    }

    // Approve KYC
    public function approveKYC($id, Request $request)
    {
        $admin = auth()->user();

        $user = User::with(['shuftipro', 'profile'])->where('id', $id)->first();
        if ($user && $user->profile && $user->shuftipro) {
            $user->kyc_verified_at = now();
            $user->save();

            $user->shuftipro->status = 'approved';
            $user->shuftipro->reviewed = 1;
            $user->shuftipro->save();

            $user->shuftipro->manual_approved_at = now();
            $user->shuftipro->manual_reviewer = $admin->email;
            $user->shuftipro->save();
            return $this->metaSuccess();
        }
        return $this->errorResponse('Fail approve KYC', Response::HTTP_BAD_REQUEST);
    }

    // Deny KYC
    public function denyKYC($id, Request $request)
    {
        $admin = auth()->user();
        $user = User::with(['shuftipro', 'profile'])->where('id', $id)->first();
        if ($user && $user->profile && $user->shuftipro) {
            $user->kyc_verified_at = null;
            $user->save();

            $user->shuftipro->status = 'denied';
            $user->shuftipro->reviewed = 1;
            $user->shuftipro->save();

            $user->shuftipro->manual_approved_at = now();
            $user->shuftipro->manual_reviewer = $admin->email;
            $user->shuftipro->save();

            return $this->metaSuccess();
        }
        return $this->errorResponse('Fail deny KYC', Response::HTTP_BAD_REQUEST);
    }

    // Reset KYC
    public function resetKYC($userId, Request $request)
    {
        $admin = auth()->user();

        $message = trim($request->get('message'));
        if (!$message) {
            return $this->errorResponse('please input message', Response::HTTP_BAD_REQUEST);
        }

        $user = User::with(['profile'])->where('id', $userId)->first();
        if ($user && $user->profile) {
            $user->kyc_verified_at = null;
            $user->save();

            Shuftipro::where('user_id', $user->id)->delete();
            ShuftiproTemp::where('user_id', $user->id)->delete();

            Mail::to($user->email)->send(new ResetKYC($message));
            return $this->metaSuccess();
        }

        return $this->errorResponse('Fail Reset KYC', Response::HTTP_BAD_REQUEST);
    }

    // gei intake
    public function getIntake(Request $request)
    {
        $limit = $request->limit ?? 15;
        $users =  User::with(['profile', 'ownerNodes'])->where(function ($q) {
            $q->where('node_verified_at', null)
                ->orWhere('kyc_verified_at', null);
        })->where('role', '<>', 'admin')
        ->paginate($limit);
        return $this->successResponse($users);
    }
}
