<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class UpdateUserAddresses6Table extends Migration
{
    public function up()
    {
        Schema::table('user_addresses', function ($table) {
            $table->timestamp('probation_start')->nullable();
            $table->timestamp('probation_end')->nullable();
        });
    }

    public function down()
    {
        //
    }
}
