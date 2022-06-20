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
}