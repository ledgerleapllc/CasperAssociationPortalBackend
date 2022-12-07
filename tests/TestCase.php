<?php

namespace Tests;

use Carbon\Carbon;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Foundation\Testing\DatabaseMigrations;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Date;

use App\Models\User;
use App\Models\UserAddress;
use App\Models\Profile;
use App\Models\VerifyUser;
use App\Models\PagePermission;
use App\Models\AllNodeData2;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication, DatabaseMigrations;
    
    public function setUp(): void
    {
        parent::setUp();
        Artisan::call('config:clear');
        Artisan::call('cache:clear');
        Artisan::call('passport:install');

        $key = '011117189c666f81c5160cd610ee383dc9b2d0361f004934754d39752eedc64957';
        $eraId = 5537;

        $record = AllNodeData2::where('public_key', $key)
                            ->where('era_id', $eraId)
                            ->first();
        if (!$record) {
            $record = new AllNodeData2;
            $record->public_key = $key;
            $record->era_id = $eraId;
            $record->uptime = 99.77;
            $record->current_era_weight = 320860571;
            $record->next_era_weight = 320868080;
            $record->in_current_era = 1;
            $record->in_next_era = 1;
            $record->in_auction = 1;
            $record->bid_delegators_count = 173;
            $record->bid_delegation_rate = 10;
            $record->bid_inactive = 0;
            $record->bid_self_staked_amount = 2281468;
            $record->bid_delegators_staked_amount = 318586612;
            $record->bid_total_staked_amount = 320868080;
            $record->port8888_peers = 0;
            $record->port8888_era_id = null;
            $record->port8888_block_height = null;
            $record->port8888_build_version = null;
            $record->port8888_next_upgrade = null;
            $record->save();
        }
    }
    
    public function addAdmin() {
        $user = User::where(['email' => 'ledgerleapllcadmin@gmail.com'])->first();
        if (!$user) {
            $user = new User;
            $user->first_name = 'Ledger';
            $user->last_name = 'Leap';
            $user->email = 'ledgerleapllcadmin@gmail.com';
            $user->password = Hash::make('Ledgerleapllcadmin111@');
            $user->email_verified_at = Carbon::now('UTC');
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

    public function addUser() {
        $node = '011117189c666f81c5160cd610ee383dc9b2d0361f004934754d39752eedc64957';

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
            $user->email_verified_at = Carbon::now('UTC');
            $user->signature_request_id = 'TestSignatureRequestId';
            $user->role = 'member';
            $user->letter_file = 'LetterFileLink';
            $user->public_address_node = $node;
            $user->save();
        }

        $userAddress = UserAddress::where('user_id', $user->id)
                                    ->where('public_address_node', $node)
                                    ->first();
        if (!$userAddress) {
            $userAddress = new UserAddress;
            $userAddress->user_id = $user->id;
            $userAddress->public_address_node = $node;
            $userAddress->save();
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

    public function getUserTokenData() {
        $user = $this->addUser();

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

    public function getUserToken() {
        $this->addUser();

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