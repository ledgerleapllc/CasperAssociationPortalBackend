<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddColumnUserTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('users', function ($table) {
            $table->string('signature_request_id')->nullable();
            $table->string('public_address_node')->nullable();
            $table->timestamp('node_verified_at')->nullable();
            $table->string('message_content')->nullable();
            $table->timestamp('kyc_verified_at')->nullable();
            $table->string('hellosign_form')->nullable();
        });
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
