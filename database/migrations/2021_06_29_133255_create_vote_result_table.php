<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateVoteResultTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('vote_result', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ballot_id')->constrained('ballot');
            $table->foreignId('vote_id')->constrained('vote');
            $table->foreignId('user_id')->constrained('users');
            $table->enum('type', ['for', 'against'])->default('for');
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
        Schema::dropIfExists('vote_result');
    }
}
