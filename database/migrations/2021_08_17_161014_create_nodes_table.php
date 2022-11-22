<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateNodesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('nodes', function (Blueprint $table) {
            $table->id();
            $table->string('node_address');
            $table->float('uptime')->nullable();
            $table->integer('block_height')->nullable();
            $table->float('update_responsiveness')->nullable();
            $table->integer('peers')->nullable();
            $table->string('block_hash')->nullable();
            $table->string('decoded_peers')->nullable();
            $table->integer('era_id')->nullable();
            $table->string('activation_point')->nullable();
            $table->string('protocol_version')->nullable();
            $table->float('weight', 30, 2)->nullable();
            $table->boolean('refreshed')->default(0);
            $table->timestamp('timestamp')->nullable();
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
        Schema::dropIfExists('nodes');
    }
}
