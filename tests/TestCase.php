<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Foundation\Testing\DatabaseMigrations;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Date;

use App\Models\User;

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

    public function addMember() {

    }
}
