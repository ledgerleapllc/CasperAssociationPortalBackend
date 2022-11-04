<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAllNodeData2 extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('all_node_data2', function (Blueprint $table) {
            // Key fields
            $table->id();
            $table->string('public_key');
            $table->integer('era_id');

            // Make uptime
            $table->float('uptime')->nullable();

            // From auction object
            $table->bigInteger('current_era_weight')->default(0);
            $table->bigInteger('next_era_weight')->default(0);

            // Found in auction object
            $table->tinyInteger('in_current_era')->default(0);
            $table->tinyInteger('in_next_era')->default(0);
            $table->tinyInteger('in_auction')->default(0);

            // Auction bid details
            $table->integer('bid_delegators_count')->nullable();
            $table->bigInteger('bid_delegation_rate')->nullable();
            $table->tinyInteger('bid_inactive')->default(0);
            $table->bigInteger('bid_self_staked_amount')->default(0);
            $table->bigInteger('bid_delegators_staked_amount')->default(0);
            $table->bigInteger('bid_total_staked_amount')->default(0);

            // the following fields come from port 8888 when/if discovered
            $table->integer('port8888_peers')->nullable();
            $table->integer('port8888_era_id')->nullable();
            $table->integer('port8888_block_height')->nullable();
            $table->string('port8888_build_version')->nullable();
            $table->string('port8888_next_upgrade')->nullable();

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
        Schema::dropIfExists('all_node_data2');
    }
}
