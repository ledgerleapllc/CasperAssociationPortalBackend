<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class AuthFunctionsTest extends TestCase
{
    public function testLogin() {
        $this->addAdmin();

        $params = [
            'email' => 'ledgerleapllcadmin@gmail.com',
            'password' => 'Ledgerleapllcadmin111@',
        ];

        $response = $this->withHeaders([
            'Accept' => 'application/json',
        ])->json('post', '/api/v1/auth/login', $params);

        // $apiResponse = $response->baseResponse->getData();
        
        $response->assertStatus(200)
                ->assertJsonStructure([
                    'message',
                    'data',
                ]);
    }

    public function testRegisterEntity() {
        $params = [
            'entity_name' => 'Test Entity',
            'entity_type' => 'Test Entity Type',
            'entity_register_number' => 'Test Number',
            'entity_register_country' => 'United States',
            'entity_tax' => 'Entity Tax',
            'first_name' => 'Test',
            'last_name' => 'Entity',
            'email' => 'testentity@gmail.com',
            'password' => 'TestEntity111@',
            'pseudonym' => 'testentity',
            'telegram' => '@testentity',
        ];

        $response = $this->withHeaders([
            'Accept' => 'application/json',
        ])->json('post', '/api/v1/auth/register-entity', $params);
        
        // $apiResponse = $response->baseResponse->getData();

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'message',
                    'data',
                ]);
    }

    public function testRegisterIndividual() {
        $params = [
            'first_name' => 'Test',
            'last_name' => 'Individual',
            'email' => 'testindividual@gmail.com',
            'password' => 'TestIndividual111@',
            'pseudonym' => 'testindividual',
            'telegram' => '@testindividual',
        ];

        $response = $this->withHeaders([
            'Accept' => 'application/json',
        ])->json('post', '/api/v1/auth/register-individual', $params);

        // $apiResponse = $response->baseResponse->getData();

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'message',
                    'data',
                ]);
    }

    public function testRegisterSubAdmin() {
        $params = [
            'first_name' => 'Test',
            'last_name' => 'SubAdmin',
            'email' => 'testsubadmin@gmail.com',
            'code' => 'testcode',
            'password' => 'TestSubAdmin111@',
        ];

        $response = $this->withHeaders([
            'Accept' => 'application/json',
        ])->json('post', '/api/v1/auth/register-sub-admin', $params);

        // $apiResponse = $response->baseResponse->getData();

        $response->assertStatus(400)
                ->assertJsonStructure([
                    'message',
                    'data',
                ]);
    }

    public function testSendResetPassword() {
        $this->addUser();

        $params = [
            'email' => 'testindividual@gmail.com',
        ];

        $response = $this->withHeaders([
            'Accept' => 'application/json',
        ])->json('post', '/api/v1/auth/send-reset-password', $params);

        // $apiResponse = $response->baseResponse->getData();

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'message',
                    'data',
                ]);
    }

    public function testResetPassword() {
        $this->addUser();

        $params = [
            'email' => 'testindividual@gmail.com',
            'code' => 'testcode',
            'password' => 'NewTestIndividual111@'
        ];

        $response = $this->withHeaders([
            'Accept' => 'application/json',
        ])->json('post', '/api/v1/auth/reset-password', $params);

        // $apiResponse = $response->baseResponse->getData();

        $response->assertStatus(400)
                ->assertJsonStructure([
                    'message',
                    'data',
                ]);
    }

    public function testVerifyEmail() {
        $token = $this->getUserToken();

        $params = [
            'email' => 'testindividual@gmail.com',
            'code' => 'testcode',
        ];

        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => 'Bearer ' . $token,
        ])->json('post', '/api/v1/users/verify-email', $params);

        // $apiResponse = $response->baseResponse->getData();

        $response->assertStatus(400)
                ->assertJsonStructure([
                    'message',
                    'data',
                ]);
    }

    public function testResendVerifyEmail() {
        $token = $this->getUserToken();

        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => 'Bearer ' . $token,
        ])->json('post', '/api/v1/users/resend-verify-email', []);

        // $apiResponse = $response->baseResponse->getData();
        
        $response->assertStatus(200)
                ->assertJsonStructure([
                    'message',
                    'data',
                ]);
    }
}