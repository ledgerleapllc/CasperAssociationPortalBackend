<?php

namespace App\Http\Controllers\Api\V1;

use App\Console\Helper;
use App\Http\Controllers\Controller;
use App\Http\EmailerHelper;
use App\Http\Requests\Api\AddOwnerNodeRequest;
use App\Http\Requests\Api\ChangeEmailRequest;
use App\Http\Requests\Api\ChangePasswordRequest;
use App\Http\Requests\Api\ResendEmailRequest;
use App\Http\Requests\Api\SubmitKYCRequest;
use App\Http\Requests\Api\SubmitPublicAddressRequest;
use App\Http\Requests\Api\VerifyFileCasperSignerRequest;
use App\Mail\AddNodeMail;
use App\Mail\LoginTwoFA;
use App\Mail\UserConfirmEmail;
use App\Mail\UserVerifyMail;
use App\Models\Ballot;
use App\Models\BallotFile;
use App\Models\BallotFileView;
use App\Models\LockRules;
use App\Models\Metric;
use App\Models\MonitoringCriteria;
use App\Models\DiscussionPin;
use App\Models\NodeInfo;
use App\Models\OwnerNode;
use App\Models\Profile;
use App\Models\ShuftiproTemp;
use App\Models\User;
use App\Models\VerifyUser;
use App\Models\Vote;
use App\Models\VoteResult;
use App\Repositories\OwnerNodeRepository;
use App\Repositories\ProfileRepository;
use App\Repositories\UserRepository;
use App\Repositories\VerifyUserRepository;
use App\Services\CasperSignature;
use App\Services\CasperSigVerify;
use App\Services\ShuftiproCheck;
use App\Services\Test;
use Exception;
use Illuminate\Validation\Rule;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class UserController extends Controller
{
    private $userRepo;
    private $verifyUserRepo;
    private $profileRepo;
    private $ownerNodeRepo;

    /* Create a new controller instance.
     *
     * @param UserRepository $userRepo userRepo
     *
     * @return void
     */
    public function __construct(
        UserRepository $userRepo,
        VerifyUserRepository $verifyUserRepo,
        ProfileRepository $profileRepo,
        OwnerNodeRepository $ownerNodeRepo
    ) {
        $this->userRepo = $userRepo;
        $this->verifyUserRepo = $verifyUserRepo;
        $this->profileRepo = $profileRepo;
        $this->ownerNodeRepo = $ownerNodeRepo;
    }

    /**
     * change email
     */
    public function changeEmail(ChangeEmailRequest $request)
    {
        try {
            DB::beginTransaction();
            $user = auth()->user();
            $user->update(['email' => $request->email, 'email_verified_at' => null]);
            $code = generateString(7);
            $userVerify = $this->verifyUserRepo->updateOrCreate(
                [
                    'email' => $request->email,
                    'type' => VerifyUser::TYPE_VERIFY_EMAIL,
                ],
                [
                    'code' => $code,
                    'created_at' => now()
                ]
            );
            if ($userVerify) {
                Mail::to($request->email)->send(new UserVerifyMail($code));
            }
            DB::commit();
            return $this->metaSuccess();
        } catch (\Exception $ex) {
            return $this->errorResponse(__('api.error.internal_error'), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
    /**
     * Change password
     */
    public function changePassword(ChangePasswordRequest $request)
    {
        $user = auth()->user();
        if (Hash::check($request->new_password, $user->password)) {
            return $this->errorResponse(__('api.error.not_same_current_password'), Response::HTTP_BAD_REQUEST);
        }
        $newPassword = bcrypt($request->new_password);
        $user->update(['password' => $newPassword]);
        return $this->metaSuccess();
    }

    /**
     * Get user profile
     */
    public function getProfile()
    {
        $user = auth()->user()->load(['profile', 'permissions']);
        return $this->successResponse($user);
    }

    /**
     * loggout user
     */
    public function logout()
    {
        auth()->user()->token()->revoke();
        return $this->metaSuccess();
    }

    /**
     * verify file casper singer
     */
    public function uploadLetter(Request $request)
    {
        try {
            // Validator
            $validator = Validator::make($request->all(), [
                'file' => 'required|mimes:pdf,docx,doc,txt,rtf|max:20000',
            ]);
            if ($validator->fails()) {
                return $this->validateResponse($validator->errors());
            }
            $user = auth()->user();
            $filenameWithExt = $request->file('file')->getClientOriginalName();
            //Get just filename
            $filename = pathinfo($filenameWithExt, PATHINFO_FILENAME);
            // Get just ext
            $extension = $request->file('file')->getClientOriginalExtension();
            // Filename to store
            $fileNameToStore = $filename . '_' . time() . '.' . $extension;
            // Upload Image
            $path = $request->file('file')->storeAs('users', $fileNameToStore);
            $user->letter_file = $path;
            $user->letter_rejected_at = null;
            $user->save();
            $emailerData = EmailerHelper::getEmailerData();
            EmailerHelper::triggerAdminEmail('User uploads a letter', $emailerData, $user);
            EmailerHelper::triggerUserEmail($user->email, 'Your letter of motivation is received', $emailerData, $user);
            return $this->metaSuccess();
        } catch (\Exception $ex) {
            return $this->errorResponse(__('Failed upload file'), Response::HTTP_BAD_REQUEST, $ex->getMessage());
        }
    }

    /**
     * Send Hellosign Request
     */
    public function sendHellosignRequest()
    {
        $user = auth()->user();
        if ($user) {
            $client_key = config('services.hellosign.api_key');
            $client_id = config('services.hellosign.client_id');
            $template_id = '80392797521f1adb88743f75ea04203a6504ef81';
            $client = new \HelloSign\Client($client_key);
            $request = new \HelloSign\TemplateSignatureRequest;

            $request->enableTestMode();
            $request->setTemplateId($template_id);
            $request->setSubject('User Agreement');
            $request->setSigner('User', $user->email, $user->first_name . ' ' . $user->last_name);
            $request->setCustomFieldValue('FullName', $user->first_name . ' ' . $user->last_name);
            $request->setCustomFieldValue('FullName2', $user->first_name . ' ' . $user->last_name);
            $request->setClientId($client_id);

            $initial = strtoupper(substr($user->first_name, 0, 1)) . strtoupper(substr($user->last_name, 0, 1));
            $request->setCustomFieldValue('Initial', $initial);

            $embedded_request = new \HelloSign\EmbeddedSignatureRequest($request, $client_id);
            $response = $client->createEmbeddedSignatureRequest($embedded_request);

            $signature_request_id = $response->getId();

            $signatures = $response->getSignatures();
            $signature_id = $signatures[0]->getId();

            $response = $client->getEmbeddedSignUrl($signature_id);
            $sign_url = $response->getSignUrl();

            $user->update(['signature_request_id' => $signature_request_id]);
            $emailerData = EmailerHelper::getEmailerData();
            if ($user->letter_verified_at && $user->signature_request_id && $user->node_verified_at) {
                EmailerHelper::triggerUserEmail($user->email, 'Congratulations', $emailerData, $user);
            }
            return $this->successResponse([
                'signature_request_id' => $signature_request_id,
                'url' => $sign_url,
            ]);
        }
        return $this->errorResponse(__('Hellosign request fail'), Response::HTTP_BAD_REQUEST);
    }
    /**
     * verify bypass
     */
    public function verifyBypass(Request $request)
    {
        $user = auth()->user();
        // Validator
        $validator = Validator::make($request->all(), [
            'type' => 'required'
        ]);
        if ($validator->fails()) {
            return $this->validateResponse($validator->errors());
        }
        if ($request->type == 'hellosign') {
            $user->signature_request_id = 'signature_'  . $user->id . '._id';
            $user->hellosign_form = 'hellosign_form_' . $user->id;
            // $user->letter_file = 'leteter_file.pdf';
            $user->save();
        }

        if ($request->type == 'verify-node') {
            $user->public_address_node = 'public_address_node'  . $user->id;
            $user->node_verified_at = now();
            $user->message_content = 'message_content';
            $user->signed_file = 'signture';
            $user->save();
        }

        if ($request->type == 'submit-kyc') {
            $user->kyc_verified_at = now();
            $user->save();
            if (!$user->profile) {
                $profile = new Profile();
                $profile->user_id = $user->id;
                $profile->first_name = $user->first_name;
                $profile->last_name = $user->last_name;
                $profile->dob = '1990-01-01';
                $profile->country_citizenship = 'United States';
                $profile->country_residence = 'United States';
                $profile->address = 'New York';
                $profile->city = 'New York';
                $profile->zip = '10025';
                $profile->type_owner_node = 1;
                $profile->type = $user->type;
                $profile->save();
            }
        }

        if ($request->type == 'letter-upload') {
            $user->letter_file = 'letter_file.pdf';
            $user->letter_verified_at = now();
            $user->save();
        }

        return $this->metaSuccess();
    }

    /**
     * submit node address
     */
    public function submitPublicAddress(SubmitPublicAddressRequest $request)
    {
        $user = auth()->user();
        $user->update(['public_address_node' => $request->public_address]);
        return $this->metaSuccess();
    }

    /**
     * submit node address
     */
    public function getMessageContent()
    {
        $user = auth()->user();
        $timestamp = date('d/m/Y');
        $message = "Please use the Casper Signature python tool to sign this message! " . $timestamp;
        $user->update(['message_content' => $message]);
        $filename = 'message.txt';
        return response()->streamDownload(function () use ($message) {
            echo $message;
        }, $filename);
    }

    /**
     * verify file casper singer
     */
    public function verifyFileCasperSigner(VerifyFileCasperSignerRequest $request)
    {
        try {
            $casperSigVerify = new CasperSigVerify();
            $user = auth()->user();
            $message = $user->message_content;
            $public_validator_key = $user->public_address_node;
            $file = $request->file;

            $name = $file->getClientOriginalName();
            $hexstring = $file->get();

            if (
                $hexstring &&
                $name == 'signature'
            ) {
                $verified = $casperSigVerify->verify(
                    trim($hexstring),
                    $public_validator_key,
                    $message
                );
                // $verified = true;
                if ($verified) {

                    $fullpath = 'sigfned_file/' . $user->id . '/signature';
                    Storage::disk('local')->put($fullpath,  trim($hexstring));
                    // $url = Storage::disk('local')->url($fullpath);
                    $user->signed_file = $fullpath;
                    $user->node_verified_at = now();
                    $user->save();
                    $emailerData = EmailerHelper::getEmailerData();
                    EmailerHelper::triggerUserEmail($user->email, 'Your Node is Verified', $emailerData, $user);

                    if ($user->letter_verified_at && $user->signature_request_id && $user->node_verified_at) {
                        EmailerHelper::triggerUserEmail($user->email, 'Congratulations', $emailerData, $user);
                    }
                    return $this->metaSuccess();
                } else {
                    return $this->errorResponse(__('Failed verification'), Response::HTTP_BAD_REQUEST);
                }
            }
            return $this->errorResponse(__('Failed verification'), Response::HTTP_BAD_REQUEST);
        } catch (\Exception $ex) {
            return $this->errorResponse(__('Failed verification'), Response::HTTP_BAD_REQUEST, $ex->getMessage());
        }
    }

    /**
     * submit KYC
     */
    public function functionSubmitKYC(SubmitKYCRequest $request)
    {
        $user = auth()->user();
        $data = $request->validated();
        $data['dob'] = \Carbon\Carbon::parse($request->dob)->format('Y-m-d');
        $user->update(['member_status' => User::STATUS_INCOMPLETE]);
        $this->profileRepo->updateOrCreate(
            [
                'user_id' => $user->id,
            ],
            $data
        );
        return $this->metaSuccess();
    }

    /**
     * verify owner node
     */
    public function verifyOwnerNode(Request $request)
    {
        $user = auth()->user();
        $this->profileRepo->updateConditions(
            ['type_owner_node' => $request->type],
            ['user_id' => $user->id]
        );
        return $this->metaSuccess();
    }

    /**
     * add owner node
     */
    public function addOwnerNode(AddOwnerNodeRequest $request)
    {
        try {
            $user = auth()->user();
            $data = $request->validated();
            $ownerNodes = [];
            $percents = 0;
            foreach ($data as $value) {
                $percents += $value['percent'];
                $value['user_id'] = $user->id;
                $value['created_at'] = now();
                array_push($ownerNodes, $value);
            }
            if ($percents >= 100) {
                return $this->errorResponse(__('Total percent must less 100'), Response::HTTP_BAD_REQUEST);
            }

            OwnerNode::where('user_id', $user->id)->delete();
            OwnerNode::insert($ownerNodes);
            $user->update(['kyc_verified_at' => now()]);

            $url = $request->header('origin') ?? $request->root();
            $resetUrl = $url . '/register-type';
            foreach ($ownerNodes as $node) {
                $email = $node['email'];
                $user = User::where('email', $email)->first();
                if (!$user) {
                    Mail::to($email)->send(new AddNodeMail($resetUrl));
                }
            }
            return $this->metaSuccess();
        } catch (\Exception $ex) {
            return $this->errorResponse(__('api.error.internal_error'), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * get Owner nodes
     */
    public function getOwnerNodes()
    {
        $user = auth()->user();
        $owners = OwnerNode::where('user_id', $user->id)->get();
        foreach ($owners as $owner) {
            $email = $owner->email;
            $userOwner = User::where('email', $email)->first();
            if ($userOwner) {
                $owner->kyc_verified_at = $userOwner->kyc_verified_at;
            } else {
                $owner->kyc_verified_at = null;
            }
        }
        $data = [];
        $data['kyc_verified_at'] = $user->kyc_verified_at;
        $data['owner_node'] = $owners;

        return $this->successResponse($data);
    }

    public function resendEmailOwnerNodes(ResendEmailRequest $request)
    {
        $user = auth()->user();
        $email = $request->email;
        $owners = OwnerNode::where('user_id', $user->id)->where('email', $email)->first();
        if ($owners) {
            $userOwner = User::where('email', $email)->first();
            if (!$userOwner) {
                $url = $request->header('origin') ?? $request->root();
                $resetUrl = $url . '/register-type';
                Mail::to($email)->send(new AddNodeMail($resetUrl));
            }
        } else {
            return $this->errorResponse('Email does not exist', Response::HTTP_BAD_REQUEST);
        }
        return $this->successResponse(null);
    }

    // Save Shuftipro Temp
    public function saveShuftiproTemp(Request $request)
    {
        $user = auth()->user();
        // Validator
        $validator = Validator::make($request->all(), [
            'reference_id' => 'required'
        ]);
        if ($validator->fails()) {
            return $this->validateResponse($validator->errors());
        }

        $user_id = $user->id;
        $reference_id = $request->reference_id;

        ShuftiproTemp::where('user_id', $user_id)->delete();

        $record = new ShuftiproTemp;
        $record->user_id = $user_id;
        $record->reference_id = $reference_id;
        $record->save();

        return $this->metaSuccess();
    }

    // Update Shuftipro Temp Status
    public function updateShuftiProTemp(Request $request)
    {
        $user = auth()->user();
        // Validator
        $validator = Validator::make($request->all(), [
            'reference_id' => 'required'
        ]);
        if ($validator->fails()) {
            return $this->validateResponse($validator->errors());
        }

        $user_id = $user->id;
        $reference_id = $request->reference_id;
        $profile = Profile::where('user_id', $user_id)->first();
        if ($profile) {
            $profile->status = 'pending';
            $profile->save();
        }
        $record = ShuftiproTemp::where('user_id', $user_id)
            ->where('reference_id', $reference_id)
            ->first();
        if ($record) {
            $record->status = 'booked';
            $record->save();
            $emailerData = EmailerHelper::getEmailerData();
            EmailerHelper::triggerAdminEmail('KYC or AML need review', $emailerData, $user);
            return $this->metaSuccess();
        }
        return $this->errorResponse('Fail submit AML', Response::HTTP_BAD_REQUEST);
    }

    // Updateq Temp Status
    public function updateTypeOwnerNode(Request $request)
    {
        $user = auth()->user();
        // Validator
        $validator = Validator::make($request->all(), [
            'type' => [
                'required',
                Rule::in([1, 2]),
            ],
        ]);
        if ($validator->fails()) {
            return $this->validateResponse($validator->errors());
        }
        if ($user->profile) {
            $user->profile->type_owner_node = $request->type;
            $user->profile->save();
            if ($request->type == 1) {
                $user->kyc_verified_at = now();
                $user->save();
            }
            return $this->metaSuccess();
        }
        return $this->errorResponse('Fail update type', Response::HTTP_BAD_REQUEST);
    }

    // get vote list
    public function getVotes(Request $request)
    {
        $status = $request->status ?? 'active';
        $limit = $request->limit ?? 15;
        $sort_key = $request->sort_key ?? '';
        $sort_direction = $request->sort_direction ?? '';
        if (!$sort_key) $sort_key = 'ballot.id';
        if (!$sort_direction) $sort_direction = 'desc';

        if ($status != 'active' && $status != 'finish') {
            return $this->errorResponse('Paramater invalid (status is active or finish)', Response::HTTP_BAD_REQUEST);
        }

        if ($status == 'active') {
            $query = Ballot::where('status', 'active');
        } else {
            $query = Ballot::where('status', '<>', 'active');
        }
        $data = $query->with('vote')->orderBy($sort_key, $sort_direction)->paginate($limit);

        return $this->successResponse($data);
    }

    // get vote detail
    public function getVoteDetail($id)
    {
        $user = auth()->user();
        $ballot = Ballot::with(['vote', 'voteResults.user', 'files'])->where('id', $id)->first();
        if (!$ballot) {
            return $this->errorResponse('Not found ballot', Response::HTTP_BAD_REQUEST);
        }
        foreach($ballot->files as $file) {
            $ballotFileView = BallotFileView::where('ballot_file_id', $file->id)->where('user_id', $user->id)->first();
            $file->is_viewed =  $ballotFileView  ? 1: 0;
        }
        $ballot->user_vote = VoteResult::where('user_id', $user->id)->where('ballot_id', $ballot->id)->first();
        return $this->successResponse($ballot);
    }

    // vote the ballot
    public function vote($id, Request $request)
    {
        $user = auth()->user();
        $vote = $request->vote;
        if (!$vote || ($vote != 'for' && $vote != 'against')) {
            return $this->errorResponse('Paramater invalid (vote is for or against)', Response::HTTP_BAD_REQUEST);
        }
        $ballot = Ballot::where('id', $id)->first();
        if (!$ballot) {
            return $this->errorResponse('Not found ballot', Response::HTTP_BAD_REQUEST);
        }
        $voteResult = VoteResult::where('user_id', $user->id)->where('ballot_id', $ballot->id)->first();
        if ($voteResult) {
            if ($vote == $voteResult->type) {
                return $this->metaSuccess();
            } else {
                $voteResult->type = $vote;
                $voteResult->updated_at = now();
                if ($vote == 'for') {
                    $ballot->vote->for_value = $ballot->vote->for_value + 1;
                    $ballot->vote->against_value = $ballot->vote->against_value - 1;
                } else {
                    $ballot->vote->for_value = $ballot->vote->for_value - 1;
                    $ballot->vote->against_value = $ballot->vote->against_value + 1;
                }
                $ballot->vote->updated_at = now();
                $ballot->vote->save();
                $voteResult->save();
            }
        } else {
            $voteResult = new VoteResult();
            $voteResult->user_id = $user->id;
            $voteResult->ballot_id = $ballot->id;
            $voteResult->vote_id = $ballot->vote->id;
            $voteResult->type = $vote;
            $voteResult->save();
            if ($vote == 'for') {
                $ballot->vote->for_value = $ballot->vote->for_value + 1;
            } else {
                $ballot->vote->against_value = $ballot->vote->against_value + 1;
            }
            $ballot->vote->result_count = $ballot->vote->result_count + 1;
            $ballot->vote->updated_at = now();
            $ballot->vote->save();
        }
        return $this->metaSuccess();
    }

    public function submitViewFileBallot(Request $request, $fileId)
    {
        $user = auth()->user();
        $ballotFile = BallotFile::where('id', $fileId)->first();
        if (!$ballotFile) {
            return $this->errorResponse('Not found ballot file', Response::HTTP_BAD_REQUEST);
        }
        $ballotFileView = BallotFileView::where('ballot_file_id', $ballotFile->id)->where('user_id', $user->id)->first();
        if ($ballotFileView) {
            return $this->metaSuccess();
        }
        $ballotFileView = new BallotFileView();
        $ballotFileView->ballot_file_id =  $ballotFile->id;
        $ballotFileView->ballot_id =  $ballotFile->ballot_id;
        $ballotFileView->user_id =  $user->id;
        $ballotFileView->save();
        return $this->metaSuccess();
    }
    /**
     * verify file casper singer
     */
    public function uploadAvatar(Request $request)
    {
        try {
            // Validator
            $validator = Validator::make($request->all(), [
                'avatar' => 'sometimes|mimes:jpeg,jpg,png,gif|max:100000',
            ]);
            if ($validator->fails()) {
                return $this->validateResponse($validator->errors());
            }
            $user = auth()->user();
            $filenameWithExt = $request->file('avatar')->getClientOriginalName();
            //Get just filename
            $filename = pathinfo($filenameWithExt, PATHINFO_FILENAME);
            // Get just ext
            $extension = $request->file('avatar')->getClientOriginalExtension();
            // Filename to store
            $fileNameToStore = $filename . '_' . time() . '.' . $extension;
            // Upload Image
            $path = $request->file('avatar')->storeAs('users/avatar', $fileNameToStore);
            $user->avatar = $path;
            $user->save();
            return $this->metaSuccess();
        } catch (\Exception $ex) {
            return $this->errorResponse(__('Failed upload avatar'), Response::HTTP_BAD_REQUEST, $ex->getMessage());
        }
    }

    public function getMembers(Request $request)
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

    public function getMemberDetail($id)
    {
        $user = User::where('id', $id)->first();
        if (!$user || $user->role == 'admin') {
            return $this->errorResponse(__('api.error.not_found'), Response::HTTP_NOT_FOUND);
        }
        $user->metric = Helper::getNodeInfo($user);
        $response = $user->load(['profile']);
        return $this->successResponse($response);
    }

    public function getMyVotes(Request $request)
    {
        $limit = $request->limit ?? 15;
        $user = auth()->user();
        $data = VoteResult::where('vote_result.user_id', $user->id)
            ->join('ballot', function ($query) use ($user) {
                $query->on('vote_result.ballot_id', '=', 'ballot.id');
            })
            ->join('vote', function ($query) use ($user) {
                $query->on('vote.ballot_id', '=', 'vote_result.ballot_id');
            })
            ->select([
                'vote.*',
                'ballot.*',
                'vote_result.created_at as date_placed',
                'vote_result.type as voteType',
            ])->orderBy('vote_result.created_at', 'DESC')->paginate($limit);
        return $this->successResponse($data);
    }

    public function checkCurrentPassword(Request $request)
    {
        $user = auth()->user();
        if (Hash::check($request->current_password, $user->password)) {
            return $this->metaSuccess();
        } else {
            return $this->errorResponse(__('Invalid password'), Response::HTTP_BAD_REQUEST);
        }
    }

    public function settingUser(Request $request)
    {
        $user = auth()->user();
        if ($request->new_password) {
            $user->password = bcrypt($request->new_password);
        }
        if ($request->username) {
            $checkUsername = User::where('username', $request->username)->where('username', '!=', $user->username)->first();
            if ($checkUsername) {
                return $this->errorResponse(__('this username has already been taken'), Response::HTTP_BAD_REQUEST);
            }
            $user->username = $request->username;
        }
        if (isset($request->twoFA_login)) {
            $user->twoFA_login = $request->twoFA_login;
        }
        if ($request->email) {
            $checkEmail = User::where('email', $request->email)->orWhere('new_email',  $request->email)->first();
            $currentEmail = $user->email;
            $newEmail = $request->email;
            if ($checkEmail) {
                return $this->errorResponse(__('this email has already been taken'), Response::HTTP_BAD_REQUEST);
            }
            $user->new_email = $newEmail;

            // curent email 
            $codeCurrentEmail = Str::random(6);
            $url = $request->header('origin') ?? $request->root();
            $urlCurrentEmail = $url . '/change-email/cancel-changes?code=' . $codeCurrentEmail . '&email=' . urlencode($currentEmail);
            $newMemberData = [
                'title' => 'Are you trying to update your email?',
                'content' => 'You recently requested to update your email address with the Casper Association Portal. If this is correct, click the link sent to your new email address to activate it. <br> If you did not initiate this update, your account could be compromised. Click the button to cancel the change',
                'url' => $urlCurrentEmail,
                'action' => 'cancel'
            ];
            Mail::to($currentEmail)->send(new UserConfirmEmail($newMemberData['title'], $newMemberData['content'], $newMemberData['url'], $newMemberData['action']));
            VerifyUser::where('email', $currentEmail)->where('type', VerifyUser::TYPE_CANCEL_EMAIL)->delete();
            $verify = new VerifyUser();
            $verify->code = $codeCurrentEmail;
            $verify->email = $currentEmail;
            $verify->type = VerifyUser::TYPE_CANCEL_EMAIL;
            $verify->created_at = now();
            $verify->save();

            // new email
            $codeNewEmail = Str::random(6);
            $urlNewEmail = $url . '/change-email/confirm?code=' . $codeNewEmail . '&email=' . urlencode($newEmail);
            $newMemberData = [
                'title' => 'You recently updated your email',
                'content' => 'You recently requested to update your email address with the Casper Association Portal. If this is correct, click the button below to confirm the change. <br> If you received this email in error, you can simply delete it',
                'url' => $urlNewEmail,
                'action' => 'confirm'
            ];
            Mail::to($newEmail)->send(new UserConfirmEmail($newMemberData['title'], $newMemberData['content'], $newMemberData['url'], $newMemberData['action']));
            VerifyUser::where('email', $newEmail)->where('type', VerifyUser::TYPE_CONFIRM_EMAIL)->delete();
            $verify = new VerifyUser();
            $verify->email = $newEmail;
            $verify->code = $codeNewEmail;
            $verify->type = VerifyUser::TYPE_CONFIRM_EMAIL;
            $verify->created_at = now();
            $verify->save();
        }
        $user->save();

        return $this->successResponse($user);
    }

    public function cancelChangeEmail(Request $request)
    {
        $verify = VerifyUser::where('email', $request->email)->where('type', VerifyUser::TYPE_CANCEL_EMAIL)
            ->where('code', $request->code)->first();
        if ($verify) {
            $user = User::where('email', $request->email)->first();
            if ($user) {
                $user->new_email = null;
                $user->save();
                $verify->delete();
                VerifyUser::where('email', $user->new_email)->where('type', VerifyUser::TYPE_CONFIRM_EMAIL)->delete();
                return $this->successResponse($user);
            }
            return $this->errorResponse(__('Fail cancel change email'), Response::HTTP_BAD_REQUEST);
        }
        return $this->errorResponse(__('Fail cancel change email'), Response::HTTP_BAD_REQUEST);
    }

    public function confirmChangeEmail(Request $request)
    {
        $verify = VerifyUser::where('email', $request->email)->where('type', VerifyUser::TYPE_CONFIRM_EMAIL)
            ->where('code', $request->code)->first();
        if ($verify) {
            $user = User::where('new_email', $request->email)->first();
            if ($user) {
                VerifyUser::where('email',  $user->email)->where('type', VerifyUser::TYPE_CANCEL_EMAIL)->delete();
                $user->new_email = null;
                $user->email = $request->email;
                $user->save();
                $verify->delete();
                return $this->successResponse($user);
            }
            return $this->errorResponse(__('Fail confirm change email'), Response::HTTP_BAD_REQUEST);
        }
        return $this->errorResponse(__('Fail confirm change email'), Response::HTTP_BAD_REQUEST);
    }

    public function checkLogin2FA(Request $request)
    {
        $user = auth()->user();
        $verify = VerifyUser::where('email', $user->email)->where('type', VerifyUser::TYPE_LOGIN_TWO_FA)
            ->where('code', $request->code)->first();
        if ($verify) {
            $verify->delete();
            $user->twoFA_login_active = 0;
            $user->save();
            return $this->metaSuccess();
        }
        return $this->errorResponse(__('Fail check twoFA code'), Response::HTTP_BAD_REQUEST);
    }

    public function resend2FA()
    {
        $user = auth()->user();
        if ($user->twoFA_login == 1) {
            VerifyUser::where('email', $user->email)->where('type', VerifyUser::TYPE_LOGIN_TWO_FA)->delete();
            $code = Str::random(6);
            $verify = new VerifyUser();
            $verify->email = $user->email;
            $verify->type = VerifyUser::TYPE_LOGIN_TWO_FA;
            $verify->code = $code;
            $verify->created_at = now();
            $verify->save();
            Mail::to($user)->send(new LoginTwoFA($code));
            return $this->metaSuccess();
        }
        return $this->errorResponse(__('Please enable 2Fa setting'), Response::HTTP_BAD_REQUEST);
    }

    public function getLockRules()
    {
        $user = auth()->user();

        $ruleKycNotVerify = LockRules::where('type', 'kyc_not_verify')->where('is_lock', 1)
            ->orderBy('id', 'ASC')->select(['id', 'screen'])->get();
        $ruleKycNotVerify1 = array_map(function ($object) {
            return $object->screen;
        }, $ruleKycNotVerify->all());
        $ruleStatusIsPoor = LockRules::where('type', 'status_is_poor')->where('is_lock', 1)
            ->orderBy('id', 'ASC')->select(['id', 'screen'])->get();
        $ruleStatusIsPoor1 = array_map(function ($object) {
            return $object->screen;
        }, $ruleStatusIsPoor->all());

        $data = [
            'kyc_not_verify' => $ruleKycNotVerify1,
            'status_is_poor' => $ruleStatusIsPoor1,
            'node_status' => $user->node_status
        ];
        return $this->successResponse($data);
    }

    public function getListNodes(Request $request)
    {
        $limit = $request->limit ?? 15;
        $nodes =  User::select([
            'id as user_id',
            'public_address_node',
            'is_fail_node'
        ])
            ->where('banned', 0)
            ->whereNotNull('public_address_node')
            ->orderBy('id', 'desc')
            ->paginate($limit);

        return $this->successResponse($nodes);
    }

    public function infoDashboard()
    {
        $user = auth()->user();
        $rank = 5; // dummy
        $delegators = 0;
        $stake_amount = 0;
        $nodeInfo = NodeInfo::where('node_address', $user->public_address_node)->first();
        if ($nodeInfo) {
            $delegators = $nodeInfo->delegators_count;
            $stake_amount = $nodeInfo->total_staked_amount;
        }
        $totalPin = DiscussionPin::where('user_id', $user->id)->count();
        $response['totalNewDiscusstion'] = $user->new_threads;
        $response['totalPinDiscusstion'] = $totalPin;
        $response['rank'] = $rank;
        $response['delegators'] = $delegators;
        $response['stake_amount'] = $stake_amount;
        return $this->successResponse($response);
    }
}
