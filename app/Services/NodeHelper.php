<?php

namespace App\Services;

use App\Models\KeyPeer;
use App\Models\Node;
use App\Models\NodeInfo;
use App\Models\User;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Exception\ServerException;
use GuzzleHttp\Exception\BadResponseException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;


class NodeHelper
{
    public function __construct()
    {
        $this->SEENA_API_KEY = '48454d73487700a29f50719d69be47d4';
    }
    public function getValidatorStanding()
    {
        $response = Http::withHeaders([
            'Authorization' => "token $this->SEENA_API_KEY",
        ])->withOptions([
            'verify' => false,
        ])->get('https://seena.ledgerleap.com/validator-standing');
        return $response->json();
    }

    public function getTotalRewards($validatorId)
    {
        $response = Http::withHeaders([
            'Authorization' => "token $this->SEENA_API_KEY",
        ])->withOptions([
            'verify' => false,
        ])->get("https://api.CSPR.live/validators/$validatorId/total-rewards");
        return $response->json();
    }

    public function updateStats()
    {
        $data = $this->getValidatorStanding();
        $validator_standing = isset($data['validator_standing']) ? $data['validator_standing']  : null;
        $mbs =  isset($data['MBS']) ? $data['MBS']  : 0;
        $users = User::whereNotNull('public_address_node')->get();
        if ($validator_standing) {
            foreach ($users as $user) {
                $validatorid = $user->public_address_node;
                if (isset($validator_standing[$validatorid])) {
                    $info = $validator_standing[$validatorid];
                    $totalRewards = $this->getTotalRewards($validatorid);
                    $build_version = $info['build_version'] ?? null;
                    if ($build_version) {
                        $build_version = explode('-', $build_version);
                        $build_version = $build_version[0];
                    }
                    $is_open_port =  isset($info['uptime']) && isset($info['update_responsiveness']) ? 1 : 0 ;
                    NodeInfo::updateOrCreate(
                        ['node_address' => $validatorid],
                        [
                            'delegators_count' => $info['delegator_count'] ?? 0,
                            'total_staked_amount' => $info['total_stake'],
                            'delegation_rate' => $info['delegation_rate'],
                            'daily_earning' => $info['daily_earnings'] ?? 0,
                            'self_staked_amount' => $info['self_stake'] ?? 0,
                            'total_earning' => isset($totalRewards['data']) &&  $totalRewards['data']  > 0 ? $totalRewards['data'] / 1000000000 : 0,
                            'is_open_port' => $is_open_port,
                            'mbs' => $mbs,
                        ]
                    );

                    Node::create(
                        [
                            'node_address' => $validatorid,
                            'block_height' => $info['block_height'] ?? null,
                            'protocol_version' => $build_version,
                            'update_responsiveness' => isset($info['update_responsiveness']) ? $info['update_responsiveness'] * 100 : null,
                            'uptime' => isset($info['uptime']) ? $info['uptime'] : null,
                            'weight' => $info['daily_earnings'] ?? 0,
                        ]
                    );
                }
            }
        }
    }

    public function getValidatorRewards($validatorId, $range)
    {
        $response = Http::withHeaders([
            'Authorization' => "token $this->SEENA_API_KEY",
        ])->withOptions([
            'verify' => false,
        ])->get("https://seena.ledgerleap.com/validator-rewards?validator_id=$validatorId&range=$range");
        $data =  $response->json();
        if (isset($data['success']) && $data['success'] == false) {
            return [];
        }
        $result_day = [];
        $result_week = [];
        $result_month = [];
        $result_year = [];
        $result = collect();
        foreach ($data as $key => $value) {
            $result->push([
                'weight' => $value,
                'time' => Carbon::parse($key),
            ]);
        }
        if ($range == 'day') {
            $startDate = Carbon::now()->startOfDay()->subHours(2);
            $endDate = Carbon::now()->startOfDay();
            for ($i = 0; $i <= 12; $i++) {
                $min =  $result->whereBetween('time', [$startDate, $endDate])->min('weight');
                $max = $result->whereBetween('time', [$startDate, $endDate])->max('weight');
                $weight =  $max - $min;
                array_push($result_day, [
                    'weight' => $max - $min,
                    'item' => $i,
                ]);
                $startDate->addHours(2);
                $endDate->addHours(2);
            }
            return $result_day;
        }

        if ($range == 'week') {
            $startWeek = Carbon::now()->startOfWeek(Carbon::MONDAY);
            $endWeek =  Carbon::now()->startOfWeek(Carbon::MONDAY)->addDay();
            for ($i = 0; $i <= 6; $i++) {
                $min =  $result->whereBetween('time', [$startWeek, $endWeek])->min('weight');
                $max = $result->whereBetween('time', [$startWeek, $endWeek])->max('weight');
                $weight =  $max - $min;
                array_push($result_week, [
                    'weight' => $weight,
                    'item' => $i,
                ]);
                $startWeek->addDay();
                $endWeek->addDay();
            }
            return $result_week;
        }

        if ($range == 'month') {
            $totalDaysInMonth = Carbon::now()->daysInMonth;
            $startMonth = Carbon::now()->startOfMonth();
            $endMonth = Carbon::now()->startOfMonth()->addDay();
            for ($i = 0; $i < $totalDaysInMonth; $i++) {
                $min =  $result->whereBetween('time', [$startMonth, $endMonth])->min('weight');
                $max = $result->whereBetween('time', [$startMonth, $endMonth])->max('weight');
                $weight =  $max - $min;
                array_push($result_month, [
                    'weight' => $weight,
                    'item' => $i,
                ]);
                $startMonth->addDay();
                $endMonth->addDay();
            }
            return $result_month;
        }

        if ($range == 'year') {
            $startYear = Carbon::now()->startOfYear();
            $endYear = Carbon::now()->startOfYear()->addMonth();
            for ($i = 0; $i <= 11; $i++) {
                $min =  $result->whereBetween('time', [$startYear, $endYear])->min('weight');
                $max = $result->whereBetween('time', [$startYear, $endYear])->max('weight');
                $weight =  $max - $min;
                array_push($result_year, [
                    'weight' => $weight,
                    'item' => $i,
                ]);
                $startYear->addMonth();
                $endYear->addMonth();
            }
            return $result_year;
        }
    }
}
