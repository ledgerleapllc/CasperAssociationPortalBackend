<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class UpdateTableBallot3 extends Migration {
    public function up()
    {
        Schema::table('ballot', function (Blueprint $table) {
        	$table->timestamp('time_begin')->nullable();
        });
    }
	public function down() {
        //
    }
}
