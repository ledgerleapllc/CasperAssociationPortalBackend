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
        
        DB::statement("ALTER TABLE profile modify first_name varchar(255) NULL");
        DB::statement("ALTER TABLE profile modify last_name varchar(255) NULL");
        DB::statement("ALTER TABLE profile modify country_citizenship varchar(255) NULL");
        DB::statement("ALTER TABLE profile modify country_residence varchar(255) NULL");
        DB::statement("ALTER TABLE profile modify address varchar(255) NULL");
        DB::statement("ALTER TABLE profile modify city varchar(255) NULL");
        DB::statement("ALTER TABLE profile modify zip varchar(255) NULL");

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
