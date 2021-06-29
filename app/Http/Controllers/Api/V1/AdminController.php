<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Mail\ResetKYC;
use App\Models\Ballot;
use App\Models\BallotFile;
use App\Models\OwnerNode;
use App\Models\Shuftipro;
use App\Models\ShuftiproTemp;
use App\Models\User;
use App\Models\Vote;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
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
        $response = $user->load(['profile', 'shuftipro', 'shuftiproTemp']);
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
        $search = $request->search ?? '';
        $users =  User::select(['users.created_at as registration_date', 'users.id', 'users.email', 'users.kyc_verified_at', 'users.node_verified_at'])
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
            ->where(function ($query) use ($search) {
                if ($search) {
                    $query->where('users.email', 'like', '%' . $search . '%');
                }
            })
            ->groupBy(['users.created_at', 'users.id', 'users.email', 'users.kyc_verified_at', 'users.node_verified_at'])
            ->paginate($limit);

        foreach ($users as $user) {
            $total = 0;
            $unopenedInvites = 0;
            $ownerNodes = OwnerNode::where('user_id', $user->id)->get();
            foreach ($ownerNodes as $node) {
                $total++;
                $user2 = User::select(['users.id', 'users.email', 'users.kyc_verified_at', 'users.node_verified_at'])
                    ->where('email', $node->email)->first();
                $node->user = $user2;
                if ($user2 && $user2->kyc_verified_at && $user2->node_verified_at) {
                } else {
                    $unopenedInvites++;
                }
            }

            $user->beneficial_owners = $total;
            $user->unopened_invites = $unopenedInvites;
            if ($unopenedInvites == 0) {
                $user->owner_kyc_status = 'Approved';
            } else {
                $user->owner_kyc_status = 'Not Approve';
            }
            if ($user->kyc_verified_at && $user->node_verified_at) {
                $user->kyc_status = 'Approved';
            } else {
                $user->kyc_status = 'Not Approved';
            }
        }

        return $this->successResponse($users);
    }

    public function submitBallot(Request $request)
    {
        try {
            DB::beginTransaction();
            $user = auth()->user();
            // Validator
            $validator = Validator::make($request->all(), [
                'title' => 'required',
                'description' => 'required',
                'time' => 'required',
                'time_unit' => 'required',
                'files' => 'array',
                'files.*' => 'file|max:100000|mimes:pdf,docx',
            ]);
            if ($validator->fails()) {
                return $this->validateResponse($validator->errors());
            }

            $time = $request->time;
            $timeUnit = $request->time_unit;
            $mins = 0;
            if ($timeUnit == 'mins') {
                $mins = $time;
            } else if ($timeUnit == 'hours') {
                $mins = $time * 60;
            } else if ($timeUnit == 'days') {
                $mins = $time * 60 * 24;
            }
            $start = Carbon::createFromFormat("Y-m-d H:i:s", Carbon::now('UTC'), "UTC");
            $now = Carbon::now('UTC');
            $timeEnd = $start->addMinutes($mins);
            $ballot = new Ballot();
            $ballot->user_id = $user->id;
            $ballot->title = $request->title;
            $ballot->description = $request->description;
            $ballot->time = $time;
            $ballot->time_unit = $timeUnit;
            $ballot->time_end = $timeEnd;
            $ballot->status = 'active';
            $ballot->created_at = $now;
            $ballot->save();
            $vote = new Vote();
            $vote->ballot_id = $ballot->id;
            $vote->save();

            $files = $request->file('files');
            foreach ($files as $file) {
                $name = $file->getClientOriginalName();
                $folder = 'ballot/' . $ballot->id;
                $path = $file->storeAs($folder, $name);
                $url = Storage::url($path);
                $ballotFile = new BallotFile();
                $ballotFile->ballot_id = $ballot->id;
                $ballotFile->name = $name;
                $ballotFile->path = $path;
                $ballotFile->url = $url;
                $ballotFile->save();
            }
            DB::commit();
            return $this->metaSuccess();
        } catch (\Exception $ex) {
            DB::rollBack();
            return $this->errorResponse('Submit ballot fail', Response::HTTP_BAD_REQUEST, $ex->getMessage());
        }
    }

    public function getBallots(Request $request)
    {
        $limit = $request->limit;
        $status = $request->status;
        if ($status == 'active') {
            $ballots = Ballot::with(['user', 'vote'])->where('ballot.status', 'active')->paginate($limit);
        } else if ($status && $status != 'active') {
            $ballots = Ballot::with(['user', 'vote'])->where('ballot.status', '!=', 'active')->paginate($limit);
        } else {
            $ballots = Ballot::with(['user', 'vote'])->paginate($limit);
        }
        return $this->successResponse($ballots);
    }

    public function getDetailBallot($id)
    {
        $ballot = Ballot::with(['user', 'vote', 'voteResults.user', 'files'])->where('id', $id)->first();
        if (!$ballot) {
            return $this->errorResponse('Not found ballot', Response::HTTP_BAD_REQUEST);
        }
        return $this->successResponse($ballot);
    }

    public function cancelBallot($id)
    {
        $ballot = Ballot::where('id', $id)->first();
        if ($ballot->status != 'active') {
            return $this->errorResponse('Cannot cancle ballot', Response::HTTP_BAD_REQUEST);
        }
        $ballot->status = 'cancelled';
        $ballot->save();
    }
}
