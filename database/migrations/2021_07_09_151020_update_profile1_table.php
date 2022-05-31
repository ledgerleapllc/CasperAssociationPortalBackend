<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class UpdateProfile1Table extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('profile', function ($table) {
            $table->string('entity_name')->nullable();
            $table->string('entity_type')->nullable();
            $table->string('entity_registration_number')->nullable();
            $table->string('entity_registration_country')->nullable();
            $table->string('vat_number')->nullable();
            $table->string('page_is_representative')->nullable();
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
