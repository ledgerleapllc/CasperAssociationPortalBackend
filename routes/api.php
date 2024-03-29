<?php

use App\Http\Controllers\Api\V1\AdminController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\ContactController;
use App\Http\Controllers\Api\V1\HelloSignController;
use App\Http\Controllers\Api\V1\UserController;
use App\Http\Controllers\Api\V1\DiscussionController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\PerkController;
use App\Http\Controllers\Api\V1\VerificationController;
use App\Http\Controllers\Api\V1\BlockAccessController;

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

// Route::get('/dev-verify-node/{address}', [AuthController::class, 'devVerifyNode'])->where('address', '[0-9a-zA-Z]+');
Route::get('/test-job', [AuthController::class, 'testJob']);
Route::get('/dev-upgrade-list', [AuthController::class, 'devUpgradeList']);

Route::namespace('Api')->middleware([])->group(function () {
    Route::post('hellosign', [HelloSignController::class, 'hellosignHook']);
});

Route::post('shuftipro-status', [UserController::class, 'updateShuftiproStatus']);
Route::get('shuftipro-status', [UserController::class, 'updateShuftiproStatus']);
Route::put('shuftipro-status', [UserController::class, 'updateShuftiproStatus']);

Route::prefix('v1')->namespace('Api')->middleware([])->group(function () {
    Route::post('/auth/login', [AuthController::class, 'login'])->name('login');
    Route::post('/auth/register-entity', [AuthController::class, 'registerEntity']);
    Route::post('/auth/register-individual', [AuthController::class, 'registerIndividual']);
    Route::post('/auth/register-sub-admin', [AuthController::class, 'registerSubAdmin']);
    Route::post('/auth/send-reset-password', [AuthController::class, 'sendResetLinkEmail']);
    Route::post('auth/reset-password', [AuthController::class, 'resetPassword']);
    Route::get('/members', [UserController::class, 'getMembers']);
    Route::get('/members/ca-kyc-hash/{hash}', [UserController::class, 'getCaKycHash'])->where('hash', '[0-9a-zA-Z]+');
    Route::get('/members/{id}', [UserController::class, 'getMemberDetail'])->where('id', '[0-9]+');
    Route::post('/users/cancel-change-email', [UserController::class, 'cancelChangeEmail']);
    Route::post('/users/confirm-change-email', [UserController::class, 'confirmChangeEmail']);
    Route::get('/graph-info', [AdminController::class, 'getGraphInfo']);
    Route::get('/donation', [UserController::class, 'getDonationSessionId']);
    Route::post('/donation', [UserController::class, 'submitDonation']);
    Route::post('/contact-us',  [ContactController::class, 'submitContact']);
    Route::post('/users/check-validator-address', [UserController::class, 'checkValidatorAddress']);
    Route::post('/upgrade-list', [ContactController::class, 'submitUpgradeList']);
    Route::middleware(['auth:api'])->group(function () {
        Route::middleware(['user_banned'])->group(function () {
            // New dashboard endpoint
            Route::get('/users/get-dashboard', [UserController::class, 'getUserDashboard']);

            // New membership page endpoint
            Route::get('/users/get-membership-page', [UserController::class, 'getMembershipPage']);

            // New Nodes page endpoint
            Route::get('/users/get-nodes-page', [UserController::class, 'getNodesPage']);
            
            // New My Eras page endpoint
            Route::get('/users/get-my-eras', [UserController::class, 'getMyEras']);
            
            // New endpoint for User voting eligibility check
            Route::get('/users/can-vote', [UserController::class, 'canVote']);
            Route::post('/users/can-request-reactivation', [UserController::class, 'canRequestReactivation']);
            Route::post('/users/request-reactivation', [UserController::class, 'requestReactivation']);
            
            Route::post('/users/verify-email', [AuthController::class, 'verifyEmail']);
            Route::post('/users/resend-verify-email', [AuthController::class, 'resendVerifyEmail']);
            Route::post('/users/change-email', [UserController::class, 'changeEmail']);
            Route::get('/users/profile', [UserController::class, 'getProfile']);
            Route::post('users/hellosign-request', [UserController::class, 'sendHellosignRequest']);
            Route::post('users/submit-public-address', [UserController::class, 'submitPublicAddress']);
            Route::post('users/check-public-address', [UserController::class, 'checkPublicAddress']);
            Route::post('users/verify-file-casper-signer', [UserController::class, 'verifyFileCasperSigner']);
            Route::post('users/verify-file-casper-signer-2', [UserController::class, 'verifyFileCasperSigner2']);
            Route::post('users/submit-kyc', [UserController::class, 'functionSubmitKYC']);
            Route::get('users/message-content', [UserController::class, 'getMessageContent']);
            Route::post('users/shuftipro-temp',  [UserController::class, 'saveShuftiproTemp']);
            Route::put('users/shuftipro-temp/delete', [UserController::class, 'deleteShuftiproTemp']);
            Route::post('/users/upload-letter',  [UserController::class, 'uploadLetter']);
            Route::get('users/votes', [UserController::class, 'getVotes']);
            Route::get('users/my-votes', [UserController::class, 'getMyVotes']);
            Route::get('users/votes/{id}', [UserController::class, 'getVoteDetail']);
            Route::post('users/votes/{id}', [UserController::class, 'vote']);
            Route::post('users/viewed-docs/{fileId}', [UserController::class, 'submitViewFileBallot']);
            Route::post('/users/upload-avatar',  [UserController::class, 'uploadAvatar']);
            Route::post('/users/check-password',  [UserController::class, 'checkCurrentPassword']);
            Route::post('/users/settings',  [UserController::class, 'settingUser']);
            
            Route::post('/users/check-login-2fa',  [UserController::class, 'checkLogin2FA']);
            Route::post('/users/resend-2fa',  [UserController::class, 'resend2FA']);
            Route::get('/users/notification',  [NotificationController::class, 'getNotificationUser']);
            Route::put('/users/notification/{id}/view',  [NotificationController::class, 'updateView'])->where('id', '[0-9]+');
            Route::put('/users/notification/{id}/dismiss',  [NotificationController::class, 'dismiss'])->where('id', '[0-9]+');
            Route::put('/users/notification/{id}/click-cta',  [NotificationController::class, 'clickCTA'])->where('id', '[0-9]+');
            
            // rules lock
            Route::get('/users/lock-rules',  [UserController::class, 'getLockRules']);
            Route::get('users/list-node-by', [UserController::class, 'getListNodesBy']);
            Route::get('users/dashboard', [UserController::class, 'infoDashboard']);
            
            Route::post('/users/contact-us',  [ContactController::class, 'submitContact']);
            Route::post('/users/complete-upgrade', [UserController::class, 'completeUpgrade']);

            Route::get('/users/membership-file',  [UserController::class, 'getMembershipFile']);
            Route::post('/users/membership-agreement',  [UserController::class, 'membershipAgreement']);
        });
        
        Route::prefix('admin')->middleware(['role_admin'])->group(function () {
            // New Nodes page endpoint
            Route::get('/users/get-nodes-page', [AdminController::class, 'getNodesPage']);
            
            // New Eras page endpoint
            Route::get('/users/all-eras', [AdminController::class, 'allEras']);

            // New Eras page endpoint for specific selected user
            Route::get('/users/all-eras-user/{id}', [AdminController::class, 'allErasUser'])->where('id', '[0-9]+');

            // New admin endpoint for manual kyc bypass approval
            Route::post('/users/bypass-approve-kyc/{user_id}', [AdminController::class, 'bypassApproveKYC'])->where('user_id', '[0-9]+');

            Route::get('/users', [AdminController::class, 'getUsers']);
            Route::get('/users/{id}', [AdminController::class, 'getUserDetail'])->where('id', '[0-9]+');
            Route::get('/dashboard', [AdminController::class, 'infoDashboard']);
            
            Route::get('/active-reinstatements', [AdminController::class, 'getActiveReinstatements']);
            Route::get('/history-reinstatements', [AdminController::class, 'getHistoryReinstatements']);

            Route::post('/approve-reinstatement', [AdminController::class, 'approveReinstatement']);
            Route::post('/reject-reinstatement', [AdminController::class, 'rejectReinstatement']);

            // intakes
            Route::middleware([])->group(function () {
                Route::get('/users/intakes', [AdminController::class, 'getIntakes']);
                Route::post('/users/intakes/{id}/approve', [AdminController::class, 'approveIntakeUser'])->where('id', '[0-9]+');
                Route::post('/users/intakes/{id}/reset', [AdminController::class, 'resetIntakeUser'])->where('id', '[0-9]+');
                Route::post('/users/{id}/ban', [AdminController::class, 'banUser'])->where('id', '[0-9]+');
                Route::post('/users/{id}/revoke', [AdminController::class, 'revokeUser'])->where('id', '[0-9]+');
                Route::post('/users/{id}/reactivate', [AdminController::class, 'reactivateUser'])->where('id', '[0-9]+');
                Route::post('/users/{id}/remove', [AdminController::class, 'removeUser'])->where('id', '[0-9]+');
            });
            
            // user
            Route::middleware([])->group(function () {
                Route::get('/users/verification', [AdminController::class, 'getVerificationUsers']);
                Route::get('/users/verification/{id}', [AdminController::class, 'getVerificationDetail'])->where('id', '[0-9]+');
                Route::post('/users/{id}/reset-kyc', [AdminController::class, 'resetKYC'])->where('id', '[0-9]+');
                Route::post('/users/{id}/approve-document', [AdminController::class, 'approveDocument'])->where('id', '[0-9]+');
            });

            // ballots
            Route::middleware([])->group(function () {
                Route::post('/ballots', [AdminController::class, 'submitBallot']);
                Route::get('/ballots', [AdminController::class, 'getBallots']);
                Route::get('/ballots/{id}', [AdminController::class, 'getDetailBallot'])->where('id', '[0-9]+');
                Route::post('/ballots/{id}/edit', [AdminController::class, 'editBallot'])->where('id', '[0-9]+');
                Route::get('/ballots/{id}/votes', [AdminController::class, 'getBallotVotes'])->where('id', '[0-9]+');
                Route::post('/ballots/{id}/cancel', [AdminController::class, 'cancelBallot'])->where('id', '[0-9]+');
                Route::get('/ballots/viewed-docs/{fileId}', [AdminController::class, 'getViewFileBallot'])->where('id', '[0-9]+');
            });

            // perk
            Route::middleware([])->group(function () {
                Route::get('/perks',  [PerkController::class, 'getPerksAdmin']);
                Route::post('/perks/update/{id}',  [PerkController::class, 'updatePerk']);
                Route::get('/perks/{id}',  [PerkController::class, 'getPerkDetailAdmin']);
                Route::get('/perks/{id}/result',  [PerkController::class, 'getPerkResultAdmin']);
                Route::delete('/perks/{id}',  [PerkController::class, 'deletePerk']);
                Route::post('/perks',  [PerkController::class, 'createPerk']);
            });

            Route::get('/upgrades', [AdminController::class, 'getUpgrades']);
            Route::get('/upgrades/{id}', [AdminController::class, 'getSingleUpgrade']);
            Route::post('/upgrades', [AdminController::class, 'createUpgrade']);
            Route::put('/upgrades/{id}', [AdminController::class, 'updateUpgrade']);
            Route::delete('/upgrades/{id}', [AdminController::class, 'deleteUpgrade']);

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
            
            // emailer
            Route::post('/emailer-admin', [AdminController::class, 'addEmailerAdmin']);
            Route::delete('/emailer-admin/{adminId}', [AdminController::class, 'deleteEmailerAdmin']);
            Route::get('/emailer-data', [AdminController::class, 'getEmailerData']);
            Route::put('/emailer-trigger-admin/{recordId}', [AdminController::class, 'updateEmailerTriggerAdmin']);
            Route::put('/emailer-trigger-user/{recordId}', [AdminController::class, 'updateEmailerTriggerUser']);
            
            Route::get('/notification/{id}',  [NotificationController::class, 'getNotificationDetail'])->where('id', '[0-9]+');
            Route::put('/notification/{id}',  [NotificationController::class, 'updateNotification'])->where('id', '[0-9]+');
            Route::post('/notification',  [NotificationController::class, 'createNotification']);
            Route::get('/notification',  [NotificationController::class, 'getNotification']);
            Route::get('/notification/{id}/view-logs',  [NotificationController::class, 'getUserViewLogs']);
            Route::get('/notification/high-priority',  [NotificationController::class, 'getHighPriority']);
            
            // rules lock
            Route::put('/lock-rules/{id}',  [AdminController::class, 'updateLockRules'])->where('id', '[0-9]+');
            
            // contact recipients
            Route::post('/contact-recipients',  [ContactController::class, 'addContactRecipients']);
            Route::delete('/contact-recipients/{id}',  [ContactController::class, 'deleteContactRecipients'])->where('id', '[0-9]+');
            Route::post('/membership-file',  [AdminController::class, 'uploadMembershipFile']);
            
            // CMP Status
            Route::post('/cmp-status', [AdminController::class, 'updateCMPStatus']);

            // Block Access
            Route::post('/block-access', [BlockAccessController::class, 'updateBlockAccess']);
        });
        
        Route::prefix('discussions')->group(function () {
            Route::get('/trending', [DiscussionController::class, 'getTrending']);
            Route::get('/all', [DiscussionController::class, 'getDiscussions']);
            Route::get('/pin', [DiscussionController::class, 'getPinnedDiscussions']);
            Route::get('/my', [DiscussionController::class, 'getMyDiscussions']);
            Route::get('/detail/{id}', [DiscussionController::class, 'getDiscussion']);
            Route::post('/new', [DiscussionController::class, 'postDiscussion']);
            Route::put('/{id}', [DiscussionController::class, 'updateDiscussion']);
            Route::delete('/{id}', [DiscussionController::class, 'deleteDiscussion']);
            Route::delete('/{id}/comment/{commentId}', [DiscussionController::class, 'deleteComment']);
            Route::delete('/{id}/new', [DiscussionController::class, 'removeNewMark']);
            Route::post('/{id}/comment', [DiscussionController::class, 'createComment']);
            Route::put('/{id}/comment', [DiscussionController::class, 'updateComment']);
            Route::post('/{id}/vote', [DiscussionController::class, 'setVote']);
            Route::post('/{id}/pin', [DiscussionController::class, 'setPin']);
            Route::get('/{id}/comment', [DiscussionController::class, 'getComment']);
            Route::post('/{id}/publish', [DiscussionController::class, 'publishDraftDiscussion']);
            Route::get('/draft', [DiscussionController::class, 'getDraftDiscussions']);
            Route::delete('{id}/draft', [DiscussionController::class, 'deleteDraftDiscussions']);
        });
        
        Route::prefix('users/verification')->group(function () {
            Route::post('/submit-node', [VerificationController::class, 'submitNode']);
            Route::post('/submit-detail', [VerificationController::class, 'submitDetail']);
            Route::post('/upload-document', [VerificationController::class, 'uploadDocument']);
        });

        Route::prefix('perks')->group(function () {
            Route::get('/',  [PerkController::class, 'getPerksUser']);
            Route::get('/{id}',  [PerkController::class, 'getPerkDetailUser']);
        });
    });
});