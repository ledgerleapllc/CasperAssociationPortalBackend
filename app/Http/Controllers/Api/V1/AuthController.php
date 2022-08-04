<?php

namespace App\Http\Controllers\Api\V1;

use App\Console\Helper;

use App\Http\Controllers\Controller;
use App\Http\EmailerHelper;
use App\Http\Requests\Api\LoginRequest;
use App\Http\Requests\Api\RegisterEntityRequest;
use App\Http\Requests\Api\RegisterIndividualRequest;
use App\Http\Requests\Api\ResetPasswordRequest;
use App\Http\Requests\Api\SendResetPasswordMailRequeslRequest;

use App\Mail\LoginTwoFA;
use App\Mail\ResetPasswordMail;
use App\Mail\UserVerifyMail;

use App\Models\IpHistory;
use App\Models\User;
use App\Models\UserAddress;
use App\Models\VerifyUser;

use App\Repositories\UserRepository;
use App\Repositories\VerifyUserRepository;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

use Laravel\Passport\Token;

use App\Services\ChecksumValidator;
use App\Services\NodeHelper;

class AuthController extends Controller
{
    private $userRepo;
    private $verifyUserRepo;

    /**
     * Create a new controller instance.
     *
     * @param UserRepository $userRepo userRepo
     *
     * @return void
     */
    public function __construct(
        UserRepository $userRepo,
        VerifyUserRepository $verifyUserRepo
    ) {
        $this->userRepo = $userRepo;
        $this->verifyUserRepo = $verifyUserRepo;
    }

    public function testHash() {
        exit(Hash::make('ledgerleapllc'));
    }

