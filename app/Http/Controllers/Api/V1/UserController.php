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
use App\Models\Donation;
use App\Models\MembershipAgreementFile;
use App\Models\Node;
use App\Models\NodeInfo;
use App\Models\OwnerNode;
use App\Models\Profile;
use App\Models\Shuftipro;
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
use App\Services\NodeHelper;
use App\Services\Test;
use App\Services\ChecksumValidator;
use App\Services\ShuftiproCheck as ServicesShuftiproCheck;

use Carbon\Carbon;
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

use Aws\S3\S3Client;

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

    public function getMemberCountInfo() {
        $data = [
            'total' => 0,
            'verified' => 0,
        ];

        $data['total'] = User::count();
        $data['verified'] = User::join('profile', 'profile.user_id', '=', 'users.id')
                            ->where('profile.status', 'approved')
                            ->whereNotNull('users.public_address_node')
                            ->get()
                            ->count();

        return $this->successResponse($data);
    }

    // Get Verified Members
    public function getVerifiedMembers() {
        $data = [];
        $limit = $request->limit ?? 50;
        $user = auth()->user();

        $data = User::select([
                    'users.id',
                    'users.pseudonym',
                    'users.public_address_node',
                    'users.node_status',
                    'profile.extra_status',
                ])
                ->join('profile', 'profile.user_id', '=', 'users.id')
                ->where('profile.status', 'approved')
                ->whereNotNull('users.public_address_node')
                ->paginate($limit);

        return $this->successResponse($data);
    }

    // Shuftipro Webhook
    public function updateShuftiproStatus() {
        $json = file_get_contents('php://input');

        if ($json) {
            $data = json_decode($json, true);

            if ($data && isset($data['reference'])) {
                $shuftiproCheck = new ServicesShuftiproCheck();

                $reference_id = $data['reference'];

                $record = Shuftipro::where('reference_id', $reference_id)->first();
                $recordTemp = ShuftiproTemp::where('reference_id', $reference_id)->first();

                if (!$recordTemp) {
                    return;
                }

                if ($record) {
                    if (isset($data['event']) && $data['event'] == 'request.deleted') {
                        // Reset Action
                        $user = User::find($record->user_id);

                        if ($user) {
                            $user_id = $user->id;
                            $profile = Profile::where('user_id', $user_id)->first();

                            if ($profile) {
                                $profile->status = null;
                                $profile->save();
                            }
                            
                            Shuftipro::where('user_id', $user->id)->delete();
                            ShuftiproTemp::where('user_id', $user->id)->delete();
                        }
                        return;
                    }
                    $shuftiproCheck->handleExisting($record);
                } else {
                    $events = [
                        'verification.accepted',
                        'verification.declined',
                    ];
                    if (isset($data['event']) && in_array($data['event'], $events)) {
                        $user = User::find($recordTemp->user_id);

                        if ($user) {
                            $user_id = $user->id;
                            $profile = Profile::where('user_id', $user_id)->first();

                            if ($profile) {
                                $profile->status = 'pending';
                                $profile->save();
                            }

                            $recordTemp->status = 'booked';
                            $recordTemp->save();

                            $shuftiproCheck->handle($recordTemp);
                        }
                    }
                }
            }
        }
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
        $user = auth()->user()->load(['profile', 'permissions', 'shuftipro', 'shuftiproTemp']);
        Helper::getAccountInfoStandard($user);
        $user->metric = Helper::getNodeInfo($user);
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
            // new filename hash
            $filenamehash = md5(Str::random(10) . '_' . (string)time());
            // Filename to store
            $fileNameToStore = $filenamehash . '.' . $extension;

            // S3 file upload
            $S3 = new S3Client([
                'version' => 'latest',
                'region' => getenv('AWS_DEFAULT_REGION'),
                'credentials' => [
                    'key' => getenv('AWS_ACCESS_KEY_ID'),
                    'secret' => getenv('AWS_SECRET_ACCESS_KEY'),
                ],
            ]);

            $s3result = $S3->putObject([
                'Bucket' => getenv('AWS_BUCKET'),
                'Key' => 'letters_of_motivation/'.$fileNameToStore,
                'SourceFile' => $request->file('file')
            ]);

            $ObjectURL = $s3result['ObjectURL'] ?? getenv('SITE_URL').'/not-found';
            $user->letter_file = $ObjectURL;
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

            $whitelist = [
                'http://casper.local',
                'http://casper.local/',
                'https://backend.caspermember.com',
                'https://backend.caspermember.com/',
                'https://stage.membersbackend.casper.network',
                'https://stage.membersbackend.casper.network/',
            ];

            if (in_array(env('APP_URL'), $whitelist)) {
                $request->enableTestMode();
            }

            $request->setTemplateId($template_id);
            $request->setSubject('Member Agreement');
            $request->setSigner('Member', $user->email, $user->first_name . ' ' . $user->last_name);
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
     * submit node address
     */
    public function submitPublicAddress(SubmitPublicAddressRequest $request)
    {
        $user = auth()->user();

        $address = strtolower($request->public_address);

        $public_address_temp = (new ChecksumValidator())->do($address);
        $public_address = strtolower($address);

        $correct_checksum = (int) (new ChecksumValidator($public_address_temp))->do();
        if (!$correct_checksum) {
            return $this->errorResponse(__('Please provide valid address'), Response::HTTP_BAD_REQUEST);
        }

        $tempUser = User::where('public_address_node', $public_address)->first();
        if ($tempUser) {
            return $this->errorResponse(__('The address is already used by other user'), Response::HTTP_BAD_REQUEST);
        }

        $user->update(['public_address_node' => $public_address]);

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
            $public_validator_key = strtolower($user->public_address_node);
            $file = $request->file;

            $name = $file->getClientOriginalName();
            $hexstring = $file->get();

            if ($hexstring && $name == 'signature') {
                $verified = $casperSigVerify->verify(
                    trim($hexstring),
                    $public_validator_key,
                    $message
                );
                // $verified = true;
                if ($verified) {
                    $filenamehash = md5(Str::random(10) . '_' . (string)time());

                    // S3 file upload
                    $S3 = new S3Client([
                        'version' => 'latest',
                        'region' => getenv('AWS_DEFAULT_REGION'),
                        'credentials' => [
                            'key' => getenv('AWS_ACCESS_KEY_ID'),
                            'secret' => getenv('AWS_SECRET_ACCESS_KEY'),
                        ],
                    ]);

                    $s3result = $S3->putObject([
                        'Bucket' => getenv('AWS_BUCKET'),
                        'Key' => 'signatures/'.$filenamehash,
                        'SourceFile' => $request->file('file')
                    ]);

                    // $ObjectURL = 'https://'.getenv('AWS_BUCKET').'.s3.amazonaws.com/signatures/'.$filenamehash;
                    $ObjectURL = $s3result['ObjectURL'] ?? getenv('SITE_URL').'/not-found';
                    $user->signed_file = $ObjectURL;
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
        $user->reset_kyc = 0;
        $user->save();
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

    // Delete Shuftipro Temp Status
    public function deleteShuftiproTemp(Request $request)
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
            $profile->status = null;
            $profile->save();
        }

        Shuftipro::where('user_id', $user_id)->delete();
        ShuftiproTemp::where('user_id', $user_id)->delete();
        
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

    // get vote list
    public function getVotes(Request $request)
    {
        $status = $request->status ?? 'active';
        $limit = $request->limit ?? 50;
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
        foreach ($ballot->files as $file) {
            $ballotFileView = BallotFileView::where('ballot_file_id', $file->id)->where('user_id', $user->id)->first();
            $file->is_viewed =  $ballotFileView  ? 1 : 0;
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
                'avatar' => 'sometimes|mimes:jpeg,jpg,png,gif,webp|max:100000',
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
            // new filename hash
            $filenamehash = md5(Str::random(10) . '_' . (string)time());
            // Filename to store
            $fileNameToStore = $filenamehash . '.' . $extension;

            // S3 file upload
            $S3 = new S3Client([
                'version' => 'latest',
                'region' => getenv('AWS_DEFAULT_REGION'),
                'credentials' => [
                    'key' => getenv('AWS_ACCESS_KEY_ID'),
                    'secret' => getenv('AWS_SECRET_ACCESS_KEY'),
                ],
            ]);

            $s3result = $S3->putObject([
                'Bucket' => getenv('AWS_BUCKET'),
                'Key' => 'client_uploads/' . $fileNameToStore,
                'SourceFile' => $request->file('avatar'),
            ]);

            // $ObjectURL = 'https://'.getenv('AWS_BUCKET').'.s3.amazonaws.com/client_uploads/'.$fileNameToStore;
            $user->avatar = $s3result['ObjectURL'] ?? getenv('SITE_URL') . '/not-found';
            $user->save();
            return $this->metaSuccess();

            /* old
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
            */
        } catch (\Exception $ex) {
            return $this->errorResponse(__('Failed upload avatar'), Response::HTTP_BAD_REQUEST, $ex->getMessage());
        }
    }

    public function getMembers(Request $request)
    {
        $search = $request->search;
        $limit = $request->limit ?? 50;

        $slide_value_uptime = $request->uptime ?? 0;
        $slide_value_update_responsiveness = $request->update_responsiveness ?? 0;
        $slide_value_delegotors = $request->delegators ?? 0;
        $slide_value_stake_amount = $request->stake_amount ?? 0;
        $slide_delegation_rate = $request->delegation_rate ?? 0;

        $max_uptime = Node::max('uptime');
        $max_uptime = $max_uptime * 100;
        
        $max_delegators = NodeInfo::max('delegators_count');
        if(!$max_delegators || $max_delegators < 1) $max_delegators = 1;
        
        $max_stake_amount = NodeInfo::max('total_staked_amount');
        if(!$max_stake_amount || $max_stake_amount < 1) $max_stake_amount = 1;

        $sort_key = $request->sort_key ?? 'created_at';
        
        $users = User::with(['metric', 'nodeInfo', 'profile'])
                    ->whereHas('nodeInfo')
                    ->where('role', 'member')
                    ->where(function ($query) use ($search) {
                        if ($search) {
                            $query->where('users.first_name', 'like', '%' . $search . '%')
                                ->orWhere('users.last_name', 'like', '%' . $search . '%');
                        }
                    })
                    ->get();

        foreach ($users as $user) {
            $latest = Node::where('node_address', strtolower($user->public_address_node))
                            ->whereNotnull('protocol_version')
                            ->orderBy('created_at', 'desc')
                            ->first();
            if (!$latest) {
                $latest = new Node();
            }

            $user->status = isset($user->profile) && isset($user->profile->status) ? $user->profile->status : '';

            $uptime_nodeInfo = $user->nodeInfo->uptime;
            $uptime_node = isset($latest->uptime) && $latest->uptime ? $latest->uptime * 100 : null;
            $uptime_metric = isset($user->metric) && isset($user->metric->uptime) ? $user->metric->uptime : null;

            $res_nodeInfo = $user->nodeInfo->update_responsiveness ?? null;
            $res_node = $latest->update_responsiveness ?? null;
            $res_metric = $metric->update_responsiveness ?? null;

            $uptime = $uptime_nodeInfo ? $uptime_nodeInfo : ($uptime_node ? $uptime_node : ($uptime_metric ? $uptime_metric : 1));
            $res = $res_nodeInfo ? $res_nodeInfo : ($res_node ? $res_node : ($res_metric ? $res_metric : 0));

            $delegation_rate = isset($user->nodeInfo->delegation_rate) && $user->nodeInfo->delegation_rate ? $user->nodeInfo->delegation_rate / 100 : 1;
            if ($delegation_rate > 1) {
                $delegation_rate = 1;
            }
            $delegators_count = isset($user->nodeInfo->delegators_count) && $user->nodeInfo->delegators_count ? $user->nodeInfo->delegators_count : 0;
            $total_staked_amount = isset($user->nodeInfo->total_staked_amount) && $user->nodeInfo->total_staked_amount ? $user->nodeInfo->total_staked_amount : 0;

            $uptime_score = (float) (($slide_value_uptime * $uptime) / 100);
            $delegation_rate_score = (float) (($slide_delegation_rate * (1 - $delegation_rate)) / 100);
            $delegators_count_score = (float) ($delegators_count / $max_delegators) * $slide_value_delegotors;
            $total_staked_amount_score = (float) ($total_staked_amount / $max_stake_amount) * $slide_value_stake_amount;
            $res_score = (float) (($slide_value_update_responsiveness * $res) / 100);

            $user->uptime = $uptime;
            $user->delegation_rate = $delegation_rate;
            $user->delegators_count = $delegators_count;
            $user->total_staked_amount = $total_staked_amount;
            $user->totalScore = $uptime_score + $delegation_rate_score + $delegators_count_score + $total_staked_amount_score + $res_score;
        }

        $users = $users->sortByDesc($sort_key)->values();
        $users = Helper::paginate($users, $limit, $request->page);
        return $this->successResponse($users);
    }

    public function getMembersOld(Request $request)
    {
        $search = $request->search;
        $limit = $request->limit ?? 50;
        $slide_value_uptime = $request->uptime ?? 0;
        $slide_value_update_responsiveness = $request->update_responsiveness ?? 0;
        $slide_value_delegotors = $request->delegators ?? 0;
        $slide_value_stake_amount = $request->stake_amount ?? 0;
        $slide_delegation_rate = $request->delegation_rate ?? 0;

        $max_uptime = Node::max('uptime');
        $max_uptime = $max_uptime * 100;
        $max_delegators = NodeInfo::max('delegators_count');
        if($max_delegators < 1) $max_delegators = 1;
        $max_stake_amount = NodeInfo::max('total_staked_amount');
        if($max_stake_amount < 1) $max_stake_amount = 1;

        $sort_key = $request->sort_key ?? '';
        $sort_direction = $request->sort_direction ?? '';
        if (!$sort_key) $sort_key = 'created_at';
        if (!$sort_direction) $sort_direction = 'desc';

        $users = User::with(['metric'])
                    ->where('role', 'member')
                    ->leftJoin('node_info', 'users.public_address_node', '=', 'node_info.node_address')
                    ->leftJoin('profile', 'users.id', '=', 'profile.user_id')
                    ->where(function ($query) use ($search) {
                        if ($search) {
                            $query->where('users.first_name', 'like', '%' . $search . '%')
                                ->orWhere('users.last_name', 'like', '%' . $search . '%');
                        }
                    })
                    ->select([
                        'users.id',
                        'users.created_at',
                        'users.first_name',
                        'users.last_name',
                        'users.kyc_verified_at',
                        'users.pseudonym',
                        'profile.status',
                        'node_info.uptime',
                        'node_info.delegation_rate',
                        'node_info.delegators_count',
                        'node_info.total_staked_amount',
                    ])
                    ->get();

        foreach ($users as $user) {
            if (!$user->metric && !$user->nodeInfo) {
                $user->totalScore = 0;
                continue;
            }

            $latest = Node::where('node_address', strtolower($user->public_address_node))
                            ->whereNotnull('protocol_version')
                            ->orderBy('created_at', 'desc')
                            ->first();
            if (!$latest) {
                $latest = new Node();
            }

            $delegation_rate = $user->delegation_rate ? $user->delegation_rate / 100 : 1;
            
            $latest_uptime_node = isset($latest->uptime) ? $latest->uptime * 100 : null;
            $latest_update_responsiveness_node = $latest->update_responsiveness ?? null;
            $metric = $user->metric;
            if (!$metric) {
                $metric = new Metric();
            }
            $latest_uptime_metric = $metric->uptime ? $metric->uptime : null;
            $latest_update_responsiveness_metric = $metric->update_responsiveness ? $metric->update_responsiveness : null;

            $latest_uptime = $latest_uptime_node ?? $latest_uptime_metric ?? 1;
            $latest_update_responsiveness = $latest_update_responsiveness_node ??  $latest_update_responsiveness_metric ?? 1;

            // $delegators_count = $user->delegators_count ? $user->nodeInfo->delegators_count : 0;
            $delegators_count = $user->delegators_count ? $user->delegators_count : 0;
            // $total_staked_amount = $user->total_staked_amount ? $user->nodeInfo->total_staked_amount : 0;
            $total_staked_amount = $user->total_staked_amount ? $user->total_staked_amount : 0;

            $uptime_score = ($slide_value_uptime * $latest_uptime) / 100;
            $update_responsiveness_score = ($slide_value_update_responsiveness * $latest_update_responsiveness) / 100;
            $dellegator_score = ($delegators_count / $max_delegators) * $slide_value_delegotors;
            $satke_amount_score = ($total_staked_amount / $max_stake_amount) * $slide_value_stake_amount;
            $delegation_rate_score = ($slide_delegation_rate * (1 - $delegation_rate)) / 100;
            $totalScore =  $uptime_score + $update_responsiveness_score + $dellegator_score + $satke_amount_score + $delegation_rate_score;

            $user->totalScore = $totalScore;
            $user->uptime = $user->uptime ? $user->uptime : $metric->uptime;
        }
        if ($sort_key == 'totalScore') {
            $users = $users->sortByDesc('totalScore')->values();
        } else {
            $users = $users->sortByDesc('created_at')->values();
        }
        $users = Helper::paginate($users, $limit, $request->page);
        return $this->successResponse($users);
    }

    public function getMemberDetail($id)
    {
        $user = User::where('id', $id)->first();
        Helper::getAccountInfoStandard($user);

        if (!$user || $user->role == 'admin') {
            return $this->errorResponse(__('api.error.not_found'), Response::HTTP_NOT_FOUND);
        }
        $user->metric = Helper::getNodeInfo($user);
        $response = $user->load(['profile']);

        unset($response->last_login_at);
        unset($response->last_login_ip_address);
        unset($response->profile->dob);
        unset($response->profile->address);
        unset($response->profile->city);
        unset($response->profile->zip);

        return $this->successResponse($response);
    }

    public function getCaKycHash($hash)
    {
        if(!ctype_xdigit($hash)) {
            return $this->errorResponse(__('api.error.not_found'), Response::HTTP_NOT_FOUND);
        }

        $selection = DB::select("
            SELECT a.casper_association_kyc_hash as proof_hash, b.reference_id, b.status, c.pseudonym
            FROM profile as a
            LEFT JOIN shuftipro AS b
            ON a.user_id = b.user_id
            LEFT JOIN users AS c
            ON b.user_id = c.id
            WHERE a.casper_association_kyc_hash = '$hash'
        ");
        $selection = $selection[0] ?? array();

        return $this->successResponse($selection);
    }

    public function getMyVotes(Request $request)
    {
        $limit = $request->limit ?? 50;
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
            $checkUsername = User::where('username', $request->username)
                                ->where('username', '!=', $user->username)
                                ->first();
            if ($checkUsername) {
                return $this->errorResponse(__('this username has already been taken'), Response::HTTP_BAD_REQUEST);
            }
            $user->username = $request->username;
        }
        
        if (isset($request->twoFA_login)) {
            $user->twoFA_login = $request->twoFA_login;
        }
        
        if ($request->email && $request->email != $user->email) {
            $emailParam = $request->email;

            $checkEmail = User::where(function ($query) use ($emailParam) {
                                $query->where('email', $emailParam)
                                        ->orWhere('new_email', $emailParam);
                            })
                            ->where('id', '!=', $user->id)
                            ->first();
            
            $currentEmail = $user->email;
            $newEmail = $request->email;
            if ($checkEmail) {
                return $this->errorResponse(__('this email has already been taken'), Response::HTTP_BAD_REQUEST);
            }
            $user->new_email = $newEmail;

            // Current Email 
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
        $verify = VerifyUser::where('email', $request->email)
                            ->where('type', VerifyUser::TYPE_CONFIRM_EMAIL)
                            ->where('code', $request->code)
                            ->first();
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
        $verify = VerifyUser::where('email', $user->email)
                            ->where('type', VerifyUser::TYPE_LOGIN_TWO_FA)
                            ->where('code', $request->code)
                            ->first();
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
        $limit = $request->limit ?? 50;
        $nodes =  User::select([
            'id as user_id',
            'public_address_node',
            'is_fail_node',
            'rank',
        ])
            ->where('banned', 0)
            ->whereNotNull('public_address_node')
            ->orderBy('rank', 'asc')
            ->paginate($limit);

        return $this->successResponse($nodes);
    }

    public function infoDashboard()
    {
        $user = auth()->user();
        $delegators = 0;
        $stake_amount = 0;
        $nodeInfo = NodeInfo::where('node_address', strtolower($user->public_address_node))->first();
        if ($nodeInfo) {
            $delegators = $nodeInfo->delegators_count;
            $stake_amount = $nodeInfo->total_staked_amount;
        }
        $totalPin = DiscussionPin::where('user_id', $user->id)->count();
        $response['totalNewDiscusstion'] = $user->new_threads;
        $response['totalPinDiscusstion'] = $totalPin;
        $response['rank'] = $user->rank;
        $response['delegators'] = $delegators;
        $response['stake_amount'] = $stake_amount;
        return $this->successResponse($response);
    }

    public function getEarningByNode($node)
    {
        $node = strtolower($node);
        $user = User::where('public_address_node', $node)->first();
        $nodeInfo = NodeInfo::where('node_address', $node)->first();
        $mbs = NodeInfo::max('mbs');
        if ($user && $nodeInfo) {
            return $this->successResponse([
                'daily_earning' => $nodeInfo->daily_earning,
                'total_earning' => $nodeInfo->total_earning,
                'mbs' => $mbs,
            ]);
        } else {
            return $this->successResponse([
                'mbs' => $mbs,
            ]);
        }
    }

    public function getChartEarningByNode($node)
    {
        $node = strtolower($node);
        $user = User::where('public_address_node', $node)->first();
        if ($user) {
            $nodeHelper = new NodeHelper();
            $result_day =  $nodeHelper->getValidatorRewards($node, 'day');
            $result_week =  $nodeHelper->getValidatorRewards($node, 'week');
            $result_month =  $nodeHelper->getValidatorRewards($node, 'month');
            $result_year =  $nodeHelper->getValidatorRewards($node, 'year');
            return $this->successResponse([
                'day' => $result_day,
                'week' => $result_week,
                'month' => $result_month,
                'year' => $result_year,
            ]);
        } else {
            return $this->successResponse(null);
        }
    }

    public function getMembershipFile()
    {
        $membershipAgreementFile = MembershipAgreementFile::first();
        return $this->successResponse($membershipAgreementFile);
    }

    public function membershipAgreement()
    {
        $user = auth()->user();
        $user->membership_agreement = 1;
        $user->save();
        return $this->metaSuccess();
    }

    public function checkResetKyc()
    {
        $user = auth()->user();
        $user->reset_kyc = 0;
        $user->save();
        return $this->metaSuccess();
    }

    public function getDonationSessionId(Request $request)
    {
        $sessionId = $request->get('sessionId');
        $flag = false;

        if ($sessionId) {
            \Stripe\Stripe::setApiKey(env('STRIPE_SEC_KEY'));
            $sessionData = \Stripe\Checkout\Session::retrieve($sessionId);

            if ($sessionData && isset($sessionData->id) && isset($sessionData->status) && $sessionData->status == 'complete') {
                $donation = Donation::where('checkout_session_id', $sessionId)->first();
                if ($donation) {
                    $donation->status = 'complete';
                    $donation->save();
                }
                $flag = true;
            }
        }

        return $this->successResponse([
            'success' => $flag,
        ]);
    }

    public function submitDonation(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'first_name' => 'required|regex:/^[A-Za-z. ]{1,255}$/',
            'last_name' => 'nullable|regex:/^[A-Za-z. ]{1,255}$/',
            'email' => 'required|email|max:256',
            'amount' => 'required',
            'message' => 'nullable|max:5000',
        ]);

        if ($validator->fails()) {
            return $this->validateResponse($validator->errors());
        }

        \Stripe\Stripe::setApiKey(env('STRIPE_SEC_KEY'));

        $productId = env('STRIPE_PRODUCTION_ID');
        $amount = (int) $request->amount * 100;

        $allPrices = \Stripe\Price::all();
        $priceId = null;

        if ($allPrices && isset($allPrices['data']) && count($allPrices['data'])) {
            foreach ($allPrices['data'] as $item) {
                if ((int) $item->unit_price == $amount) {
                    $priceId = $item->id;
                    break;
                }
            }
        }

        try {
            if (!$priceId) {
                $priceObject = \Stripe\Price::create([
                    'unit_amount' => $amount,
                    'currency' => 'usd',
                    'product' => $productId,
                ]);
                $priceId = $priceObject->id;
            }

            $url = $request->header('origin') ?? $request->root();
            $checkoutSession = \Stripe\Checkout\Session::create([
                'success_url' => $url . '/donate?session_id={CHECKOUT_SESSION_ID}',
                'cancel_url' => $url . '/donate',
                'customer_email' => $request->email,
                'mode' => 'payment',
                'line_items' => [[
                    'price' => $priceId,
                    'quantity' => 1,
                ]]
            ]);

            if ($checkoutSession && isset($checkoutSession->url) && isset($checkoutSession->id)) {
                $checkoutSessionId = $checkoutSession->id;

                $donation = Donation::where('checkout_session_id', $checkoutSessionId)->first();
                if (!$donation) {
                    $donation = new Donation();
                    $donation->first_name = $request->first_name;
                    $donation->last_name = $request->last_name;
                    $donation->email = $request->email;
                    $donation->amount = $request->amount;
                    $donation->message = $request->message;
                    $donation->checkout_session_id = $checkoutSessionId;
                    $donation->status = 'pending';
                    $donation->save();
                }
                
                return $this->successResponse([
                    'url' => $checkoutSession->url,
                ]);
            } else {
                return $this->errorResponse(__('Invalid payment request'), Response::HTTP_BAD_REQUEST);
            }
        } catch (\Exception $ex) {
            return $this->errorResponse(__('Invalid payment request'), Response::HTTP_BAD_REQUEST);
        }
    }
}
