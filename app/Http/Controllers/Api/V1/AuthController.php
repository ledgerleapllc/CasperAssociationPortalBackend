<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\LoginRequest;
use App\Http\Requests\Api\RegisterEntityRequest;
use App\Http\Requests\Api\RegisterIndividualRequest;
use App\Http\Requests\Api\ResetPasswordRequest;
use App\Http\Requests\Api\SendResetPasswordMailRequeslRequest;
use App\Mail\ResetPasswordMail;
use App\Mail\UserVerifyMail;
use App\Models\User;
use App\Models\VerifyUser;
use App\Repositories\UserRepository;
use App\Repositories\VerifyUserRepository;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

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
            if ($userVerify) {
                Mail::to($user->email)->send(new UserVerifyMail($code));
            }
            DB::commit();
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
                    'created_at' => now()
                ]
            );
            if ($userVerify) {
                Mail::to($user->email)->send(new UserVerifyMail($code));
            }
            DB::commit();
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
            $url = $request->header('origin') ?? $request->root();
            $resetUrl = $url . '/update-password?code=' . $code . '&email=' . urlencode( $request->email);
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
    public function createTokenFromUser($user, $info = [])
    {
        $token = $user->createToken(config('auth.secret_code'));
        $data = array_merge([
            'user_id' => $user->id,
            'is_verify' => $user->email_verified_at ? true : false,
            'type' => $user->type,
        ], $info);
        return $this->responseToken($token, $data);
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
