<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\Request;

class InstallController extends Controller
{
    public function install()
    {
        /* Setting */
        $names = [
            'quorum_rate_ballot' => '50',    
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
        echo "Setting created<br/>";
    }
}
