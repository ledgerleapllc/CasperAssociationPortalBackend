<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class UpdateMbs extends Migration
{
    public function up()
    {
        Schema::table('mbs', function (Blueprint $table) {
        	$table->float('mbs', 20, 2)->change();
        });
    }

    public function down()
    {
        //
    }
}
