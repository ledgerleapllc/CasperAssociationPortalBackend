<?php

namespace App\Http\Controllers\Api\V1;

use  App\Models\NodeInfo;
use App\Http\Controllers\Controller;
use App\Http\EmailerHelper;
use App\Mail\AdminAlert;
use App\Mail\ResetKYC;
use App\Mail\ResetPasswordMail;
use App\Mail\InvitationMail;
use App\Models\Ballot;
use App\Models\BallotFile;
use App\Models\BallotFileView;
use App\Models\Discussion;
use App\Models\DiscussionComment;
use App\Models\DocumentFile;
use App\Models\EmailerAdmin;
use App\Models\EmailerTriggerAdmin;
use App\Models\EmailerTriggerUser;
use App\Models\IpHistory;
use App\Models\LockRules;
use App\Models\Metric;
use App\Models\MonitoringCriteria;
use App\Models\Node;
use App\Models\OwnerNode;
use App\Models\Perk;
use App\Models\Permission;
use App\Models\Profile;
use App\Models\Setting;
use App\Models\Shuftipro;
use App\Models\ShuftiproTemp;
use App\Models\TokenPrice;
use App\Models\User;
use App\Models\VerifyUser;
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
use Illuminate\Support\Str;

class AdminController extends Controller
{
    public function getUsers(Request $request)
    {
        $limit = $request->limit ?? 15;
        $sort_key = $request->sort_key ?? '';
        $sort_direction = $request->sort_direction ?? '';
        if (!$sort_key) $sort_key = 'created_at';
        if (!$sort_direction) $sort_direction = 'desc';
        $users = User::where('role', 'member')->with(['profile'])
            ->orderBy($sort_key, $sort_direction)->paginate($limit);
        foreach ($users as $user) {
            $status = 'Onboarding';
            if ($user->profile && $user->profile->status == 'pending') {
                $status = 'Not verified';
            } else if ($user->profile && $user->profile->status == 'approved') {
                $status = 'Verified';
            } else if (!$user->node_verified_at || !$user->letter_verified_at || !$user->signature_request_id) {
                $status = 'Onboarding';
            } else if ($user->node_verified_at && $user->letter_verified_at && $user->signature_request_id && !$user->profile) {
                $status = 'Not verified';
            }
            $user->membership_status = $status;
        }
        return $this->successResponse($users);
    }

    public function getUserDetail($id)
    {
        $user = User::where('id', $id)->first();
        if (!$user || $user->role == 'admin') {
            return $this->errorResponse(__('api.error.not_found'), Response::HTTP_NOT_FOUND);
        }
        $user = $user->load(['profile', 'shuftipro', 'shuftiproTemp']);
        $status = 'Onboarding';
        if ($user->profile && $user->profile->status == 'pending') {
            $status = 'Not verified';
        } else if ($user->profile && $user->profile->status == 'approved') {
            $status = 'Verified';
        } else if (!$user->node_verified_at || !$user->letter_verified_at || !$user->signature_request_id) {
            $status = 'Onboarding';
        } else if ($user->node_verified_at && $user->letter_verified_at && $user->signature_request_id && !$user->profile) {
            $status = 'Not verified';
        }
        $user->membership_status = $status;
        return $this->successResponse($user);
    }

