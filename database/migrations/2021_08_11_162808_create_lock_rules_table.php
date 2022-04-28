<?php

use App\Models\MonitoringCriteria;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CreateLockRulesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('lock_rules', function (Blueprint $table) {
            $table->id();
            $table->string('type')->nullable();
            $table->string('screen')->nullable();
            $table->boolean('is_lock')->nullable();
            $table->timestamps();
        });

        $arr = [['type'=>'kyc_not_verify', 'screen' => 'nodes', 'is_lock' => false, 'created_at' => now()],
            ['type'=>'kyc_not_verify', 'screen' => 'discussions', 'is_lock' => false, 'created_at' => now()],
            ['type'=>'kyc_not_verify', 'screen' => 'votes', 'is_lock' => false, 'created_at' => now()],
            ['type'=>'kyc_not_verify', 'screen' => 'perks', 'is_lock' => false, 'created_at' => now()],
            ['type'=>'status_is_poor', 'screen' => 'nodes', 'is_lock' => false, 'created_at' => now()],
            ['type'=>'status_is_poor', 'screen' => 'discussions', 'is_lock' => false, 'created_at' => now()],
            ['type'=>'status_is_poor', 'screen' => 'votes', 'is_lock' => false, 'created_at' => now()],
            ['type'=>'status_is_poor', 'screen' => 'perks', 'is_lock' => false, 'created_at' => now()]];

        foreach ($arr as $item) {
            DB::table('lock_rules')->insert($item);
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('lock_rules');
    }
}
