<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\UserController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::prefix('v1')->namespace('Api')->middleware([])->group(function() {
    Route::post('/auth/login', [AuthController::class, 'login'])->name('login');;
    Route::post('/auth/register-entity', [AuthController::class, 'registerEntity']);
    Route::post('/auth/register-individual', [AuthController::class, 'registerIndividual']);
    Route::post('/auth/send-reset-password', [AuthController::class, 'sendResetLinkEmail']);
    Route::post('auth/reset-password', [AuthController::class, 'resetPassword']);
    Route::middleware(['auth:api'])->group(function () {
        Route::post('/users/verify-email', [AuthController::class, 'verifyEmail']);
        Route::post('/users/resend-verify-email', [AuthController::class, 'resendVerifyEmail']);
        Route::post('/users/change-email', [UserController::class, 'changeEmail']);
        Route::post('/users/change-password', [UserController::class, 'changePassword']);
        Route::get('/users/profile', [UserController::class, 'getProfile']);
        Route::post('/users/logout', [UserController::class, 'logout']);
        Route::post('users/hellosign-request', [UserController::class, 'sendHellosignRequest']);
        Route::post('users/submit-public-address', [UserController::class, 'submitPublicAddress']);
        Route::post('users/verify-file-casper-signer', [UserController::class, 'verifyFileCasperSigner']);
        Route::post('users/submit-kyc', [UserController::class, 'functionSubmitKYC']);
        Route::post('users/verify-owner-node', [UserController::class, 'verifyOwnerNode']);
        Route::post('users/owner-node', [UserController::class, 'addOwnerNode']);
        Route::get('users/owner-node', [UserController::class, 'getOwnerNodes']);
        Route::get('users/message-content', [UserController::class, 'getMessageContent']);
    });
});
