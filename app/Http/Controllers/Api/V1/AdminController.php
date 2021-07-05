<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Mail\ResetKYC;
use App\Models\OwnerNode;
use App\Models\Shuftipro;
use App\Models\ShuftiproTemp;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;

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
        $response = $user->load(['profile', 'shuftipro','shuftiproTemp']);
        return $this->successResponse($response);
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

    // get intake
    public function getIntakes(Request $request)
    {
        $limit = $request->limit ?? 15;
        $users =  User::select(['users.created_at as registration_date','users.id', 'users.email', 'users.kyc_verified_at', 'users.node_verified_at'])
        ->leftJoin('owner_node', function ($join) {
            $join->on('owner_node.user_id', '=', 'users.id');
        })
        ->leftJoin('users as u2', function ($join) {
            $join->on('owner_node.email', '=', 'u2.email');
        })
        ->where(function ($q) {
            $q->where('users.node_verified_at', null)
                ->orWhere('users.kyc_verified_at', null)
                ->orWhere('u2.node_verified_at', null)
                ->orWhere('u2.kyc_verified_at', null);
        })->where('users.role', '<>', 'admin')
        ->groupBy(['users.created_at','users.id', 'users.email', 'users.kyc_verified_at', 'users.node_verified_at'])
        ->paginate($limit);

        foreach ($users as $user) {
            $total = 0;
            $unopenedInvites = 0;
            $ownerNodes = OwnerNode::where('user_id', $user->id)->get();
            foreach ($ownerNodes as $node) { 
                $total ++;
                $user2 = User::select(['users.id', 'users.email', 'users.kyc_verified_at', 'users.node_verified_at'])
                    ->where('email', $node->email)->first();
                $node->user = $user2;
                if ($user2 && $user2->kyc_verified_at && $user2->node_verified_at) {
                    
                } else {
                    $unopenedInvites ++;
                }
            }

            $user->beneficial_owners = $total;
            $user->unopened_invites = $unopenedInvites;
            if ($unopenedInvites == 0) {
                $user->owner_kyc_status = 'Approved';
            } else {
                $user->owner_kyc_status = 'Not Approve';
            }
            if($user->kyc_verified_at && $user->node_verified_at) {
                $user->kyc_status = 'Approved';
            } else {
                $user->kyc_status = 'Not Approved';
            }
        }

        return $this->successResponse($users);
    }

    public function getSubAdmins(Request $request) {
        $admins = User::where(['role' => 'sub-admin'])->get();

        return $this->successResponse($admins);
    }

    public function inviteSubAdmin(Request $request) {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
        ]);
        if ($validator->fails()) {
            return $this->validateResponse($validator->errors());
        }

        $admin = User::create([
            'first_name' => 'faker',
            'last_name' => 'faker',
            'email' => $request->email,
            'password' => '',
            'type' => 'invited',
            'role' => 'sub-admin'
        ]);
        // $admin->invite_link = '/invite';

        return $this->successResponse(['invited_admin' => $admin]);
    }

    public function changeSubAdminPermissions(Request $request, $id) {
        $data['intake'] = $request->intake;
        $data['users'] = $request->users;
        $data['ballots'] = $request->ballots;
        $data['perks'] = $request->perks;

        $admin = User::find($id);
        if ($admin == null || $admin->role != 'sub-admin') 
            return $this->errorResponse('No admin to be send invite link', Response::HTTP_BAD_REQUEST);

        $admin->permissions = $data;
        $admin->save();

        return $this->metaSuccess();
    }

    public function resendLink(Request $request) {
        $admin = User::find($id);
        if ($admin == null || $admin->role != 'sub-admin') 
            return $this->errorResponse('No admin to be send invite link', Response::HTTP_BAD_REQUEST);
        
        // $admin->invite_link = '/invite';
        $admin->save();

        return $this->metaSuccess();
    }

    public function changeSubAdminResetPassword(Request $request) {
        $admin = User::find($id);
        if ($admin == null || $admin->role != 'sub-admin') 
            return $this->errorResponse('No admin to be revoked', Response::HTTP_BAD_REQUEST);
        
        // $admin->reset_link = '/reset-password';
        $admin->save();

        return $this->metaSuccess();
    }

    public function revokeSubAdmin(Request $request, $id) {
        $admin = User::find($id);
        if ($admin == null || $admin->role != 'sub-admin') 
            return $this->errorResponse('No admin to be revoked', Response::HTTP_BAD_REQUEST);
        
        $admin->type = 'revoked';
        $admin->save();

        return $this->successResponse(['revoked' => $admin]);
    }
}
