<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateReinstatementHistoryTable extends Migration
{
    public function up()
    {
        Schema::create('reinstatement_history', function (Blueprint $table) {
            $table->id();
            $table->integer('user_id');
            $table->timestamp('revoke_at')->nullable();
            $table->string('revoke_reason', 255)->nullable();
            $table->text('reactivation_reason')->nullable();
            $table->timestamp('reactivation_requested_at')->nullable();
            $table->boolean('decision')->nullable();
            $table->timestamp('decision_at')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('reinstatement_history');
    }
}
