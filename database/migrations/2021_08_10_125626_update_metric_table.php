<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class UpdateMetricTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('metric', function ($table) {
            $table->timestamp('uptime_time_start')->nullable();
            $table->timestamp('block_height_average_time_start')->nullable();
            $table->timestamp('update_responsiveness_time_start')->nullable();
            $table->timestamp('uptime_time_end')->nullable();
            $table->timestamp('block_height_average_time_end')->nullable();
            $table->timestamp('update_responsiveness_time_end')->nullable();
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
