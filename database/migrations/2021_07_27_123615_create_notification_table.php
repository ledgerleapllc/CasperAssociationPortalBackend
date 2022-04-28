<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateNotificationTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('notification', function (Blueprint $table) {
            $table->id();
            $table->string('type');
            $table->string('title');
            $table->text('body')->nullable();
            $table->tinyInteger('show_login')->nullable()->default(0);
            $table->tinyInteger('allow_dismiss_btn')->nullable()->default(0);
            $table->tinyInteger('high_priority')->nullable()->default(0);
            $table->tinyInteger('have_action')->nullable()->default(0);
            $table->string('action_link')->nullable();
            $table->string('btn_text')->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->tinyInteger('setting')->nullable()->default(0);
            $table->integer('total_views')->nullable()->default(0);
            $table->string('status')->nullable();
            $table->string('visibility')->nullable();
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
        Schema::dropIfExists('perk');
    }
}
