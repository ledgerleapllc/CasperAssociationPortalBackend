<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class UpdateProfile6Table extends Migration
{
    public function up()
    {
        Schema::table('profile', function ($table) {
            $table->text('reactivation_reason')->nullable();
            $table->boolean('reactivation_requested')->nullable();
            $table->timestamp('reactivation_requested_at')->nullable();
        });
    }

    public function down()
    {
        //
    }
}
