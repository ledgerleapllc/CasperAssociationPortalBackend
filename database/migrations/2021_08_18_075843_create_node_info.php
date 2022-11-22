<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateNodeInfo extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('node_info', function (Blueprint $table) {
            $table->id();
            $table->string('node_address');
            $table->float('uptime')->nullable();
            $table->integer('block_height_average')->nullable();
            $table->float('update_responsiveness')->nullable();
            $table->integer('peers')->nullable();
            $table->integer('block_height')->nullable();
            $table->integer('delegators_count')->nullable();
            $table->float('delegation_rate')->nullable();
            $table->bigInteger('self_staked_amount')->nullable();
            $table->bigInteger('total_staked_amount')->nullable();
            $table->timestamp('uptime_time_start')->nullable();
            $table->timestamp('block_height_average_time_start')->nullable();
            $table->timestamp('update_responsiveness_time_start')->nullable();
            $table->timestamp('uptime_time_end')->nullable();
            $table->timestamp('block_height_average_time_end')->nullable();
            $table->timestamp('update_responsiveness_time_end')->nullable();
            $table->float('daily_earning', 30, 8)->nullable();
            $table->float('total_earning', 30, 8)->nullable();
            $table->tinyInteger('is_open_port')->nullable()->default(0);
            $table->integer('mbs')->nullable()->default(0);
            $table->boolean('refreshed')->default(0);
            $table->tinyInteger('inactive')->nullable()->default(0);
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
        Schema::dropIfExists('node_info');
    }
}
