<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class UdpateTableBallot4 extends Migration {
    public function up() {
        Schema::table('ballot', function (Blueprint $table) {
        	$table->boolean('reminder_24_sent')->default(false);
        });
    }
    public function down() {
        //
    }
}