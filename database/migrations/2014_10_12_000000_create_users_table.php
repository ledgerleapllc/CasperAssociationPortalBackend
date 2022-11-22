<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateUsersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('email')->unique();
            $table->string('pseudonym')->nullable();
            $table->string('telegram')->nullable();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->string('type');
            $table->string('entity_name')->nullable();
            $table->string('entity_type')->nullable();
            $table->string('entity_register_number')->nullable();
            $table->string('entity_register_country')->nullable();
            $table->string('entity_tax')->nullable();
            $table->timestamp('last_login_at')->nullable();
            $table->string('role')->default('member');
            $table->string('signed_file')->nullable();
            $table->string('member_status')->nullable();
            $table->string('signature_request_id')->nullable();
            $table->string('public_address_node')->nullable();
            $table->timestamp('node_verified_at')->nullable();
            $table->string('message_content')->nullable();
            $table->timestamp('kyc_verified_at')->nullable();
            $table->string('hellosign_form')->nullable();
            $table->string('letter_file')->nullable();
            $table->string('invite_link')->nullable();
            $table->string('reset_link')->nullable();
            $table->string('permissions')->nullable();
            $table->timestamp('letter_verified_at')->nullable();
            $table->timestamp('letter_rejected_at')->nullable();
            $table->tinyInteger('banned')->default(0);
            $table->string('avatar')->nullable();
            $table->timestamp('approve_at')->nullable();
            $table->integer('average_peers')->nullable()->default(0);
            $table->float('validator_fee')->nullable()->default(0);
            $table->bigInteger('cspr_delegated')->nullable()->default(0);
            $table->bigInteger('cspr_self_staked')->nullable()->default(0);
            $table->string('new_email')->nullable();
            $table->string('username')->nullable();
            $table->tinyInteger('twoFA_login')->nullable()->default(0);
            $table->tinyInteger('twoFA_login_active')->nullable()->default(0);
            $table->string('last_login_ip_address')->nullable();
            $table->string('node_status')->nullable();
            $table->tinyInteger('is_fail_node')->nullable()->default(0);
            $table->integer('rank')->nullable();
            $table->tinyInteger('membership_agreement')->nullable()->default(0);
            $table->tinyInteger('reset_kyc')->nullable()->default(0);
            $table->boolean('refreshed')->default(0);
            $table->boolean('pending_node')->default(0);
            $table->boolean('has_address')->default(0);
            $table->boolean('has_verified_address')->default(0);
            $table->boolean('kyc_bypass_approval')->default(0);
            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('users');
    }
}
