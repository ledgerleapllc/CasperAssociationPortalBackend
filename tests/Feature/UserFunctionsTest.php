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
}
