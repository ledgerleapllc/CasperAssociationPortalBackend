<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ChangeEmailRequest;
use App\Http\Requests\Api\ChangePasswordRequest;
use App\Mail\UserVerifyMail;
use App\Models\VerifyUser;
use App\Repositories\UserRepository;
use App\Repositories\VerifyUserRepository;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

class UserController extends Controller
{
    private $userRepo;

    /* Create a new controller instance.
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
}
