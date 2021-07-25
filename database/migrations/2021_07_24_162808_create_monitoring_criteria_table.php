<?php

use App\Models\MonitoringCriteria;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CreateMonitoringCriteriaTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('monitoring_criteria', function (Blueprint $table) {
            $table->id();
            $table->string('type')->nullable();
            $table->string('warning_level')->nullable();
            $table->string('probation_start')->nullable();
            $table->string('frame_calculate_unit')->nullable();
            $table->string('frame_calculate_value')->nullable();
            $table->string('given_to_correct_unit')->nullable();
            $table->string('given_to_correct_value')->nullable();
            $table->string('system_check_unit')->nullable();
            $table->string('system_check_value')->nullable();
            $table->timestamps();
        });
        $uptime = ['type'=>'uptime'];
        DB::table('monitoring_criteria')->insert($uptime);
        $blockHeight = ['type'=>'block-height'];
        DB::table('monitoring_criteria')->insert($blockHeight);
        $updateResponsiveness = ['type'=>'update-responsiveness'];
        DB::table('monitoring_criteria')->insert($updateResponsiveness);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('monitoring_criteria');
    }
}
