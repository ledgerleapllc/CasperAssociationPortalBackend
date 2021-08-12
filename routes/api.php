<?php

use App\Http\Controllers\Api\V1\AdminController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\HelloSignController;
use App\Http\Controllers\Api\V1\UserController;
use App\Http\Controllers\Api\V1\DiscussionController;
use App\Http\Controllers\Api\V1\MetricController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\PerkController;
use App\Http\Controllers\Api\V1\VerificationController;
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

Route::namespace('Api')->middleware([])->group(function () {
    Route::post('hellosign', [HelloSignController::class, 'hellosignHook']);
});

Route::prefix('v1')->namespace('Api')->middleware([])->group(function () {
    Route::post('/auth/login', [AuthController::class, 'login'])->name('login');;
    Route::post('/auth/register-entity', [AuthController::class, 'registerEntity']);
    Route::post('/auth/register-individual', [AuthController::class, 'registerIndividual']);
    Route::post('/auth/register-sub-admin', [AuthController::class, 'registerSubAdmin']);
    Route::post('/auth/send-reset-password', [AuthController::class, 'sendResetLinkEmail']);
    Route::post('auth/reset-password', [AuthController::class, 'resetPassword']);
    Route::get('/members', [UserController::class, 'getMembers']);
    Route::get('/members/{id}', [UserController::class, 'getMemberDetail'])->where('id', '[0-9]+');
    Route::post('/users/cancel-change-email', [UserController::class, 'cancelChangeEmail']);
    Route::post('/users/confirm-change-email', [UserController::class, 'confirmChangeEmail']);
    Route::middleware(['auth:api'])->group(function () {
        Route::middleware(['user_banned'])->group(function () {
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
            Route::post('users/resend-invite-owner', [UserController::class, 'resendEmailOwnerNodes']);
            Route::get('users/message-content', [UserController::class, 'getMessageContent']);
            Route::post('users/shuftipro-temp',  [UserController::class, 'saveShuftiproTemp']);
            Route::put('users/shuftipro-temp', [UserController::class, 'updateShuftiproTemp']);
            Route::put('/users/type-owner-node',  [UserController::class, 'updateTypeOwnerNode']);
            Route::post('users/verify-bypass',  [UserController::class, 'verifyBypass']);
            Route::post('/users/upload-letter',  [UserController::class, 'uploadLetter']);
            Route::get('users/votes', [UserController::class, 'getVotes']);
            Route::get('users/my-votes', [UserController::class, 'getMyVotes']);
            Route::get('users/votes/{id}', [UserController::class, 'getVoteDetail']);
            Route::post('users/votes/{id}', [UserController::class, 'vote']);
            Route::post('/users/upload-avatar',  [UserController::class, 'uploadAvatar']);
            Route::post('/users/check-password',  [UserController::class, 'checkCurrentPassword']);
            Route::post('/users/settings',  [UserController::class, 'settingUser']);

            Route::get('/users/metrics',  [MetricController::class, 'getMetric']);
            Route::post('/users/check-login-2fa',  [UserController::class, 'checkLogin2FA']);
            Route::post('/users/resend-2fa',  [UserController::class, 'resend2FA']);
            Route::get('/users/notification',  [NotificationController::class, 'getNotificationUser']);
            Route::put('/users/notification/{id}/view',  [NotificationController::class, 'updateView'])->where('id', '[0-9]+');
            Route::put('/users/notification/{id}/dismiss',  [NotificationController::class, 'dismiss'])->where('id', '[0-9]+');
            Route::put('/users/notification/{id}/click-cta',  [NotificationController::class, 'clickCTA'])->where('id', '[0-9]+');
            // rules lock
            Route::get('/users/lock-rules',  [UserController::class, 'getLockRules']);
            Route::get('users/list-node', [UserController::class, 'getListNodes']);
            Route::get('users/dashboard', [UserController::class, 'infoDashboard']);
        });
        Route::prefix('admin')->middleware(['role_admin'])->group(function () {
            Route::get('/users', [AdminController::class, 'getUsers']);
            Route::get('/users/{id}', [AdminController::class, 'getUserDetail'])->where('id', '[0-9]+');
            Route::get('/dashboard', [AdminController::class, 'infoDashboard']);
            Route::get('/users/{id}/kyc', [AdminController::class, 'getKYC'])->where('id', '[0-9]+');
            Route::get('/list-node', [AdminController::class, 'getListNodes']);
            // intakes
            Route::middleware([])->group(function () {
                Route::get('/users/intakes', [AdminController::class, 'getIntakes']);
                Route::post('/users/intakes/{id}/approve', [AdminController::class, 'approveIntakeUser'])->where('id', '[0-9]+');
                Route::post('/users/intakes/{id}/reset', [AdminController::class, 'resetIntakeUser'])->where('id', '[0-9]+');
                Route::post('/users/{id}/ban', [AdminController::class, 'banUser'])->where('id', '[0-9]+');
            });
            
            // user
            Route::middleware([])->group(function () {
                Route::get('/users/verification', [AdminController::class, 'getVerificationUsers']);
                Route::get('/users/verification/{id}', [AdminController::class, 'getVerificationDetail'])->where('id', '[0-9]+');
                Route::post('/users/{id}/approve-kyc', [AdminController::class, 'approveKYC'])->where('id', '[0-9]+');
                Route::post('/users/{id}/reset-kyc', [AdminController::class, 'resetKYC'])->where('id', '[0-9]+');
                Route::post('/users/{id}/approve-aml', [AdminController::class, 'approveAML'])->where('id', '[0-9]+');
                Route::post('/users/{id}/reset-aml', [AdminController::class, 'resetAML'])->where('id', '[0-9]+');
                Route::post('/users/{id}/deny-ban', [AdminController::class, 'banAndDenyUser'])->where('id', '[0-9]+');
                Route::post('/users/{id}/approve-document', [AdminController::class, 'approveDocument'])->where('id', '[0-9]+');
                Route::post('/users/{id}/active', [AdminController::class, 'activeUser'])->where('id', '[0-9]+');
            });

            // ballots
            Route::middleware([])->group(function () {
                Route::post('/ballots', [AdminController::class, 'submitBallot']);
                Route::get('/ballots', [AdminController::class, 'getBallots']);
                Route::get('/ballots/{id}', [AdminController::class, 'getDetailBallot'])->where('id', '[0-9]+');
                Route::get('/ballots/{id}/votes', [AdminController::class, 'getBallotVotes'])->where('id', '[0-9]+');
                Route::post('/ballots/{id}/cancel', [AdminController::class, 'cancelBallot'])->where('id', '[0-9]+');
            });

             //perk
             Route::middleware([])->group(function () {
                Route::get('/perks',  [PerkController::class, 'getPerksAdmin']);
                Route::post('/perks/update/{id}',  [PerkController::class, 'updatePerk']);
                Route::get('/perks/{id}',  [PerkController::class, 'getPerkDetailAdmin']);
                Route::get('/perks/{id}/result',  [PerkController::class, 'getPerkResultAdmin']);
                Route::delete('/perks/{id}',  [PerkController::class, 'deletePerk']);
                Route::post('/perks',  [PerkController::class, 'createPerk']);
            });

            Route::get('/global-settings', [AdminController::class, 'getGlobalSettings']);
            Route::put('/global-settings', [AdminController::class, 'updateGlobalSettings']);

            Route::prefix('/teams')->group(function () {
                Route::get('/', [AdminController::class, 'getSubAdmins']);
                Route::post('/invite', [AdminController::class, 'inviteSubAdmin']);
                Route::post('/{id}/re-invite', [AdminController::class, 'resendLink']);
                Route::put('/{id}/change-permissions', [AdminController::class, 'changeSubAdminPermissions']);
                Route::post('/{id}/reset-password', [AdminController::class, 'resetSubAdminResetPassword']);
                Route::post('/{id}/revoke', [AdminController::class, 'revokeSubAdmin']);
                Route::get('/{id}/ip-histories', [AdminController::class, 'getIpHistories']);
                Route::post('/{id}/undo-revoke', [AdminController::class, 'undoRevokeSubAdmin']);
            });

            //emailer
            Route::post('/emailer-admin', [AdminController::class, 'addEmailerAdmin']);
            Route::delete('/emailer-admin/{adminId}', [AdminController::class, 'deleteEmailerAdmin']);
            Route::get('/emailer-data', [AdminController::class, 'getEmailerData']);
            Route::put('/emailer-trigger-admin/{recordId}', [AdminController::class, 'updateEmailerTriggerAdmin']);
            Route::put('/emailer-trigger-user/{recordId}', [AdminController::class, 'updateEmailerTriggerUser']);

            // metrics
            Route::get('/metrics/{id}',  [MetricController::class, 'getMetricUser']);
            Route::put('/metrics/{id}',  [MetricController::class, 'updateMetric']);

            Route::get('/monitoring-criteria',  [AdminController::class, 'getMonitoringCriteria']);
            Route::put('/monitoring-criteria/{type}',  [AdminController::class, 'updateMonitoringCriteria']);

            Route::get('/notification/{id}',  [NotificationController::class, 'getNotificationDetail'])->where('id', '[0-9]+');
            Route::put('/notification/{id}',  [NotificationController::class, 'updateNotification'])->where('id', '[0-9]+');
            Route::post('/notification',  [NotificationController::class, 'createNotification']);
            Route::get('/notification',  [NotificationController::class, 'getNotification']);
            Route::get('/notification/{id}/view-logs',  [NotificationController::class, 'getUserViewLogs']);
            Route::get('/notification/high-priority',  [NotificationController::class, 'getHighPriority']);

            // rules lock
            Route::get('/lock-rules',  [AdminController::class, 'getLockRules']);
            Route::put('/lock-rules/{id}',  [AdminController::class, 'updateLockRules'])->where('id', '[0-9]+');
        });
        Route::prefix('discussions')->group(function () {
            Route::get('/trending', [DiscussionController::class, 'getTrending']);
            Route::get('/all', [DiscussionController::class, 'getDiscussions']);
            Route::get('/pin', [DiscussionController::class, 'getPinnedDiscussions']);
            Route::get('/my', [DiscussionController::class, 'getMyDiscussions']);
            Route::get('/detail/{id}', [DiscussionController::class, 'getDiscussion']);
            Route::post('/new', [DiscussionController::class, 'postDiscussion']);
            Route::delete('/{id}/new', [DiscussionController::class, 'removeNewMark']);
            Route::post('/{id}/comment', [DiscussionController::class, 'createComment']);
            Route::put('/{id}/comment', [DiscussionController::class, 'updateComment']);
            Route::post('/{id}/vote', [DiscussionController::class, 'setVote']);
            Route::post('/{id}/pin', [DiscussionController::class, 'setPin']);
            Route::get('/{id}/comment', [DiscussionController::class, 'getComment']);
        });

        Route::prefix('users/verification')->group(function () {
            Route::post('/submit-node', [VerificationController::class, 'submitNode']);
            Route::post('/submit-detail', [VerificationController::class, 'submitDetail']);
            Route::post('/upload-document', [VerificationController::class, 'uploadDocument']);
            Route::delete('/remove-document/{id}', [VerificationController::class, 'removeDocument']);
        });

        Route::prefix('perks')->group(function () {
            Route::get('/',  [PerkController::class, 'getPerksUser']);
            Route::get('/{id}',  [PerkController::class, 'getPerkDetailUser']);
        });
    });
});
