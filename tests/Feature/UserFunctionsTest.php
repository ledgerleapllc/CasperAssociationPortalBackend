<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
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

    public function testGetDonation() {
        $params = [
            'sessionId' => 'cs_test_a1DLPYuwW0FCjwvnH8eRr9hdHZk8yPAQT5JFL1sEYRrEoiwlKHsEywo9Mw'
        ];

        $response = $this->withHeaders([
            'Accept' => 'application/json',
        ])->json('get', '/api/v1/donation', $params);

        // $apiResponse = $response->baseResponse->getData();

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'message',
                    'data',
                ]);
    }

    public function testSubmitDonation() {
        $params = [
            'first_name' => 'Test',
            'last_name' => 'Individual',
            'email' => 'testindividual@gmail.com',
            'amount' => 20,
        ];

        $response = $this->withHeaders([
            'Accept' => 'application/json',
        ])->json('post', '/api/v1/donation', $params);

        // $apiResponse = $response->baseResponse->getData();

        $response->assertStatus(200)
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

    /*
    public function testChangeEmail() {
    
    }

    public function testChangePassword() {

    }
    */

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

    public function testListNodes() {
        $token = $this->getUserToken();

        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => 'Bearer ' . $token,
        ])->json('get', '/api/v1/users/list-node');
        
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
}