    /**
     * Auth user function
     *
     * @param Request $request request
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function login(LoginRequest $request)
    {
        $user = $this->userRepo->first(['email' => $request->email]);
        if ($user && Hash::check($request->password, $user->password)) {
            if ($user->banned == 1) {
                return $this->errorResponse('User banned', Response::HTTP_BAD_REQUEST);
            }
            if ($user->twoFA_login) {
                $code = strtoupper(Str::random(6));
                $user->twoFA_login_active = 1;
                $user->save();
                VerifyUser::where('email', $user->email)->where('type', VerifyUser::TYPE_LOGIN_TWO_FA)->delete();
                $verify = new VerifyUser();
                $verify->email = $user->email;
                $verify->type = VerifyUser::TYPE_LOGIN_TWO_FA;
                $verify->code = $code;
                $verify->created_at = now();
                $verify->save();
                Mail::to($user)->send(new LoginTwoFA($code));
            }
            $user->last_login_at = now();
            $user->last_login_ip_address = request()->ip();
            $user->save();
            $ipHistory = new IpHistory();
            $ipHistory->user_id = $user->id;
            $ipHistory->ip_address =  request()->ip();
            $ipHistory->save();
            Helper::getAccountInfoStandard($user);
            return $this->createTokenFromUser($user);
        }

        return $this->errorResponse(__('api.error.login_not_found'), Response::HTTP_BAD_REQUEST);
    }

    /**
     * Register user function
     *
     * @param Request $request request
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function registerEntity(RegisterEntityRequest $request)
    {
        try {
            DB::beginTransaction();
            $data = $request->all();

            $validatorAddress = strtolower($data['validatorAddress'] ?? '');
            if (!$validatorAddress) {
                return $this->errorResponse(__('The validator ID is invalid'), Response::HTTP_BAD_REQUEST);
            } else {
                $public_address_temp = (new ChecksumValidator())->do($validatorAddress);
                if (!$public_address_temp) {
                    return $this->errorResponse(__('The validator ID is invalid'), Response::HTTP_BAD_REQUEST);
                }

                $correct_checksum = (int) (new ChecksumValidator($public_address_temp))->do();
                if (!$correct_checksum) {
                    return $this->errorResponse(__('The validator ID is invalid'), Response::HTTP_BAD_REQUEST);
                }

                // User Check
                $tempUser = User::where('public_address_node', $validatorAddress)->first();
                if ($tempUser) {
                    return $this->errorResponse(__('The validator ID you specified is already associated with an Association member'), Response::HTTP_BAD_REQUEST);
                }

                // User Address Check
                $tempUserAddress = UserAddress::where('public_address_node', $validatorAddress)->first();
                if ($tempUserAddress) {
                    return $this->errorResponse(__('The validator ID you specified is already associated with an Association member'), Response::HTTP_BAD_REQUEST);
                }

                $nodeHelper = new NodeHelper();
                $addresses = $nodeHelper->getValidAddresses();
                if (!in_array($validatorAddress, $addresses)) {
                    return $this->errorResponse(__('The validator ID specified could not be found in the Casper validator pool'), Response::HTTP_BAD_REQUEST);
                }
            }

            $data['password'] = bcrypt($request->password);
            $data['last_login_at'] = now();
            $data['type'] = User::TYPE_ENTITY;
            $user = $this->userRepo->create($data);
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
            Mail::to($user->email)->send(new UserVerifyMail($code));
            DB::commit();
            $user->pending_node = 1;
            $user->last_login_at = now();
            $user->last_login_ip_address = request()->ip();
            $user->public_address_node = $validatorAddress;
            $user->has_address = 1;
            $user->save();

            $userAddress = new UserAddress;
            $userAddress->user_id = $user->id;
            $userAddress->public_address_node = $validatorAddress;
            $userAddress->save();

            $ipHistory = new IpHistory();
            $ipHistory->user_id = $user->id;
            $ipHistory->ip_address =  request()->ip();
            $ipHistory->save();
            return $this->createTokenFromUser($user);
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Register user function
     *
     * @param Request $request request
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function registerIndividual(RegisterIndividualRequest $request)
    {
        try {
            DB::beginTransaction();
            $data = $request->all();
            
            $validatorAddress = strtolower($data['validatorAddress'] ?? '');
            if (!$validatorAddress) {
                return $this->errorResponse(__('The validator ID is invalid'), Response::HTTP_BAD_REQUEST);
            } else {
                $public_address_temp = (new ChecksumValidator())->do($validatorAddress);
                if (!$public_address_temp) {
                    return $this->errorResponse(__('The validator ID is invalid'), Response::HTTP_BAD_REQUEST);
                }

                $correct_checksum = (int) (new ChecksumValidator($public_address_temp))->do();
                if (!$correct_checksum) {
                    return $this->errorResponse(__('The validator ID is invalid'), Response::HTTP_BAD_REQUEST);
                }

                // User Check
                $tempUser = User::where('public_address_node', $validatorAddress)->first();
                if ($tempUser) {
                    return $this->errorResponse(__('The validator ID you specified is already associated with an Association member'), Response::HTTP_BAD_REQUEST);
                }

                // User Address Check
                $tempUserAddress = UserAddress::where('public_address_node', $validatorAddress)->first();
                if ($tempUserAddress) {
                    return $this->errorResponse(__('The validator ID you specified is already associated with an Association member'), Response::HTTP_BAD_REQUEST);
                }

                $nodeHelper = new NodeHelper();
                $addresses = $nodeHelper->getValidAddresses();
                if (!in_array($validatorAddress, $addresses)) {
                    return $this->errorResponse(__('The validator ID specified could not be found in the Casper validator pool'), Response::HTTP_BAD_REQUEST);
                }
            }

            $data['password'] = bcrypt($request->password);
            $data['last_login_at'] = now();
            $data['type'] = User::TYPE_INDIVIDUAL;
            $user = $this->userRepo->create($data);
            $code = generateString(7);
            $userVerify = $this->verifyUserRepo->updateOrCreate(
                [
                    'email' => $request->email,
                    'type' => VerifyUser::TYPE_VERIFY_EMAIL,
                ],
                [
                    'code' => $code,
                    'created_at' => now(),
                ]
            );
            Mail::to($user->email)->send(new UserVerifyMail($code));
            DB::commit();
            $user->pending_node = 1;
            $user->last_login_at = now();
            $user->last_login_ip_address = request()->ip();
            $user->public_address_node = $validatorAddress;
            $user->has_address = 1;
            $user->save();

            $userAddress = new UserAddress;
            $userAddress->user_id = $user->id;
            $userAddress->public_address_node = $validatorAddress;
            $userAddress->save();

            $ipHistory = new IpHistory();
            $ipHistory->user_id = $user->id;
            $ipHistory->ip_address =  request()->ip();
            $ipHistory->save();
            return $this->createTokenFromUser($user);
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * verify email
     *
     * @param Request $request request
     *
     * @return Json
     */
    public function verifyEmail(Request $request)
    {
        try {
            $user = auth()->user();
            $verifyUser = $this->verifyUserRepo->first(
                ['code' => $request->code, 'email' => $user->email]
            );
            if ($this->checCode($verifyUser)) {
                $user->update(['email_verified_at' => now()]);
                $verifyUser->delete();
                $emailerData = EmailerHelper::getEmailerData();
                EmailerHelper::triggerUserEmail($user->email, 'Welcome to the Casper', $emailerData, $user);
                return $this->metaSuccess();
            }
            return $this->errorResponse(__('api.error.code_not_found'), Response::HTTP_BAD_REQUEST);
        } catch (\Exception $ex) {
            return $this->errorResponse(__('api.error.internal_error'), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Send a reset link to the given user.
     *
     * @param Request $request request
     *
     * @return Json
     */
    public function sendResetLinkEmail(SendResetPasswordMailRequeslRequest $request)
    {
        try {
            $user = $this->userRepo->first(
                [
                    'email' => $request->email,
                ]
            );
            if (!$user) {
                return $this->errorResponse(__('api.error.email_not_found'), Response::HTTP_BAD_REQUEST);
            }
            $code = Str::random(60);
            // $url = $request->header('origin') ?? $request->root();
            $url = getenv('SITE_URL');
            $resetUrl = $url . '/update-password?code=' . $code . '&email=' . urlencode($request->email);
            $passwordReset = $this->verifyUserRepo->updateOrCreate(
                [
                    'email' => $user->email,
                    'type' => VerifyUser::TYPE_RESET_PASSWORD,
                ],
                [
                    'code' => $code,
                    'created_at' => now(),
                ]
            );
            if ($passwordReset) {
                Mail::to($request->email)->send(new ResetPasswordMail($resetUrl));
            }
            return $this->metaSuccess();
        } catch (\Exception $ex) {
            return $this->errorResponse(__('api.error.internal_error'), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Reset the given user's password.
     *
     * @param Request $request request
     *
     * @return Json
     */
    public function resetPassword(ResetPasswordRequest $request)
    {
        try {
            $passwordReset = $this->verifyUserRepo->first(
                [
                    'code' => $request->code,
                    'type' => VerifyUser::TYPE_RESET_PASSWORD,
                    'email' => $request->email
                ]
            );
            if ($this->checCode($passwordReset)) {
                DB::beginTransaction();
                $this->userRepo->updateConditions(['password' => bcrypt($request->password)], ['email' => $passwordReset->email]);
                $passwordReset->delete();
                DB::commit();
                return $this->metaSuccess();
            }
            DB::rollBack();
            return $this->errorResponse(__('api.error.code_not_found'), Response::HTTP_BAD_REQUEST);
        } catch (\Exception $ex) {
            DB::rollBack();
            return $this->errorResponse(__('api.error.internal_error'), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
    /**
     * Resend verify email
     *
     * @param Request $request request
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function resendVerifyEmail(Request $request)
    {
        try {
            $user = auth()->user();
            $code = generateString(7);
            $userVerify = $this->verifyUserRepo->updateOrCreate(
                [
                    'email' => $user->email,
                    'type' => VerifyUser::TYPE_VERIFY_EMAIL,
                ],
                [
                    'code' => $code,
                    'created_at' => now()
                ]
            );
            if ($userVerify) {
                Mail::to($user->email)->send(new UserVerifyMail($code));
            }
            return $this->metaSuccess();
        } catch (\Exception $e) {
            throw $e;
        }
    }

    public function registerSubAdmin(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'first_name' => 'required|regex:/^[A-Za-z. ]{1,255}$/',
            'last_name' => 'required|regex:/^[A-Za-z. ]{1,255}$/',
            'email' => 'required|email|max:256',
            'code' => 'required',
            'password' => 'required|min:8|max:80',
        ]);

        if ($validator->fails()) {
            return $this->validateResponse($validator->errors());
        }
        $user = User::where('email', $request->email)->where('member_status', 'invited')->where('role', 'sub-admin')->first();
        if (!$user) {
            return $this->errorResponse('There is no admin user with this email', Response::HTTP_BAD_REQUEST);
        }
        $verify = VerifyUser::where('email', $request->email)->where('type', VerifyUser::TYPE_INVITE_ADMIN)->where('code', $request->code)->first();
        if (!$verify) {
            return $this->errorResponse('Fail register sub-amdin', Response::HTTP_BAD_REQUEST);
        }
        $user->first_name = $request->first_name;
        $user->last_name = $request->last_name;
        $user->password = bcrypt($request->password);
        $user->last_login_at = now();
        $user->last_login_ip_address = request()->ip();
        $user->member_status = 'active';
        $user->save();
        $ipHistory = new IpHistory();
        $ipHistory->user_id = $user->id;
        $ipHistory->ip_address = request()->ip();
        $ipHistory->save();
        $verify->delete();
        return $this->createTokenFromUser($user);
    }

    public function createTokenFromUser($user, $info = [])
    {
        Token::where([
            'user_id' => $user->id
        ])->delete();
        $token = $user->createToken(config('auth.secret_code'));
        return $this->responseToken($token, $user->toArray());
    }

    /**
     * Check code.
     *
     * @param $verifyUser verifyUser
     *
     * @return string
     */
    private function checCode($verifyUser)
    {
        return ($verifyUser && $verifyUser->created_at >= now()->subMinutes(VerifyUser::TOKEN_LIFETIME));
    }
}
