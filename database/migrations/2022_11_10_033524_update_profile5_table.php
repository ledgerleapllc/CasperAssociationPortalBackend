<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class UpdateProfile5Table extends Migration
{
    public function up()
    {
        Schema::table('profile', function ($table) {
            $table->string('revoke_reason', 255)->nullable();
        });
    }

    public function down()
    {
        //
    }
}
