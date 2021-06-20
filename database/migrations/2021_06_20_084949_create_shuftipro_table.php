<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateShuftiproTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('shuftipro', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users');
            $table->string('reference_id');
            $table->boolean('is_successful')->default(0);
            $table->text('data')->nullable();
            $table->string('document_proof')->nullable();
            $table->string('address_proof')->nullable();
            $table->boolean('document_result')->default(0);
            $table->boolean('address_result')->default(0);
            $table->boolean('background_checks_result')->default(0);
            $table->enum('status', ['pending', 'denied', 'approved'])->default('pending');
            $table->boolean('reviewed')->default(0);
            $table->timestampTz('manual_approved_at', 0)->nullable();
            $table->string('manual_reviewer')->nullable();
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
        Schema::dropIfExists('shuftipro');
    }
}
