<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use App\Models\User;
use Tests\TestCase;

class UserFunctionsTest extends TestCase
{
    public function testGetMembers() {
        $response = $this->withHeaders([
            'Accept' => 'application/json',
        ])->json('get', '/api/v1/members');
        
        // $apiResponse = $response->baseResponse->getData();
        
        $response->assertStatus(200)
                ->assertJsonStructure([
                    'message',
                    'data',
                ]);
    }

    public function testGetCaKycHash() {
        $response = $this->withHeaders([
            'Accept' => 'application/json',
        ])->json('get', '/api/v1/members/ca-kyc-hash/AB10BC99');

        // $apiResponse = $response->baseResponse->getData();
        
        $response->assertStatus(200)
                ->assertJsonStructure([
                    'message',
                    'data',
                ]);
    }

    public function testGetMemberDetail() {
        $response = $this->withHeaders([
            'Accept' => 'application/json',
        ])->json('get', '/api/v1/members/1');

        // $apiResponse = $response->baseResponse->getData();

        $response->assertStatus(404)
                ->assertJsonStructure([
                    'message',
                    'data',
                ]);
    }

    public function testCancelChangeEmail() {
        $this->addUser();
        
        $params = [
            'email' => 'testindividual@gmail.com',
            'code' => 'testcode'
        ];

        $response = $this->withHeaders([
            'Accept' => 'application/json',
        ])->json('post', '/api/v1/users/cancel-change-email', $params);

        // $apiResponse = $response->baseResponse->getData();
        
        $response->assertStatus(400)
                ->assertJsonStructure([
                    'message',
                    'data',
                ]);
    }

    public function testConfirmChangeEmail() {
        $this->addUser();
        
        $params = [
            'email' => 'testindividual@gmail.com',
            'code' => 'testcode'
        ];

        $response = $this->withHeaders([
            'Accept' => 'application/json',
        ])->json('post', '/api/v1/users/confirm-change-email', $params);

        // $apiResponse = $response->baseResponse->getData();

        $response->assertStatus(400)
                ->assertJsonStructure([
                    'message',
                    'data',
                ]);
    }

