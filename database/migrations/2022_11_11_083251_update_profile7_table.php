<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class UpdateProfile7Table extends Migration
{
    public function up()
    {
        Schema::table('profile', function ($table) {
            $table->timestamp('revoke_at')->nullable();
        });
    }

    public function down()
    {
        //
    }
}
