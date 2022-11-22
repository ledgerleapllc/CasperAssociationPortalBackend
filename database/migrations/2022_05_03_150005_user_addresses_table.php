<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class UserAddressesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('user_addresses', function (Blueprint $table) {
            $table->id();
            $table->integer('user_id');
            $table->string('public_address_node');
            $table->timestamp('node_verified_at')->nullable();
            $table->text('signed_file')->nullable();
            $table->tinyInteger('is_fail_node')->nullable()->default(0);
            $table->integer('rank')->nullable();
            $table->tinyInteger('pending_node')->nullable()->default(0);
            $table->float('validator_fee')->nullable()->default(0);
            $table->string('node_status')->nullable();
            $table->string('extra_status')->nullable();
            $table->timestamp('probation_start')->nullable();
            $table->timestamp('probation_end')->nullable();
            $table->timestamps();
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
