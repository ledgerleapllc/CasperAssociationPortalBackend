<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class AdminFunctionsTest extends TestCase
{
    public function testGetGraphInfo() {
        $response = $this->withHeaders([
            'Accept' => 'application/json',
        ])->json('get', '/api/v1/graph-info');

        // $apiResponse = $response->baseResponse->getData();

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'message',
                    'data',
                ]);
    }

    public function testGetUsers() {
        $token = $this->getAdminToken();
        
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => 'Bearer ' . $token,
        ])->json('get', '/api/v1/admin/users');

        // $apiResponse = $response->baseResponse->getData();

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'message',
                    'data',
                ]);
    }

    public function testListNode() {
        $token = $this->getAdminToken();

        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => 'Bearer ' . $token,
        ])->json('get', '/api/v1/admin/list-node');

        // $apiResponse = $response->baseResponse->getData();

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'message',
                    'data',
                ]);
    }

    public function testGetIntakes() {
        $token = $this->getAdminToken();

        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => 'Bearer ' . $token,
        ])->json('get', '/api/v1/admin/users/intakes');

        // $apiResponse = $response->baseResponse->getData();

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'message',
                    'data',
                ]);
    }

    public function testApproveIntakes() {
        $token = $this->getAdminToken();
        $user = $this->addUser();

        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => 'Bearer ' . $token,
        ])->json('post', '/api/v1/admin/users/intakes/' . $user->id . '/approve');

        // $apiResponse = $response->baseResponse->getData();

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'message',
                    'data',
                ]);
    }

    public function testResetIntakes() {
        $token = $this->getAdminToken();
        $user = $this->addUser();

        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => 'Bearer ' . $token,
        ])->json('post', '/api/v1/admin/users/intakes/' . $user->id . '/reset', [
            'message' => 'Reset Message'
        ]);

        // $apiResponse = $response->baseResponse->getData();

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'message',
                    'data',
                ]);
    }

    public function testBanUser() {
        $token = $this->getAdminToken();
        $user = $this->addUser();

        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => 'Bearer ' . $token,
        ])->json('post', '/api/v1/admin/users/' . $user->id . '/ban');

        // $apiResponse = $response->baseResponse->getData();

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'message',
                    'data',
                ]);
    }

    public function testGetBallots() {
        $token = $this->getAdminToken();

        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => 'Bearer ' . $token,
        ])->json('get', '/api/v1/admin/ballots');

        // $apiResponse = $response->baseResponse->getData();

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'message',
                    'data',
                ]);
    }

    public function testGetPerks() {
        $token = $this->getAdminToken();

        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => 'Bearer ' . $token,
        ])->json('get', '/api/v1/admin/perks');

        // $apiResponse = $response->baseResponse->getData();

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'message',
                    'data',
                ]);
    }
}