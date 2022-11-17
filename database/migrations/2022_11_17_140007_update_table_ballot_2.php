<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class UpdateTableBallot2 extends Migration {
    public function up() {
        Schema::table('ballot', function (Blueprint $table) {
        	$table->string('timezone')->nullable();
        });
    }
	public function down() {
        //
    }
}