<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\EmailerHelper;
use App\Mail\ResetKYC;
use App\Models\Ballot;
use App\Models\BallotFile;
use App\Models\DocumentFile;
use App\Models\EmailerAdmin;
use App\Models\EmailerTriggerAdmin;
use App\Models\EmailerTriggerUser;
use App\Models\OwnerNode;
use App\Models\Profile;
use App\Models\Setting;
use App\Models\Shuftipro;
use App\Models\ShuftiproTemp;
use App\Models\User;
use App\Models\Vote;
use App\Models\VoteResult;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class AdminController extends Controller
{
    public function getUsers(Request $request)
    {
        $limit = $request->limit ?? 15;
        $sort_key = $request->sort_key ?? '';
        $sort_direction = $request->sort_direction ?? '';
        if (!$sort_key) $sort_key = 'created_at';
        if (!$sort_direction) $sort_direction = 'desc';
        $users = User::where('role', 'member')->orderBy($sort_key, $sort_direction)
        ->orderBy($sort_key, $sort_direction)->paginate($limit);
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

    // get intake
    public function getIntakes(Request $request)
    {
        $limit = $request->limit ?? 15;
        $search = $request->search ?? '';
        $users =  User::select([
            'id', 'email', 'node_verified_at', 'letter_verified_at', 'signature_request_id', 'created_at',
            'first_name', 'last_name', 'letter_file', 'letter_rejected_at'
        ])
            ->where('banned', 0)
            ->where('role', 'member')
            ->where(function ($q) {
                $q->where('users.node_verified_at', null)
                    ->orWhere('users.letter_verified_at', null)
                    ->orWhere('users.signature_request_id', null);
            })
            ->where(function ($query) use ($search) {
                if ($search) {
                    $query->where('users.email', 'like', '%' . $search . '%');
                }
            })
            ->paginate($limit);

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
                'files.*' => 'file|max:100000|mimes:pdf,docx,doc,txt,rtf'
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
            if ($request->hasFile('files')) {
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
        $sort_key = $request->sort_key ?? '';
        $sort_direction = $request->sort_direction ?? '';
        if (!$sort_key) $sort_key = 'ballot.id';
        if (!$sort_direction) $sort_direction = 'desc';
        
        if ($status == 'active') {
            $ballots = Ballot::with(['user', 'vote'])->where('ballot.status', 'active')
            ->orderBy($sort_key, $sort_direction)->paginate($limit);
        } else if ($status && $status != 'active') {
            $ballots = Ballot::with(['user', 'vote'])->where('ballot.status', '!=', 'active')
            ->orderBy($sort_key, $sort_direction)
            ->paginate($limit);
        } else {
            $ballots = Ballot::with(['user', 'vote'])->orderBy($sort_key, $sort_direction)->paginate($limit);
        }
        return $this->successResponse($ballots);
    }

    public function getDetailBallot($id)
    {
        $ballot = Ballot::with(['user', 'vote', 'files'])->where('id', $id)->first();
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

    public function getBallotVotes($id, Request $request)
    {
        $limit = $request->limit ?? 15;
        $data = VoteResult::where('ballot_id', '=', $id)->with('user')->orderBy('created_at', 'ASC')->paginate($limit);

        return $this->successResponse($data);
    }

    // Get Global Settings
    public function getGlobalSettings()
    {
        $items = Setting::get();
        $settings = [];
        if ($items) {
            foreach ($items as $item) {
                $settings[$item->name] = $item->value;
            }
        }

        return $this->successResponse($settings);
    }

    // Update Global Settings
    public function updateGlobalSettings(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'quorum_rate_ballot' => 'required',
        ]);
        if ($validator->fails()) {
            return $this->validateResponse($validator->errors());
        }
        $items = [
            'quorum_rate_ballot' => $request->quorum_rate_ballot,
        ];
        foreach ($items as $name => $value) {
            $setting = Setting::where('name', $name)->first();
            if ($setting) {
                $setting->value = $value;
                $setting->save();
            } else {
                $setting = new Setting();
                $setting->value = $value;
                $setting->save();
            }
        }

        return $this->metaSuccess();
    }

    public function getSubAdmins(Request $request)
    {
        $admins = User::where(['role' => 'sub-admin'])->get();

        return $this->successResponse($admins);
    }

    public function inviteSubAdmin(Request $request)
    {
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

        return $this->successResponse(['invited_admin' => $admin]);
    }

    public function changeSubAdminPermissions(Request $request, $id)
    {
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

    public function resendLink(Request $request)
    {
        $admin = User::find($id);
        if ($admin == null || $admin->role != 'sub-admin')
            return $this->errorResponse('No admin to be send invite link', Response::HTTP_BAD_REQUEST);

        $admin->save();

        return $this->metaSuccess();
    }

    public function changeSubAdminResetPassword(Request $request, $id)
    {
        $admin = User::find($id);
        if ($admin == null || $admin->role != 'sub-admin')
            return $this->errorResponse('No admin to be revoked', Response::HTTP_BAD_REQUEST);

        $admin->save();

        return $this->metaSuccess();
    }

    public function revokeSubAdmin(Request $request, $id)
    {
        $admin = User::find($id);
        if ($admin == null || $admin->role != 'sub-admin')
            return $this->errorResponse('No admin to be revoked', Response::HTTP_BAD_REQUEST);

        $admin->type = 'revoked';
        $admin->save();

        return $this->successResponse(['revoked' => $admin]);
    }

    public function approveIntakeUser($id)
    {
        $admin = auth()->user();

        $user = User::where('id', $id)->where('banned', 0)->where('role', 'member')->first();
        if ($user && $user->letter_file) {
            $user->letter_verified_at = now();
            $user->kyc_verified_at = now();
            $user->save();
            $emailerData = EmailerHelper::getEmailerData();
            EmailerHelper::triggerUserEmail($user->email, 'Your letter of motivation is APPROVED',$emailerData, $user);
            if ($user->letter_verified_at && $user->signature_request_id && $user->node_verified_at) {
                EmailerHelper::triggerUserEmail($user->email, 'Congratulations',$emailerData, $user);
            }
            return $this->metaSuccess();
        }
        return $this->errorResponse('Fail approved User', Response::HTTP_BAD_REQUEST);
    }

    public function resetIntakeUser($id, Request $request)
    {
        $admin = auth()->user();

        $user = User::where('id', $id)->where('banned', 0)->where('role', 'member')->first();
        if ($user) {
            $user->letter_verified_at = null;
            $user->letter_file = null;
            $user->letter_rejected_at = now();
            $user->save();
            $message = trim($request->get('message'));
            if (!$message) {
                return $this->errorResponse('please input message', Response::HTTP_BAD_REQUEST);
            }
            Mail::to($user->email)->send(new ResetKYC($message));
            return $this->metaSuccess();
        }
        return $this->errorResponse('Fail reset User', Response::HTTP_BAD_REQUEST);
    }

    public function banUser($id)
    {
        $admin = auth()->user();

        $user = User::where('id', $id)->where('banned', 0)->first();
        if ($user) {
            $user->banned = 1;
            $user->save();
            return $this->metaSuccess();
        }
        return $this->errorResponse('Fail Ban User', Response::HTTP_BAD_REQUEST);
    }

    public function getVerificationUsers(Request $request)
    {
        $limit = $request->limit ?? 15;
        $users = User::where('users.role', 'member')->where('banned', 0)
            ->join('profile', function ($query) {
                $query->on('profile.user_id', '=', 'users.id')
                    ->where('profile.status', 'pending');
            })
            ->join('shuftipro', 'shuftipro.user_id', '=', 'users.id')
            ->select([
                'users.id as user_id',
                'users.created_at',
                'users.email',
                'profile.*',
                'shuftipro.status as kyc_status',
                'shuftipro.background_checks_result',
            ])->paginate($limit);
        return $this->successResponse($users);
    }

    // Reset KYC
    public function resetKYC($id, Request $request)
    {
        $admin = auth()->user();

        $message = trim($request->get('message'));
        if (!$message) {
            return $this->errorResponse('please input message', Response::HTTP_BAD_REQUEST);
        }

        $user = User::with(['profile'])->where('id', $id)->first();
        if ($user && $user->profile) {
            $user->profile->status = 'pending';
            $user->profile->save();
            Shuftipro::where('user_id', $user->id)->delete();
            ShuftiproTemp::where('user_id', $user->id)->delete();
            DocumentFile::where('user_id', $user->id)->delete();

            Mail::to($user->email)->send(new ResetKYC($message));
            return $this->metaSuccess();
        }

        return $this->errorResponse('Fail Reset KYC', Response::HTTP_BAD_REQUEST);
    }

    // Reset AML
    public function resetAML($id, Request $request)
    {
        $admin = auth()->user();

        $message = trim($request->get('message'));
        if (!$message) {
            return $this->errorResponse('please input message', Response::HTTP_BAD_REQUEST);
        }

        $user = User::with(['profile'])->where('id', $id)->first();
        if ($user && $user->profile) {
            Profile::where('user_id', $user->id)->delete();
            Shuftipro::where('user_id', $user->id)->delete();
            ShuftiproTemp::where('user_id', $user->id)->delete();
            DocumentFile::where('user_id', $user->id)->delete();

            Mail::to($user->email)->send(new ResetKYC($message));
            return $this->metaSuccess();
        }

        return $this->errorResponse('Fail Reset AML', Response::HTTP_BAD_REQUEST);
    }

    // Approve kyc 
    public function approveKYC($id, Request $request)
    {
        $admin = auth()->user();

        $user = User::with(['shuftipro', 'profile'])->where('id', $id)
            ->where('users.role', 'member')->where('banned', 0)->first();
        if ($user && $user->profile) {
            $user->profile->status = 'approved';
            $user->profile->save();
            if ($user->shuftipro) {
                $user->shuftipro->status = 'approved';
                $user->shuftipro->reviewed = 1;
                $user->shuftipro->background_checks_result = 1;
                $user->shuftipro->manual_approved_at = now();
                $user->shuftipro->manual_reviewer = $admin->email;
                $user->shuftipro->save();
            }
            $user->kyc_verified_at = now();
            $user->save();
            return $this->metaSuccess();
        }
        return $this->errorResponse('Fail approve KYC', Response::HTTP_BAD_REQUEST);
    }

    // Approve AML
    public function approveAML($id)
    {
        $admin = auth()->user();

        $user = User::with(['shuftipro', 'profile'])->where('id', $id)
            ->where('users.role', 'member')->where('banned', 0)->first();
        if ($user && $user->profile && $user->shuftipro) {
            $user->shuftipro->background_checks_result = 1;
            $user->shuftipro->save();
            if ($user->shuftipro->status = 'approved') {
                $user->profile->status = 'approved';
                $user->profile->save();
            }
            $user->kyc_verified_at = now();
            $user->save();
            return $this->metaSuccess();
        }
        return $this->errorResponse('Fail approve AML', Response::HTTP_BAD_REQUEST);
    }

    public function banAndDenyUser($id)
    {
        $user = User::with(['shuftipro', 'profile'])->where('id', $id)
            ->where('users.role', 'member')->where('banned', 0)->first();
        if ($user && $user->profileT) {
            $user->profile->status = 'denied';
            $user->profile->save();
            $user->banned = 1;
            $user->save();
            return $this->metaSuccess();
        }
        return $this->errorResponse('Fail deny and ban user', Response::HTTP_BAD_REQUEST);
    }

    public function getVerificationDetail($id)
    {
        $user = User::with(['shuftipro', 'profile', 'documentFiles'])
            ->leftJoin('shuftipro', 'shuftipro.user_id', '=', 'users.id')
            ->where('users.id', $id)
            ->select([
                'users.*',
                'shuftipro.status as kyc_status',
                'shuftipro.background_checks_result',
            ])
            ->where('users.role', 'member')->where('banned', 0)->first();
        if ($user) {
            return $this->successResponse($user);
        }
        return $this->errorResponse('Fail get verification user', Response::HTTP_BAD_REQUEST);
    }

    public function approveDocument($id)
    {
        $user = User::with(['profile'])->where('id', $id)
            ->where('users.role', 'member')->where('banned', 0)->first();
        if ($user && $user->profile) {
            $user->profile->document_verified_at = now();
            $user->profile->save();
            return $this->metaSuccess();
        }
        return $this->errorResponse('Fail approve document', Response::HTTP_BAD_REQUEST);
    }

    // Add Emailer Admin
	public function addEmailerAdmin(Request $request) {
		$user = Auth::user();

        $email = $request->get('email');
        if (!$email) {
            return [
                'success' => false,
                'message' => 'Invalid email address'
            ];
        }

        $record = EmailerAdmin::where('email', $email)->first();
        if ($record) {
            return [
                'success' => false,
                'message' => 'This emailer admin email address is already in use'
            ];
        }

        $record = new EmailerAdmin;
        $record->email = $email;
        $record->save();

        return ['success' => true];
	
    }
    
    	// Delete Emailer Admin
	public function deleteEmailerAdmin($adminId, Request $request) {
		$user = Auth::user();
        EmailerAdmin::where('id', $adminId)->delete();
        return ['success' => true];

		return ['success' => false];
    }
    
    	// Get Emailer Data
	public function getEmailerData(Request $request) {
		$user = Auth::user();
		$data = [];

        $admins = EmailerAdmin::where('id', '>', 0)->orderBy('email', 'asc')->get();
        $triggerAdmin = EmailerTriggerAdmin::where('id', '>', 0)->orderBy('id', 'asc')->get();
        $triggerUser = EmailerTriggerUser::where('id', '>', 0)->orderBy('id', 'asc')->get();
        $data = [
            'admins' => $admins,
            'triggerAdmin' => $triggerAdmin,
            'triggerUser' => $triggerUser,
        ];

		return [
			'success' => true,
			'data' => $data
		];
    }
    
    	// Update Emailer Trigger Admin
	public function updateEmailerTriggerAdmin($recordId, Request $request) {
		$user = Auth::user();
        $record = EmailerTriggerAdmin::find($recordId);

        if ($record) {
            $enabled = (int) $request->get('enabled');
            $record->enabled = $enabled;
            $record->save();

            return ['success' => true];
        }

		return ['success' => false];
	}

	// Update Emailer Trigger User
	public function updateEmailerTriggerUser($recordId, Request $request) {
		$user = Auth::user();
        $record = EmailerTriggerUser::find($recordId);

        if ($record) {
            $enabled = (int) $request->get('enabled');
            $content = $request->get('content');

            $record->enabled = $enabled;
            if ($content) $record->content = $content;

            $record->save();

            return ['success' => true];
        }

		return ['success' => false];
	}
}
