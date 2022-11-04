<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class BlockAccessFunctionsTest extends TestCase
{
    public function testUpdateBlockAccess() {
        $user = $this->addUser();
        $token = $this->getAdminToken();
        
        $params = [
            'userId' => $user->id,
            'name' => 'nodes',
            'blocked' => 1,
        ];

        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => 'Bearer ' . $token,
        ])->json('post', '/api/v1/admin/block-access', $params);

        $apiResponse = $response->baseResponse->getData();

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'message',
                    'data',
                ]);
    }

    public function testGetPerks() {
        $tokenData = $this->getUserTokenData();
        $user = $tokenData['user'];
        $token = $tokenData['token'];

        $this->unBlockAccess($user->id, 'perks');

        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => 'Bearer ' . $token,
        ])->json('get', '/api/v1/perks');

        $apiResponse = $response->baseResponse->getData();
        $response->assertStatus(200)
                ->assertJsonStructure([
                    'message',
                    'data',
                ]);

        $data = $apiResponse->data;
        $this->assertTrue(is_object($data) && property_exists($data, 'current_page'));
    }

    public function testBlockedGetPerks() {
        $tokenData = $this->getUserTokenData();
        $user = $tokenData['user'];
        $token = $tokenData['token'];

        $this->blockAccess($user->id, 'perks');

        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => 'Bearer ' . $token,
        ])->json('get', '/api/v1/perks');

        $apiResponse = $response->baseResponse->getData();
        $response->assertStatus(200)
                ->assertJsonStructure([
                    'message',
                    'data',
                ]);

        $data = $apiResponse->data;
        $this->assertTrue(!(is_object($data) && property_exists($data, 'current_page')));
    }

    public function testBlockedGetPerksDetail() {
        $tokenData = $this->getUserTokenData();
        $user = $tokenData['user'];
        $token = $tokenData['token'];

        $this->blockAccess($user->id, 'perks');

        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => 'Bearer ' . $token,
        ])->json('get', '/api/v1/perks/1');

        $apiResponse = $response->baseResponse->getData();

        $response->assertStatus(400)
                ->assertJsonStructure([
                    'message',
                    'data',
                ]);

        $message = $apiResponse->message;
        $this->assertTrue($message == 'Your access is blocked');
    }

    public function testGetPerksDetailNotFound() {
        $tokenData = $this->getUserTokenData();
        $user = $tokenData['user'];
        $token = $tokenData['token'];

        $this->unBlockAccess($user->id, 'perks');

        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => 'Bearer ' . $token,
        ])->json('get', '/api/v1/perks/1');

        $apiResponse = $response->baseResponse->getData();

        $response->assertStatus(400)
                ->assertJsonStructure([
                    'message',
                    'data',
                ]);

        $message = $apiResponse->message;
        $this->assertTrue($message == 'Not found perk');
    }

    public function testBlockedGetMyVotes() {
        $tokenData = $this->getUserTokenData();
        $user = $tokenData['user'];
        $token = $tokenData['token'];

        $this->blockAccess($user->id, 'votes');

        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => 'Bearer ' . $token,
        ])->json('get', '/api/v1/users/my-votes');

        $apiResponse = $response->baseResponse->getData();

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'message',
                    'data',
                ]);

        $data = $apiResponse->data;
        $this->assertTrue(!(is_object($data) && property_exists($data, 'current_page')));
    }

    public function testGetMyVotes() {
        $tokenData = $this->getUserTokenData();
        $user = $tokenData['user'];
        $token = $tokenData['token'];

        $this->unBlockAccess($user->id, 'votes');

        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => 'Bearer ' . $token,
        ])->json('get', '/api/v1/users/my-votes');

        $apiResponse = $response->baseResponse->getData();

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'message',
                    'data',
                ]);

        $data = $apiResponse->data;
        $this->assertTrue(is_object($data) && property_exists($data, 'current_page'));
    }

    public function testGetTrendingDiscussions() {
        $tokenData = $this->getUserTokenData();
        $user = $tokenData['user'];
        $token = $tokenData['token'];

        $this->unBlockAccess($user->id, 'discussions');

        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => 'Bearer ' . $token,
        ])->json('get', '/api/v1/discussions/trending');

        // $apiResponse = $response->baseResponse->getData();

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'message',
                    'data',
                ]);

        $this->blockAccess($user->id, 'discussions');

        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => 'Bearer ' . $token,
        ])->json('get', '/api/v1/discussions/trending');

        $apiResponse = $response->baseResponse->getData();

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'message',
                    'data',
                ]);

        $data = $apiResponse->data;
        $this->assertTrue(!(is_object($data) && property_exists($data, 'current_page')));
    }

    public function testGetDiscussions() {
        $tokenData = $this->getUserTokenData();
        $user = $tokenData['user'];
        $token = $tokenData['token'];

        $this->unBlockAccess($user->id, 'discussions');

        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => 'Bearer ' . $token,
        ])->json('get', '/api/v1/discussions/all');

        $apiResponse = $response->baseResponse->getData();

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'message',
                    'data',
                ]);

        $data = $apiResponse->data;
        $this->assertTrue(is_object($data) && property_exists($data, 'current_page'));

        $this->blockAccess($user->id, 'discussions');

        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => 'Bearer ' . $token,
        ])->json('get', '/api/v1/discussions/all');

        $apiResponse = $response->baseResponse->getData();

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'message',
                    'data',
                ]);

        $data = $apiResponse->data;
        $this->assertTrue(!(is_object($data) && property_exists($data, 'current_page')));
    }

    public function testGetDraftDiscussions() {
        $tokenData = $this->getUserTokenData();
        $user = $tokenData['user'];
        $token = $tokenData['token'];

        $this->unBlockAccess($user->id, 'discussions');

        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => 'Bearer ' . $token,
        ])->json('get', '/api/v1/discussions/draft');

        $apiResponse = $response->baseResponse->getData();

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'message',
                    'data',
                ]);

        $data = $apiResponse->data;
        $this->assertTrue(is_object($data) && property_exists($data, 'current_page'));

        $this->blockAccess($user->id, 'discussions');

        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => 'Bearer ' . $token,
        ])->json('get', '/api/v1/discussions/draft');

        $apiResponse = $response->baseResponse->getData();

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'message',
                    'data',
                ]);

        $data = $apiResponse->data;
        $this->assertTrue(!(is_object($data) && property_exists($data, 'current_page')));
    }

    public function testGetPinnedDiscussions() {
        $tokenData = $this->getUserTokenData();
        $user = $tokenData['user'];
        $token = $tokenData['token'];

        $this->unBlockAccess($user->id, 'discussions');

        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => 'Bearer ' . $token,
        ])->json('get', '/api/v1/discussions/pin');

        $apiResponse = $response->baseResponse->getData();

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'message',
                    'data',
                ]);

        $data = $apiResponse->data;
        $this->assertTrue(is_object($data) && property_exists($data, 'current_page'));

        $this->blockAccess($user->id, 'discussions');

        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => 'Bearer ' . $token,
        ])->json('get', '/api/v1/discussions/pin');

        $apiResponse = $response->baseResponse->getData();

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'message',
                    'data',
                ]);

        $data = $apiResponse->data;
        $this->assertTrue(!(is_object($data) && property_exists($data, 'current_page')));
    }

    public function testGetMyDiscussions() {
        $tokenData = $this->getUserTokenData();
        $user = $tokenData['user'];
        $token = $tokenData['token'];

        $this->unBlockAccess($user->id, 'discussions');

        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => 'Bearer ' . $token,
        ])->json('get', '/api/v1/discussions/my');

        $apiResponse = $response->baseResponse->getData();

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'message',
                    'data',
                ]);

        $data = $apiResponse->data;
        $this->assertTrue(is_object($data) && property_exists($data, 'current_page'));

        $this->blockAccess($user->id, 'discussions');

        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => 'Bearer ' . $token,
        ])->json('get', '/api/v1/discussions/my');

        $apiResponse = $response->baseResponse->getData();

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'message',
                    'data',
                ]);

        $data = $apiResponse->data;
        $this->assertTrue(!(is_object($data) && property_exists($data, 'current_page')));
    }

    public function testGetDiscussion() {
        $tokenData = $this->getUserTokenData();
        $user = $tokenData['user'];
        $token = $tokenData['token'];

        $this->unBlockAccess($user->id, 'discussions');

        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => 'Bearer ' . $token,
        ])->json('get', '/api/v1/discussions/detail/1');

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'message',
                    'data',
                ]);

        $this->blockAccess($user->id, 'discussions');

        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => 'Bearer ' . $token,
        ])->json('get', '/api/v1/discussions/detail/1');

        $apiResponse = $response->baseResponse->getData();

        $response->assertStatus(400)
                ->assertJsonStructure([
                    'message',
                    'data',
                ]);

        $message = $apiResponse->message;
        $this->assertTrue($message == 'Your access is blocked');
    }

    public function testUpdateDiscussion() {
        $tokenData = $this->getUserTokenData();
        $user = $tokenData['user'];
        $token = $tokenData['token'];

        $this->unBlockAccess($user->id, 'discussions');

        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => 'Bearer ' . $token,
        ])->json('put', '/api/v1/discussions/1', [
            'title' => 'Discussion Title',
            'description' => 'Discussion Description'
        ]);

        // $apiResponse = $response->baseResponse->getData();

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'message',
                    'data',
                ]);

        $this->blockAccess($user->id, 'discussions');

        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => 'Bearer ' . $token,
        ])->json('put', '/api/v1/discussions/1', [
            'title' => 'Discussion Title',
            'description' => 'Discussion Description'
        ]);

        $apiResponse = $response->baseResponse->getData();

        $response->assertStatus(400)
                ->assertJsonStructure([
                    'message',
                    'data',
                ]);

        $message = $apiResponse->message;
        $this->assertTrue($message == 'Your access is blocked');
    }

    public function testPostDiscussion() {
        $tokenData = $this->getUserTokenData();
        $user = $tokenData['user'];
        $token = $tokenData['token'];

        $this->unBlockAccess($user->id, 'discussions');

        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => 'Bearer ' . $token,
        ])->json('post', '/api/v1/discussions/new', [
            'title' => 'Discussion Title',
            'description' => 'Discussion Description',
            'is_draft' => 0
        ]);

        // $apiResponse = $response->baseResponse->getData();

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'message',
                    'data',
                ]);

        $this->blockAccess($user->id, 'discussions');

        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => 'Bearer ' . $token,
        ])->json('put', '/api/v1/discussions/new', [
            'title' => 'Discussion Title',
            'description' => 'Discussion Description',
            'is_draft' => 0
        ]);

        $apiResponse = $response->baseResponse->getData();

        $response->assertStatus(400)
                ->assertJsonStructure([
                    'message',
                    'data',
                ]);

        $message = $apiResponse->message;
        $this->assertTrue($message == 'Your access is blocked');
    }

    public function testDeleteDraftDiscussions() {
        $tokenData = $this->getUserTokenData();
        $user = $tokenData['user'];
        $token = $tokenData['token'];

        $this->unBlockAccess($user->id, 'discussions');

        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => 'Bearer ' . $token,
        ])->json('delete', '/api/v1/discussions/1/draft');

        $apiResponse = $response->baseResponse->getData();

        $response->assertStatus(400)
                ->assertJsonStructure([
                    'message',
                    'data',
                ]);

        $message = $apiResponse->message;
        $this->assertTrue($message == 'Can not delete draft');

        $this->blockAccess($user->id, 'discussions');

        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => 'Bearer ' . $token,
        ])->json('delete', '/api/v1/discussions/1/draft');

        $apiResponse = $response->baseResponse->getData();

        $response->assertStatus(400)
                ->assertJsonStructure([
                    'message',
                    'data',
                ]);

        $message = $apiResponse->message;
        $this->assertTrue($message == 'Your access is blocked');
    }

    public function testPublishDraftDiscussion() {
        $tokenData = $this->getUserTokenData();
        $user = $tokenData['user'];
        $token = $tokenData['token'];

        $this->unBlockAccess($user->id, 'discussions');

        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => 'Bearer ' . $token,
        ])->json('post', '/api/v1/discussions/1/publish');

        // $apiResponse = $response->baseResponse->getData();

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'message',
                    'data',
                ]);

        $this->blockAccess($user->id, 'discussions');

        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => 'Bearer ' . $token,
        ])->json('post', '/api/v1/discussions/1/publish');

        $apiResponse = $response->baseResponse->getData();

        $response->assertStatus(400)
                ->assertJsonStructure([
                    'message',
                    'data',
                ]);

        $message = $apiResponse->message;
        $this->assertTrue($message == 'Your access is blocked');
    }

    public function testCreateComment() {
        $tokenData = $this->getUserTokenData();
        $user = $tokenData['user'];
        $token = $tokenData['token'];

        $this->unBlockAccess($user->id, 'discussions');

        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => 'Bearer ' . $token,
        ])->json('post', '/api/v1/discussions/1/comment', [
            'description' => 'Comment Description'
        ]);

        $apiResponse = $response->baseResponse->getData();

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'message',
                    'data',
                ]);

        $data = $apiResponse->data;
        $this->assertTrue(is_object($data) && property_exists($data, 'comment'));

        $this->blockAccess($user->id, 'discussions');

        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => 'Bearer ' . $token,
        ])->json('post', '/api/v1/discussions/1/comment', [
            'description' => 'Comment Description'
        ]);

        $apiResponse = $response->baseResponse->getData();

        $response->assertStatus(400)
                ->assertJsonStructure([
                    'message',
                    'data',
                ]);

        $message = $apiResponse->message;
        $this->assertTrue($message == 'Your access is blocked');
    }

    public function testUpdateComment() {
        $tokenData = $this->getUserTokenData();
        $user = $tokenData['user'];
        $token = $tokenData['token'];

        $this->unBlockAccess($user->id, 'discussions');

        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => 'Bearer ' . $token,
        ])->json('put', '/api/v1/discussions/1/comment', [
            'description' => 'Comment Description',
            'comment_id' => 1,
        ]);

        $apiResponse = $response->baseResponse->getData();

        $response->assertStatus(400)
                ->assertJsonStructure([
                    'message',
                    'data',
                ]);

        $message = $apiResponse->message;
        $this->assertTrue($message == 'Invalid discussion id');

        $this->blockAccess($user->id, 'discussions');

        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => 'Bearer ' . $token,
        ])->json('put', '/api/v1/discussions/1/comment', [
            'description' => 'Comment Description',
            'comment_id' => 1,
        ]);

        $apiResponse = $response->baseResponse->getData();

        $response->assertStatus(400)
                ->assertJsonStructure([
                    'message',
                    'data',
                ]);

        $message = $apiResponse->message;
        $this->assertTrue($message == 'Your access is blocked');
    }

    public function testSetVote() {
        $tokenData = $this->getUserTokenData();
        $user = $tokenData['user'];
        $token = $tokenData['token'];

        $this->unBlockAccess($user->id, 'discussions');

        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => 'Bearer ' . $token,
        ])->json('post', '/api/v1/discussions/1/vote', [
            'is_like' => true,
        ]);

        $apiResponse = $response->baseResponse->getData();

        $response->assertStatus(400)
                ->assertJsonStructure([
                    'message',
                    'data',
                ]);

        $message = $apiResponse->message;
        $this->assertTrue($message == 'Invalid discussion id');

        $this->blockAccess($user->id, 'discussions');

        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => 'Bearer ' . $token,
        ])->json('post', '/api/v1/discussions/1/vote', [
            'is_like' => true,
        ]);

        $apiResponse = $response->baseResponse->getData();

        $response->assertStatus(400)
                ->assertJsonStructure([
                    'message',
                    'data',
                ]);

        $message = $apiResponse->message;
        $this->assertTrue($message == 'Your access is blocked');
    }

    public function testSetPin() {
        $tokenData = $this->getUserTokenData();
        $user = $tokenData['user'];
        $token = $tokenData['token'];

        $this->unBlockAccess($user->id, 'discussions');

        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => 'Bearer ' . $token,
        ])->json('post', '/api/v1/discussions/1/pin');

        $apiResponse = $response->baseResponse->getData();

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'message',
                    'data',
                ]);

        $this->blockAccess($user->id, 'discussions');

        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => 'Bearer ' . $token,
        ])->json('post', '/api/v1/discussions/1/pin');

        $apiResponse = $response->baseResponse->getData();

        $response->assertStatus(400)
                ->assertJsonStructure([
                    'message',
                    'data',
                ]);

        $message = $apiResponse->message;
        $this->assertTrue($message == 'Your access is blocked');
    }

    public function testRemoveNewMark() {
        $tokenData = $this->getUserTokenData();
        $user = $tokenData['user'];
        $token = $tokenData['token'];

        $this->unBlockAccess($user->id, 'discussions');

        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => 'Bearer ' . $token,
        ])->json('delete', '/api/v1/discussions/1/new');

        $apiResponse = $response->baseResponse->getData();

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'message',
                    'data',
                ]);

        $this->blockAccess($user->id, 'discussions');

        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => 'Bearer ' . $token,
        ])->json('delete', '/api/v1/discussions/1/new');

        $apiResponse = $response->baseResponse->getData();

        $response->assertStatus(400)
                ->assertJsonStructure([
                    'message',
                    'data',
                ]);

        $message = $apiResponse->message;
        $this->assertTrue($message == 'Your access is blocked');
    }
}