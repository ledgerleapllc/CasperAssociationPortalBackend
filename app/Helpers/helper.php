<?php

use App\Models\Setting;

if (!function_exists('generateString')) {

    function generateString($strength = 16)
    {
        $permitted_chars = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $input_length = strlen($permitted_chars);
        $random_string = '';
        for ($i = 0; $i < $strength; $i++) {
            $random_character = $permitted_chars[mt_rand(0, $input_length - 1)];
            $random_string .= $random_character;
        }
        return $random_string;
    }
}

function total_ram_cpu_usage()
{
    //RAM usage
    $free = shell_exec('free');
    $free = (string) trim($free);
    $free_arr = explode("\n", $free);
    $mem = explode(" ", $free_arr[1]);
    $mem = array_filter($mem);
    $mem = array_merge($mem);
    $usedmem = $mem[2];
    $usedmemInGB = number_format($usedmem / 1048576, 2) . ' GB';
    $memory1 = $mem[2] / $mem[1] * 100;
    $memory = round($memory1) . '%';
    $fh = fopen('/proc/meminfo', 'r');
    $mem = 0;
    while ($line = fgets($fh)) {
        $pieces = array();
        if (preg_match('/^MemTotal:\s+(\d+)\skB$/', $line, $pieces)) {
            $mem = $pieces[1];
            break;
        }
    }
    fclose($fh);
    $totalram = number_format($mem / 1048576, 2) . ' GB';

    //cpu usage
    $cpu_load = sys_getloadavg();
    $load = $cpu_load[0] . '% / 100%';
    return [
        'memory' => $memory,
        'totalram' => $totalram,
        'usedmemInGB' => $usedmemInGB,
        'load' => $load,
    ];
}

// Get Settings
function getSettings()
{
    // Get Settings
    $settings = [];
    $items = Setting::get();
    if ($items) {
        foreach ($items as $item) {
            $settings[$item->name] = $item->value;
        }
    }
    return $settings;
}
