<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMetricTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('metric', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users');
            $table->float('uptime')->nullable();
            $table->integer('block_height_average')->nullable();
            $table->float('update_responsiveness')->nullable();
            $table->float('peers')->nullable();
            $table->timestamp('uptime_time_start')->nullable();
            $table->timestamp('block_height_average_time_start')->nullable();
            $table->timestamp('update_responsiveness_time_start')->nullable();
            $table->timestamp('uptime_time_end')->nullable();
            $table->timestamp('block_height_average_time_end')->nullable();
            $table->timestamp('update_responsiveness_time_end')->nullable();
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
        Schema::dropIfExists('metric');
    }
}
