<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateProfileTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('profile', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users');
            $table->string('first_name', 255)->nullable();
            $table->string('last_name', 255)->nullable();
            $table->string('company')->nullable();
            $table->date('dob')->nullable();
            $table->string('country_citizenship', 255)->nullable();
            $table->string('country_residence', 255)->nullable();
            $table->string('address', 255)->nullable();
            $table->string('city', 255)->nullable();
            $table->string('zip', 255)->nullable();
            $table->tinyInteger('type_owner_node')->nullable();
            $table->string('status', 255)->nullable();
            $table->string('type')->nullable();
            $table->string('entity_name')->nullable();
            $table->string('entity_type')->nullable();
            $table->string('entity_registration_number')->nullable();
            $table->string('entity_registration_country')->nullable();
            $table->string('vat_number')->nullable();
            $table->string('page_is_representative')->nullable();
            $table->integer('page_number')->default(0)->nullable();
            $table->timestamp('document_verified_at')->nullable();
            $table->string('extra_status')->nullable();
            $table->string('casper_association_kyc_hash')->nullable();
            $table->string('blockchain_name')->nullable();
            $table->text('blockchain_desc')->nullable();
            $table->string('revoke_reason', 255)->nullable();
            $table->text('reactivation_reason')->nullable();
            $table->boolean('reactivation_requested')->nullable();
            $table->timestamp('reactivation_requested_at')->nullable();
            $table->timestamp('revoke_at')->nullable();
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
        Schema::dropIfExists('profile');
    }
}
