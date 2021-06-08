<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\LoginRequest;
use App\Http\Requests\Api\RegisterEntityRequest;
use App\Http\Requests\Api\RegisterIndividualRequest;
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

    public function createTokenFromUser($user, $info = [])
    {
        $token = $user->createToken(config('auth.secret_code'));
        $data = array_merge([
            'user_id' => $user->id,
            'is_verify' => $user->email_verified_at ? true : false
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
