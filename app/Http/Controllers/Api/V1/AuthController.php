<?php

namespace App\Http\Controllers\Api\V1;

use Carbon\Carbon;
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

use App\Jobs\TestJob;

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

    public function testJob() {
    	TestJob::dispatch();
    	return 'Test Job!';
    }
    
    public function devVerifyNode($address)
    {
        $query = DB::select("
            SELECT
            a.user_id, a.node_verified_at, a.signed_file, a.node_status,
            b.id, b.email, b.node_verified_at, b.signed_file,
            b.node_status, b.has_verified_address
            FROM user_addresses AS a
            JOIN users AS b
            ON a.user_id = b.id
            WHERE a.public_address_node = '$address'
        ");

        $query   = $query[0] ?? array();
        $user_id = $query->user_id ?? 0;

        if ($user_id) {
            $update = DB::table('user_addresses')
            ->where('public_address_node',    $address)
            ->update(
                array(
                    'node_verified_at' => '2022-03-18 19:26:51',
                    'signed_file'      => 'https://casper-assoc-portal-dev.s3.us-east-2.amazonaws.com/signatures/db49744f7535b218c20a48cb833da6a1',
                    'node_status'      => 'Online'
                )
            );

            $update = DB::table('users')
            ->where('id',    $user_id)
            ->update(
                array(
                    'node_verified_at'     => '2022-03-18 19:26:51',
                    'signed_file'          => 'https://casper-assoc-portal-dev.s3.us-east-2.amazonaws.com/signatures/db49744f7535b218c20a48cb833da6a1',
                    'node_status'          => 'Online',
                    'has_verified_address' => 1
                )
            );
        }

        return $this->metaSuccess();
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
                $verify->created_at = Carbon::now('UTC');
                $verify->save();
                Mail::to($user)->send(new LoginTwoFA($code));
            }
            $user->last_login_at = Carbon::now('UTC');
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

            $data['password'] = bcrypt($request->password);
            $data['last_login_at'] = Carbon::now('UTC');
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
                    'created_at' => Carbon::now('UTC')
                ]
            );
            Mail::to($user->email)->send(new UserVerifyMail($code));
            DB::commit();
            $user->pending_node = 1;
            $user->last_login_at = Carbon::now('UTC');
            $user->last_login_ip_address = request()->ip();
            $user->save();

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

            $data['password'] = bcrypt($request->password);
            $data['last_login_at'] = Carbon::now('UTC');
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
                    'created_at' => Carbon::now('UTC'),
                ]
            );
            Mail::to($user->email)->send(new UserVerifyMail($code));
            DB::commit();
            $user->pending_node = 1;
            $user->last_login_at = Carbon::now('UTC');
            $user->last_login_ip_address = request()->ip();
            $user->save();
            
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
                $user->update(['email_verified_at' => Carbon::now('UTC')]);
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
                    'created_at' => Carbon::now('UTC'),
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
                    'created_at' => Carbon::now('UTC')
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
        $user->last_login_at = Carbon::now('UTC');
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
        return ($verifyUser && $verifyUser->created_at >= Carbon::now('UTC')->subMinutes(VerifyUser::TOKEN_LIFETIME));
    }
}