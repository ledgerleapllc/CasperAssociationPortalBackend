<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

class AddDataAdminUser extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $user = User::where(['email' => 'ledgerleapllc@gmail.com'])->first();
        if (!$user) {
            $user = new User;
            $user->first_name = 'Ledger';
            $user->last_name = 'Leap';
            $user->email = 'ledgerleapllc@gmail.com';
            $user->password = Hash::make('Wireless123!');
            $user->email_verified_at = now();
            $user->type = 'active';
            $user->role = 'admin';
            $user->save();
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        //
    }
}
