<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

use App\Models\Setting;

class AddDataToSettings extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
    	$names = [
            'quorum_rate_ballot' => '50',
            'peers' => '0',
            'eras_look_back' => '3500',
            'eras_to_be_stable' => '1',
            'voting_eras_to_vote' => '1',
            'voting_eras_since_redmark' => '1',
            'uptime_calc_size' => '3500',
            'uptime_warning' => '75',
            'uptime_probation' => '70',
            'uptime_correction_unit' => 'Weeks',
            'uptime_correction_value' => '1',
            'redmarks_revoke' => '500',
            'redmarks_revoke_calc_size' => '3500',
            'responsiveness_warning' => '1',
            'responsiveness_probation' => '1'
        ];

        foreach ($names as $name => $value) {
            $setting = Setting::where('name', $name)->first();
            if (!$setting) {
                $setting = new Setting;
                $setting->name = $name;
                $setting->value = $value;
                $setting->save();
            }
        }
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