    public function infoDashboard(Request $request)
    {
        $timeframe_perk = $request->timeframe_perk ?? 'last_7days';
        $timeframe_comments = $request->timeframe_comments ?? 'last_7days';
        $timeframe_discussions = $request->timeframe_discussions ?? 'last_7days';
        // last_24hs, last_7days, last_30days, last_year
        if ($timeframe_perk == 'last_24hs') {
            $timeframe_perk =  Carbon::now('UTC')->subHours(24);
        } else if ($timeframe_perk == 'last_30days') {
            $timeframe_perk =  Carbon::now('UTC')->subDays(30);
        } else if ($timeframe_perk == 'last_year') {
            $timeframe_perk =  Carbon::now('UTC')->subYear();
        } else {
            $timeframe_perk =  Carbon::now('UTC')->subDays(7);
        }

        if ($timeframe_comments == 'last_24hs') {
            $timeframe_comments =  Carbon::now('UTC')->subHours(24);
        } else if ($timeframe_comments == 'last_30days') {
            $timeframe_comments =  Carbon::now('UTC')->subDays(30);
        } else if ($timeframe_comments == 'last_year') {
            $timeframe_comments =  Carbon::now('UTC')->subYear();
        } else {
            $timeframe_comments =  Carbon::now('UTC')->subDays(7);
        }

        if ($timeframe_discussions == 'last_24hs') {
            $timeframe_discussions =  Carbon::now('UTC')->subHours(24);
        } else if ($timeframe_discussions == 'last_30days') {
            $timeframe_discussions =  Carbon::now('UTC')->subDays(30);
        } else if ($timeframe_discussions == 'last_year') {
            $timeframe_discussions =  Carbon::now('UTC')->subYear();
        } else {
            $timeframe_discussions =  Carbon::now('UTC')->subDays(7);
        }

        $totalUser = User::where('role', 'member')->count();
        $toTalStake = NodeInfo::sum('total_staked_amount');
        $totalDelagateer = NodeInfo::sum('delegators_count');;
        $totalNewUserReady =  User::where('banned', 0)
            ->where('role', 'member')
            ->where(function ($q) {
                $q->where('users.node_verified_at', null)
                    ->orWhere('users.letter_verified_at', null)
                    ->orWhere('users.signature_request_id', null);
            })->count();

        $totalUserVerification = User::where('users.role', 'member')->where('banned', 0)
            ->join('profile', function ($query) {
                $query->on('profile.user_id', '=', 'users.id')
                    ->where('profile.status', 'pending');
            })
            ->join('shuftipro', 'shuftipro.user_id', '=', 'users.id')
            ->count();
        $totalFailNode = User::where('banned', 0)->whereNotNull('public_address_node')->where('is_fail_node', 1)->count();

        $totalPerksActive = Perk::where('status', 'active')->where('created_at', '>=', $timeframe_perk)->count();
        $totalNewComments = DiscussionComment::where('created_at', '>=', $timeframe_comments)->count();
        $totalNewDiscussions = Discussion::where('created_at', '>=', $timeframe_discussions)->count();
        $metric = Metric::select(DB::raw('avg(uptime) avg_uptime, avg(block_height_average) avg_block_height_average, avg(update_responsiveness) avg_update_responsiveness'))->first();

        $blocks_hight_nodes = NodeInfo::whereNotNull('block_height_average')->pluck('block_height_average');
        $blocks_hight_metrics = Metric::whereNotNull('block_height_average')->pluck('block_height_average');
        $total_blocks_hight_metrics = 0;
        $base_block = 10;
        foreach($blocks_hight_metrics as $value) {
            $avg = ($base_block - $value) * 10;
            if($avg > 0) {
                $total_blocks_hight_metrics += $avg;
            }
        }

        $responsiveness_nodes = NodeInfo::whereNotNull('update_responsiveness')->pluck('update_responsiveness');
        $responsiveness_metrics = Metric::whereNotNull('update_responsiveness')->pluck('update_responsiveness');

        $response['totalUser'] = $totalUser;
        $response['totalStake'] = $toTalStake;
        $response['totalDelegators'] = $totalDelagateer;
        $response['totalNewUserReady'] = $totalNewUserReady;
        $response['totalUserVerification'] = $totalUserVerification;
        $response['totalFailNode'] = $totalFailNode;
        $response['totalPerksActive'] = $totalPerksActive;
        $response['totalNewComments'] = $totalNewComments;
        $response['totalNewDiscussions'] = $totalNewDiscussions;
        $response['avgUptime'] = $metric->avg_uptime;
        $response['avgBlockHeightAverage'] =($blocks_hight_nodes->sum() + $total_blocks_hight_metrics) / (count($blocks_hight_nodes) + count($blocks_hight_metrics));
        $response['avgUpdateResponsiveness'] =( $responsiveness_nodes->sum() + $responsiveness_metrics->sum()) / (count($responsiveness_nodes) + count($responsiveness_metrics) );
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
                'time_unit' => 'required|in:minutes,hours,days',
                'files' => 'array',
                'files.*' => 'file|max:100000|mimes:pdf,docx,doc,txt,rtf'
            ]);
            if ($validator->fails()) {
                return $this->validateResponse($validator->errors());
            }

            $time = $request->time;
            $timeUnit = $request->time_unit;
            $mins = 0;
            if ($timeUnit == 'minutes') {
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
        if (!$ballot || $ballot->status != 'active') {
            return $this->errorResponse('Cannot cancle ballot', Response::HTTP_BAD_REQUEST);
        }
        $ballot->time_end = now();
        $ballot->status = 'cancelled';
        $ballot->save();
        return $this->metaSuccess();
    }

    public function getBallotVotes($id, Request $request)
    {
        $limit = $request->limit ?? 15;
        $data = VoteResult::where('ballot_id', '=', $id)->with('user')->orderBy('created_at', 'ASC')->paginate($limit);

        return $this->successResponse($data);
    }

    public function getViewFileBallot(Request $request, $fileId)
    {
        $limit = $request->limit ?? 15;
        $data = BallotFileView::where('ballot_file_id', '=',  $fileId)->with('user')->orderBy('created_at', 'ASC')->paginate($limit);
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
        $limit = $request->limit ?? 10;
        $admins = User::with(['permissions'])->where(['role' => 'sub-admin'])
            ->orderBy('created_at', 'DESC')
            ->paginate($limit);

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

        $isExist = User::where(['email' => $request->email])->count() > 0;
        if ($isExist) {
            return $this->errorResponse('This email has already been used to invite another admin.', Response::HTTP_BAD_REQUEST);
        }

        $code = Str::random(6);
        $url = $request->header('origin') ?? $request->root();
        $inviteUrl = $url . '/register-sub-admin?code=' . $code . '&email=' . urlencode($request->email);
        VerifyUser::where('email', $request->email)->where('type', VerifyUser::TYPE_INVITE_ADMIN)->delete();
        $verify = new VerifyUser();
        $verify->email = $request->email;
        $verify->type = VerifyUser::TYPE_INVITE_ADMIN;
        $verify->code = $code;
        $verify->created_at = now();
        $verify->save();
        $admin = User::create([
            'first_name' => 'faker',
            'last_name' => 'faker',
            'email' => $request->email,
            'password' => '',
            'type' => '',
            'member_status' => 'invited',
            'role' => 'sub-admin'
        ]);

        $data = [
            ['name' => 'intake', 'is_permission' => 0, 'user_id' => $admin->id],
            ['name' => 'users', 'is_permission' => 0, 'user_id' => $admin->id],
            ['name' => 'ballots', 'is_permission' => 0, 'user_id' => $admin->id],
            ['name' => 'perks', 'is_permission' => 0, 'user_id' => $admin->id],
        ];

        Permission::insert($data);
        Mail::to($request->email)->send(new InvitationMail($inviteUrl));

        return $this->successResponse($admin);
    }

    public function changeSubAdminPermissions(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'intake' => 'nullable|in:0,1',
            'users' => 'nullable|in:0,1',
            'ballots' => 'nullable|in:0,1',
            'perks' => 'nullable|in:0,1',
        ]);

        if ($validator->fails()) {
            return $this->validateResponse($validator->errors());
        }
        $admin = User::find($id);
        if ($admin == null || $admin->role != 'sub-admin') {
            return $this->errorResponse('There is no admin user with this email', Response::HTTP_BAD_REQUEST);
        }
        if (isset($request->intake)) {
            $permisstion = Permission::where('user_id', $id)->where('name', 'intake')->first();
            if ($permisstion) {
                $permisstion->is_permission = $request->intake;
                $permisstion->save();
            }
        }
        if (isset($request->users)) {
            $permisstion = Permission::where('user_id', $id)->where('name', 'users')->first();
            if ($permisstion) {
                $permisstion->is_permission = $request->users;
                $permisstion->save();
            }
        }

        if (isset($request->ballots)) {
            $permisstion = Permission::where('user_id', $id)->where('name', 'ballots')->first();
            if ($permisstion) {
                $permisstion->is_permission = $request->ballots;
                $permisstion->save();
            }
        }

        if (isset($request->perks)) {
            $permisstion = Permission::where('user_id', $id)->where('name', 'perks')->first();
            if ($permisstion) {
                $permisstion->is_permission = $request->perks;
                $permisstion->save();
            }
        }

        return $this->metaSuccess();
    }

    public function resendLink(Request $request, $id)
    {
        $admin = User::find($id);
        if ($admin == null || $admin->role != 'sub-admin')
            return $this->errorResponse('No admin to be send invite link', Response::HTTP_BAD_REQUEST);

        $code = Str::random(6);
        $url = $request->header('origin') ?? $request->root();
        $inviteUrl = $url . '/register-sub-admin?code=' . $code . '&email=' . urlencode($admin->email);
        VerifyUser::where('email', $admin->email)->where('type', VerifyUser::TYPE_INVITE_ADMIN)->delete();
        $verify = new VerifyUser();
        $verify->email = $admin->email;
        $verify->type = VerifyUser::TYPE_INVITE_ADMIN;
        $verify->code = $code;
        $verify->created_at = now();
        $verify->save();

        Mail::to($admin->email)->send(new InvitationMail($inviteUrl));

        return $this->metaSuccess();
    }

    public function resetSubAdminResetPassword(Request $request, $id)
    {
        $admin = User::find($id);
        if ($admin == null || $admin->role != 'sub-admin')
            return $this->errorResponse('No admin to be revoked', Response::HTTP_BAD_REQUEST);

        $code = Str::random(6);
        $url = $request->header('origin') ?? $request->root();
        $resetUrl = $url . '/update-password?code=' . $code . '&email=' . urlencode($admin->email);
        VerifyUser::where('email', $admin->email)->where('type', VerifyUser::TYPE_RESET_PASSWORD)->delete();
        $verify = new VerifyUser();
        $verify->email = $admin->email;
        $verify->type = VerifyUser::TYPE_RESET_PASSWORD;
        $verify->code = $code;
        $verify->created_at = now();
        $verify->save();

        Mail::to($admin->email)->send(new ResetPasswordMail($resetUrl));

        return $this->metaSuccess();
    }

    public function revokeSubAdmin(Request $request, $id)
    {
        $admin = User::find($id);
        if ($admin == null || $admin->role != 'sub-admin')
            return $this->errorResponse('No admin to be revoked', Response::HTTP_BAD_REQUEST);

        $admin->member_status = 'revoked';
        $admin->banned = 1;
        $admin->save();

        return $this->metaSuccess();
    }

    public function undoRevokeSubAdmin(Request $request, $id)
    {
        $admin = User::find($id);
        if ($admin == null || $admin->role != 'sub-admin')
            return $this->errorResponse('No admin to be revoked', Response::HTTP_BAD_REQUEST);
        if ($admin->password) {
            $admin->member_status = 'active';
        } else {
            $admin->member_status = 'invited';
        }
        $admin->banned = 0;
        $admin->save();

        return $this->successResponse($admin);
    }

    public function getIpHistories(Request $request, $id)
    {
        $admin = User::find($id);
        if ($admin == null || $admin->role != 'sub-admin') {
            return $this->errorResponse('Not found admin', Response::HTTP_BAD_REQUEST);
        }
        $limit = $request->limit ?? 10;
        $ipAddress = IpHistory::where(['user_id' => $admin->id])
            ->orderBy('created_at', 'DESC')
            ->paginate($limit);

        return $this->successResponse($ipAddress);
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
            EmailerHelper::triggerUserEmail($user->email, 'Your letter of motivation is APPROVED', $emailerData, $user);
            if ($user->letter_verified_at && $user->signature_request_id && $user->node_verified_at) {
                EmailerHelper::triggerUserEmail($user->email, 'Congratulations', $emailerData, $user);
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
            Mail::to($user->email)->send(new AdminAlert('You need to submit letter again', $message));
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
            // $user->profile->status = 'pending';
            // $user->profile->save();
            Profile::where('user_id', $user->id)->delete();
            Shuftipro::where('user_id', $user->id)->delete();
            ShuftiproTemp::where('user_id', $user->id)->delete();
            DocumentFile::where('user_id', $user->id)->delete();

            Mail::to($user->email)->send(new AdminAlert('You need to submit KYC again', $message));
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

            Mail::to($user->email)->send(new AdminAlert('You need to submit AML again', $message));
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
            $user->approve_at = now();
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

    public function activeUser($id)
    {
        $user = User::with(['profile'])->where('id', $id)
            ->where('users.role', 'member')->where('banned', 0)->first();
        if ($user && $user->profile) {
            $user->profile->status = 'approved';
            $user->profile->save();
            $user->approve_at = now();
            $user->save();
            return $this->metaSuccess();
        }
        return $this->errorResponse('Fail active document', Response::HTTP_BAD_REQUEST);
    }

    // Add Emailer Admin
    public function addEmailerAdmin(Request $request)
    {
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
    public function deleteEmailerAdmin($adminId, Request $request)
    {
        $user = Auth::user();
        EmailerAdmin::where('id', $adminId)->delete();
        return ['success' => true];

        return ['success' => false];
    }

    // Get Emailer Data
    public function getEmailerData(Request $request)
    {
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
    public function updateEmailerTriggerAdmin($recordId, Request $request)
    {
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
    public function updateEmailerTriggerUser($recordId, Request $request)
    {
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

    public function getMonitoringCriteria(Request $request)
    {
        $data = MonitoringCriteria::get();

        return $this->successResponse($data);
    }

    public function updateMonitoringCriteria($type, Request $request)
    {
        $record = MonitoringCriteria::where('type', $type)->first();

        if ($record) {
            $validator = Validator::make($request->all(), [
                'warning_level' => 'required|integer',
                'probation_start' => 'required',
                'frame_calculate_unit' => 'required|in:Weeks,Days,Hours',
                'frame_calculate_value' => 'required|integer',
                'given_to_correct_unit' => 'required|in:Weeks,Days,Hours',
                'given_to_correct_value' => 'required|integer',
                'system_check_unit' => 'required|in:Weeks,Days,Hours',
                'system_check_value' => 'required|integer',
            ]);

            if ($validator->fails()) {
                return $this->validateResponse($validator->errors());
            }

            $record->warning_level = $request->warning_level;
            $record->probation_start = $request->probation_start;
            $record->frame_calculate_unit = $request->frame_calculate_unit;
            $record->frame_calculate_value = $request->frame_calculate_value;
            $record->given_to_correct_unit = $request->given_to_correct_unit;
            $record->given_to_correct_value = $request->given_to_correct_value;
            $record->system_check_unit = $request->system_check_unit;
            $record->system_check_value = $request->system_check_value;

            $record->save();

            return ['success' => true];
        }

        return ['success' => false];
    }

    public function updateLockRules(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'is_lock' => 'required|boolean'
        ]);

        if ($validator->fails()) {
            return $this->validateResponse($validator->errors());
        }

        $rule = LockRules::where('id', $id)->first();

        $rule->is_lock = $request->is_lock;
        $rule->save();
        return ['success' => true];
    }

    public function getLockRules()
    {
        $ruleKycNotVerify = LockRules::where('type', 'kyc_not_verify')
            ->orderBy('id', 'ASC')->select(['id', 'screen', 'is_lock'])->get();
        $ruleStatusIsPoor = LockRules::where('type', 'status_is_poor')
            ->orderBy('id', 'ASC')->select(['id', 'screen', 'is_lock'])->get();

        $data = ['kyc_not_verify' => $ruleKycNotVerify, 'status_is_poor' => $ruleStatusIsPoor];
        return $this->successResponse($data);
    }

    public function getListNodes(Request $request)
    {
        $limit = $request->limit ?? 15;
        $node_failing  = $request->node_failing  ?? '';
        $nodes =  User::select([
            'id as user_id',
            'public_address_node',
            'is_fail_node'
        ])
            ->where('banned', 0)
            ->whereNotNull('public_address_node')
            ->where(function ($query) use ($node_failing) {
                if ($node_failing == 1) {
                    $query->where('is_fail_node', 1);
                }
            })
            ->orderBy('id', 'desc')
            ->paginate($limit);

        return $this->successResponse($nodes);
    }

    // Get GraphInfo
    public function getGraphInfo(Request $request)
    {
        $user = Auth::user();
        $graphData = [];

        $items = TokenPrice::orderBy('created_at', 'asc')->get();
        if ($items && count($items)) {
            foreach ($items as $item) {
                $name = Carbon::parse($item->created_at)->format("Y-m-d H:i");
                $graphData[] = [
                    'name' => $name,
                    'Price' => $item->price
                ];
            }
        }

        return $this->successResponse($graphData);
    }
}