    public function testSubmitContact() {
        $params = [
            'name' => 'Test Individual',
            'email' => 'testindividual@gmail.com',
            'message' => 'Test Message'
        ];

        $response = $this->withHeaders([
            'Accept' => 'application/json',
        ])->json('post', '/api/v1/contact-us', $params);

        // $apiResponse = $response->baseResponse->getData();

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'message',
                    'data',
                ]);
    }

    public function testCheckValidatorAddress() {
        $params = [
            'public_address' => '01ebaebffebe63ee6e35b88697dd9d5bfab23dac47cbd61a45efc8ea8d80ec9c38'
        ];

        $response = $this->withHeaders([
            'Accept' => 'application/json',
        ])->json('post', '/api/v1/users/check-validator-address', $params);

        // $apiResponse = $response->baseResponse->getData();

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'message',
                    'data',
                ]);
    }

    public function testGetUserDashboard() {
        $token = $this->getUserToken();

        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => 'Bearer ' . $token,
        ])->json('get', '/api/v1/users/get-dashboard');

        // $apiResponse = $response->baseResponse->getData();

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'message',
                    'data',
                ]);
    }

    public function testGetMembershipPage() {
        $token = $this->getUserToken();

        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => 'Bearer ' . $token,
        ])->json('get', '/api/v1/users/get-membership-page');

        // $apiResponse = $response->baseResponse->getData();

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'message',
                    'data',
                ]);
    }

    public function testGetNodesPage() {
        $token = $this->getUserToken();

        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => 'Bearer ' . $token,
        ])->json('get', '/api/v1/users/get-nodes-page');
        
        // $apiResponse = $response->baseResponse->getData();
        
        $response->assertStatus(200)
                ->assertJsonStructure([
                    'message',
                    'data',
                ]);
    }

    public function testGetMyEras() {
        $token = $this->getUserToken();

        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => 'Bearer ' . $token,
        ])->json('get', '/api/v1/users/get-my-eras');
        
        // $apiResponse = $response->baseResponse->getData();
        
        $response->assertStatus(200)
                ->assertJsonStructure([
                    'message',
                    'data',
                ]);
    }

    public function testCanVote() {
        $token = $this->getUserToken();

        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => 'Bearer ' . $token,
        ])->json('get', '/api/v1/users/can-vote');
        
        // $apiResponse = $response->baseResponse->getData();
        
        $response->assertStatus(200)
                ->assertJsonStructure([
                    'message',
                    'data',
                ]);
    }

    public function testChangeEmail() {
        $token = $this->getUserToken();

        $params = [
            'email' => 'testindividualnew@gmail.com',
        ];

        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => 'Bearer ' . $token,
        ])->json('post', '/api/v1/users/change-email', $params);

        // $apiResponse = $response->baseResponse->getData();

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'message',
                    'data',
                ]);
    }
    
    public function testGetProfile() {
        $token = $this->getUserToken();

        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => 'Bearer ' . $token,
        ])->json('get', '/api/v1/users/profile');

        // $apiResponse = $response->baseResponse->getData();

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'message',
                    'data',
                ]);
    }

    public function testSubmitPublicAddress() {
        $token = $this->getUserToken();

        $params = [
            'public_address' => '011117189c666f81c5160cd610ee383dc9b2d0361f004934754d39752eedc64957',
        ];

        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => 'Bearer ' . $token,
        ])->json('post', '/api/v1/users/submit-public-address', $params);

        // $apiResponse = $response->baseResponse->getData();

        $response->assertJsonStructure([
                    'message',
                    'data',
                ]);
    }

    public function testCheckPublicAddress() {
        $token = $this->getUserToken();

        $params = [
            'public_address' => '011117189c666f81c5160cd610ee383dc9b2d0361f004934754d39752eedc64957',
        ];

        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => 'Bearer ' . $token,
        ])->json('post', '/api/v1/users/check-public-address', $params);

        // $apiResponse = $response->baseResponse->getData();

        $response->assertJsonStructure([
                    'message',
                    'data',
                ]);
    }

    public function testVerifyFileCasperSigner() {
        $token = $this->getUserToken();

        $file = UploadedFile::fake()->create('document.pdf', 10, 'application/pdf');
        $params = [
            'file' => $file,
            'address' => '011117189c666f81c5160cd610ee383dc9b2d0361f004934754d39752eedc64957',
        ];

        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => 'Bearer ' . $token,
        ])->json('post', '/api/v1/users/verify-file-casper-signer', $params);

        // $apiResponse = $response->baseResponse->getData();

        $response->assertJsonStructure([
                    'message',
                    'data',
                ]);
    }

    public function testVerifyFileCasperSigner2() {
        $token = $this->getUserToken();

        $file = UploadedFile::fake()->create('document.pdf', 10, 'application/pdf');
        $params = [
            'file' => $file,
            'address' => '011117189c666f81c5160cd610ee383dc9b2d0361f004934754d39752eedc64957',
        ];

        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => 'Bearer ' . $token,
        ])->json('post', '/api/v1/users/verify-file-casper-signer-2', $params);

        // $apiResponse = $response->baseResponse->getData();

        $response->assertJsonStructure([
                    'message',
                    'data',
                ]);
    }

    public function testSubmitKYC() {
        $token = $this->getUserToken();

        $params = [
            'first_name' => 'Test',
            'last_name' => 'Individual',
            'dob' => '03/03/1992',
            'address' => 'New York',
            'city' => 'New York',
            'zip' => '10025',
            'country_citizenship' => 'United States',
            'country_residence' => 'United States',
            'type' => User::TYPE_INDIVIDUAL,
        ];

        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => 'Bearer ' . $token,
        ])->json('post', '/api/v1/users/submit-kyc', $params);

        // $apiResponse = $response->baseResponse->getData();

        $response->assertJsonStructure([
                    'message',
                    'data',
                ]);
    }

    /*
    public function testVerifyOwnerNode() {
        $token = $this->getUserToken();

        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => 'Bearer ' . $token,
        ])->json('post', '/api/v1/users/verify-owner-node', []);

        // $apiResponse = $response->baseResponse->getData();

        $response->assertJsonStructure([
                    'message',
                    'data',
                ]);
    }
    */

    /*
    public function testGetOwnerNodes() {
        $token = $this->getUserToken();

        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => 'Bearer ' . $token,
        ])->json('get', '/api/v1/users/owner-node');

        // $apiResponse = $response->baseResponse->getData();

        $response->assertJsonStructure([
                    'message',
                    'data',
                ]);
    }
    */

    /*
    public function testResendInviteOwner() {
        $token = $this->getUserToken();

        $params = [
            'email' => 'testindividual@gmail.com',
        ];

        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => 'Bearer ' . $token,
        ])->json('post', '/api/v1/users/resend-invite-owner', $params);

        // $apiResponse = $response->baseResponse->getData();

        $response->assertJsonStructure([
                    'message',
                    'data',
                ]);
    }
    */

    public function testGetMessageContent() {
        $token = $this->getUserToken();

        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => 'Bearer ' . $token,
        ])->json('get', '/api/v1/users/message-content');

        $content = $response->streamedContent();

        $response->assertStatus(200);
    }

    public function testSaveShuftiproTemp() {
        $token = $this->getUserToken();

        $params = [
            'reference_id' => 'TestReferenceId'
        ];

        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => 'Bearer ' . $token,
        ])->json('post', '/api/v1/users/shuftipro-temp', $params);

        // $apiResponse = $response->baseResponse->getData();

        $response->assertJsonStructure([
                    'message',
                    'data',
                ]);
    }

    /*
    public function testUpdateShuftiproTemp() {
        $token = $this->getUserToken();

        $params = [
            'reference_id' => 'TestReferenceId'
        ];

        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => 'Bearer ' . $token,
        ])->json('put', '/api/v1/users/shuftipro-temp', $params);

        // $apiResponse = $response->baseResponse->getData();

        $response->assertJsonStructure([
                    'message',
                    'data',
                ]);
    }
    */

    public function testDeleteShuftiproTemp() {
        $token = $this->getUserToken();

        $params = [
            'reference_id' => 'TestReferenceId'
        ];

        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => 'Bearer ' . $token,
        ])->json('put', '/api/v1/users/shuftipro-temp/delete', $params);

        // $apiResponse = $response->baseResponse->getData();

        $response->assertJsonStructure([
                    'message',
                    'data',
                ]);
    }

    public function testUploadLetter() {
        $token = $this->getUserToken();

        $file = UploadedFile::fake()->create('letter.pdf', 10, 'application/pdf');
        $params = [
            'file' => $file,
        ];

        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => 'Bearer ' . $token,
        ])->json('post', '/api/v1/users/upload-letter', $params);

        // $apiResponse = $response->baseResponse->getData();

        $response->assertJsonStructure([
                    'message',
                    'data',
                ]);
    }

    public function testListNodesBy() {
        $token = $this->getUserToken();

        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => 'Bearer ' . $token,
        ])->json('get', '/api/v1/users/list-node-by');
        
        // $apiResponse = $response->baseResponse->getData();
        
        $response->assertStatus(200)
                ->assertJsonStructure([
                    'message',
                    'data',
                ]);
    }

    public function testGetLockRules() {
        $token = $this->getUserToken();

        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => 'Bearer ' . $token,
        ])->json('get', '/api/v1/users/lock-rules');

        // $apiResponse = $response->baseResponse->getData();

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'message',
                    'data',
                ]);
    }
    
    public function testInfoDashboard() {
        $token = $this->getUserToken();
        
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => 'Bearer ' . $token,
        ])->json('get', '/api/v1/users/dashboard');
        
        // $apiResponse = $response->baseResponse->getData();
        
        $response->assertStatus(200)
                ->assertJsonStructure([
                    'message',
                    'data',
                ]);
    }

    /*
    public function testGetEarningByNode() {
        $node = '011117189c666f81c5160cd610ee383dc9b2d0361f004934754d39752eedc64957';
        $token = $this->getUserToken();

        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => 'Bearer ' . $token,
        ])->json('get', '/api/v1/nodes/' . $node . '/earning');

        // $apiResponse = $response->baseResponse->getData();

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'message',
                    'data',
                ]);
    }
    */

    /*
    public function testGetChartEarningByNode() {
        $node = '011117189c666f81c5160cd610ee383dc9b2d0361f004934754d39752eedc64957';
        $token = $this->getUserToken();

        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => 'Bearer ' . $token,
        ])->json('get', '/api/v1/nodes/' . $node . '/chart');

        // $apiResponse = $response->baseResponse->getData();

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'message',
                    'data',
                ]);
    }
    */

    public function testMembershipAgreement() {
        $token = $this->getUserToken();

        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => 'Bearer ' . $token,
        ])->json('post', '/api/v1/users/membership-agreement');

        // $apiResponse = $response->baseResponse->getData();

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'message',
                    'data',
                ]);
    }

    public function testGetMembershipFile() {
        $token = $this->getUserToken();

        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => 'Bearer ' . $token,
        ])->json('get', '/api/v1/users/membership-file');

        // $apiResponse = $response->baseResponse->getData();

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'message',
                    'data',
                ]);
    }

    public function testGetActiveVotes() {
        $tokenData = $this->getUserTokenData();
        $user = $tokenData['user'];
        $token = $tokenData['token'];

        $this->unBlockAccess($user->id, 'votes');

        $params = [
            'status' => 'active'
        ];

        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => 'Bearer ' . $token,
        ])->json('get', '/api/v1/users/votes', $params);

        $apiResponse = $response->baseResponse->getData();

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'message',
                    'data',
                ]);

        $data = $apiResponse->data;
        $this->assertTrue(is_object($data) && property_exists($data, 'current_page'));
    }

    public function testBlockedGetActiveVotes() {
        $tokenData = $this->getUserTokenData();
        $user = $tokenData['user'];
        $token = $tokenData['token'];

        $this->blockAccess($user->id, 'votes');

        $params = [
            'status' => 'active'
        ];

        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => 'Bearer ' . $token,
        ])->json('get', '/api/v1/users/votes', $params);

        $apiResponse = $response->baseResponse->getData();

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'message',
                    'data',
                ]);

        $data = $apiResponse->data;
        $this->assertTrue(!(is_object($data) && property_exists($data, 'current_page')));
    }

    public function testGetScheduledVotes() {
        $token = $this->getUserToken();

        $params = [
            'status' => 'scheduled'
        ];

        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => 'Bearer ' . $token,
        ])->json('get', '/api/v1/users/votes', $params);

        // $apiResponse = $response->baseResponse->getData();

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'message',
                    'data',
                ]);
    }
}