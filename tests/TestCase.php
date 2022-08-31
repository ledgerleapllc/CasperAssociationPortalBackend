<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Foundation\Testing\DatabaseMigrations;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Date;

use App\Models\User;
use App\Models\Profile;
use App\Models\VerifyUser;
use App\Models\PagePermission;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication, DatabaseMigrations;
    
    public function setUp(): void
    {
        parent::setUp();
        Artisan::call('config:clear');
        Artisan::call('cache:clear');
        Artisan::call('passport:install');
    }
    
    public function addAdmin() {
        $user = User::where(['email' => 'ledgerleapllcadmin@gmail.com'])->first();
        if (!$user) {
            $user = new User;
            $user->first_name = 'Ledger';
            $user->last_name = 'Leap';
            $user->email = 'ledgerleapllcadmin@gmail.com';
            $user->password = Hash::make('Ledgerleapllcadmin111@');
            $user->email_verified_at = now();
            $user->type = 'active';
            $user->role = 'admin';
            $user->save();
        }
    }   

    public function getAdminToken() {
        $this->addAdmin();

        $params = [
            'email' => 'ledgerleapllcadmin@gmail.com',
            'password' => 'Ledgerleapllcadmin111@',
        ];

        $response = $this->withHeaders([
            'Accept' => 'application/json',
        ])->json('post', '/api/v1/auth/login', $params);
        
        $apiResponse = $response->baseResponse->getData();

        if ($apiResponse && isset($apiResponse->data) && isset($apiResponse->data->access_token))
            return $apiResponse->data->access_token;
        return null;
    }

    public function addUser($node = null) {
        $first_name = 'Test';
        $last_name = 'Individual';
        $email = 'testindividual@gmail.com';
        $password = 'TestIndividual111@';
        $pseudonym = 'testindividual';
        $telegram = '@testindividual';
        
        $user = User::where('email', $email)->first();
        if (!$user) {
            $user = new User;
            $user->first_name = $first_name;
            $user->last_name = $last_name;
            $user->email = $email;
            $user->password = bcrypt($password);
            $user->pseudonym = $pseudonym;
            $user->telegram = $telegram;
            $user->type = User::TYPE_INDIVIDUAL;
            $user->email_verified_at = now();
            $user->signature_request_id = 'TestSignatureRequestId';
            $user->role = 'member';
            $user->letter_file = 'LetterFileLink';
            if ($node) $user->public_address_node = $node;
            $user->save();
        }
        return $user;
    }

    public function blockAccess($userId, $name) {
        $permission = PagePermission::where('user_id', $userId)->where('name', $name)->first();
        if (!$permission) $permission = new PagePermission;
        $permission->user_id = $userId;
        $permission->name = $name;
        $permission->is_permission = 0;
        $permission->save();
    }

    public function unBlockAccess($userId, $name) {
        $permission = PagePermission::where('user_id', $userId)->where('name', $name)->first();
        if (!$permission) $permission = new PagePermission;
        $permission->user_id = $userId;
        $permission->name = $name;
        $permission->is_permission = 1;
        $permission->save();
    }

    public function getUserTokenData($node = null) {
        $user = $this->addUser($node);

        $params = [
            'email' => 'testindividual@gmail.com',
            'password' => 'TestIndividual111@',
        ];

        $response = $this->withHeaders([
            'Accept' => 'application/json',
        ])->json('post', '/api/v1/auth/login', $params);

        $apiResponse = $response->baseResponse->getData();

        if ($apiResponse && isset($apiResponse->data) && isset($apiResponse->data->access_token))
            return ['user' => $user, 'token' => $apiResponse->data->access_token];
        return null;
    }

    public function getUserToken($node = null) {
        $this->addUser($node);

        $params = [
            'email' => 'testindividual@gmail.com',
            'password' => 'TestIndividual111@',
        ];

        $response = $this->withHeaders([
            'Accept' => 'application/json',
        ])->json('post', '/api/v1/auth/login', $params);

        $apiResponse = $response->baseResponse->getData();

        if ($apiResponse && isset($apiResponse->data) && isset($apiResponse->data->access_token))
            return $apiResponse->data->access_token;
        return null;
    }
}