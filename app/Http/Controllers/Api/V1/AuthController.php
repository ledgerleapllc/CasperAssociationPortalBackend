<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\LoginRequest;
use App\Http\Requests\Api\RegisterRequest;
use App\Repositories\UserRepository;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    private $userRepo;

    /**
     * Create a new controller instance.
     *
     * @param UserRepository $userRepo userRepo
     *
     * @return void
     */
    public function __construct(
        UserRepository $userRepo,
    ) {
        $this->userRepo = $userRepo;
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
    public function register(RegisterRequest $request)
    {
        try {
            $data = $request->all();
            $data['password'] = bcrypt($request->password);
            $data['last_login_at'] = now();
            $user = $this->userRepo->create($data);
            return $this->createTokenFromUser($user);
        } catch (\Exception $e) {
            throw $e;
        }
    }

    public function createTokenFromUser($user, $info = [])
    {
        $token = $user->createToken(config('auth.secret_code'));
        $data = array_merge([
            'user_id' => $user->id,
            'is_verify' => $user->email_verify_at ? true : false
        ], $info);
        return $this->responseToken($token, $data);
    }
}
