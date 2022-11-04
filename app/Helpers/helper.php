<?php

use App\Models\Setting;

if (!function_exists('generateString')) {

    function generateString($strength = 16)
    {
        $seed = str_split(
            'ABCDEFGHJKLMNPQRSTUVWXYZ'.
            '2345678923456789'
        );
        // dont use 0, 1, o, O, l, I
        shuffle($seed);
        $hash = '';

        foreach(array_rand($seed, $strength) as $k) {
            $hash .= $seed[$k];
        }

        return $hash;
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