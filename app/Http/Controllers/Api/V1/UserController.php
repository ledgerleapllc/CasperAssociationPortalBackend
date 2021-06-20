<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\AddOwnerNodeRequest;
use App\Http\Requests\Api\ChangeEmailRequest;
use App\Http\Requests\Api\ChangePasswordRequest;
use App\Http\Requests\Api\SubmitKYCRequest;
use App\Http\Requests\Api\SubmitPublicAddressRequest;
use App\Http\Requests\Api\VerifyFileCasperSignerRequest;
use App\Mail\UserVerifyMail;
use App\Models\OwnerNode;
use App\Models\ShuftiproTemp;
use App\Models\User;
use App\Models\VerifyUser;
use App\Repositories\OwnerNodeRepository;
use App\Repositories\ProfileRepository;
use App\Repositories\UserRepository;
use App\Repositories\VerifyUserRepository;
use App\Services\CasperSignature;
use App\Services\CasperSigVerify;
use App\Services\ShuftiproCheck;
use App\Services\Test;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

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
        $user = auth()->user();
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
     * Send Hellosign Request
     */
    public function sendHellosignRequest()
    {
        $user = auth()->user();
        if ($user) {
            $client_key = env('HELLOSIGN_CLIENT_KEY', 'e0c85dde1ba2697d4236a6bc6c98ed2d3ca7e3b1cb375f35b286f2c0d07b22d8');
            $client_id = env('HELLOSIGN_CLINET_ID', '986d4bc5f54a0b9a96e1816d2204a4a0');
            $template_id = env('HELLOSIGN_TEMPLATE_ID', 'f4d05a88c5d27709b9ad6d7722921b185c95e1a9');
            $client = new \HelloSign\Client($client_key);
            $request = new \HelloSign\TemplateSignatureRequest;

            $request->enableTestMode();
            $request->setTemplateId($template_id);
            $request->setSubject('User Agreement');
            $request->setSigner('User', $user->email, $user->first_name . ' ' . $user->last_name);
            $request->setCustomFieldValue('FullName', $user->first_name . ' ' . $user->last_name);
            $request->setCustomFieldValue('FullName2', $user->first_name . ' ' . $user->last_name);

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
        $user->update(['public_address_node' => $request->public_address]);
        return $this->metaSuccess();
    }

    /**
     * submit node address
     */
    public function getMessageContent()
    {
        $user = auth()->user();
        $timestamp = date('m/d/Y');
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
                    $url = Storage::disk('local')->url($fullpath);
                    $user->signed_file = $url;
                    $user->node_verified_at = now();
                    $user->save();
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
                array_push($ownerNodes, $value);
            }
            if ($percents >= 100) {
                return $this->errorResponse(__('Total percent must less 100'), Response::HTTP_BAD_REQUEST);
            }

            OwnerNode::where('user_id', $user->id)->delete();
            OwnerNode::insert($ownerNodes);
            $user->update(['kyc_verified_at' => now()]);
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
        return $this->successResponse($owners);
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

        $record = ShuftiproTemp::where('user_id', $user_id)
            ->where('reference_id', $reference_id)
            ->first();
        if ($record) {
            $record->status = 'booked';
            $record->save();
            // check shuftipro
            $shuftiproCheck = new ShuftiproCheck();
            $shuftiproCheck->handle($record);
            return $this->metaSuccess();
        }

        return $this->errorResponse('Fail submit AML', Response::HTTP_BAD_REQUEST);
    }
}
