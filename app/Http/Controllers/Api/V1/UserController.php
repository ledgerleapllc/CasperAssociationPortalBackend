<?php

namespace App\Http\Controllers\Api\V1;

use App\Console\Helper;

use App\Http\Controllers\Controller;
use App\Http\EmailerHelper;

use App\Http\Requests\Api\AddOwnerNodeRequest;
use App\Http\Requests\Api\ChangeEmailRequest;
use App\Http\Requests\Api\ResendEmailRequest;
use App\Http\Requests\Api\SubmitKYCRequest;
use App\Http\Requests\Api\SubmitPublicAddressRequest;
use App\Http\Requests\Api\VerifyFileCasperSignerRequest;

use App\Mail\AddNodeMail;
use App\Mail\LoginTwoFA;
use App\Mail\UserConfirmEmail;
use App\Mail\UserVerifyMail;

use App\Models\Ballot;
use App\Models\BallotFile;
use App\Models\BallotFileView;
use App\Models\LockRules;
use App\Models\Metric;
use App\Models\MonitoringCriteria;
use App\Models\DiscussionPin;
use App\Models\Donation;
use App\Models\MembershipAgreementFile;
use App\Models\Node;
use App\Models\NodeInfo;
use App\Models\OwnerNode;
use App\Models\Profile;
use App\Models\Shuftipro;
use App\Models\ShuftiproTemp;
use App\Models\User;
use App\Models\UserAddress;
use App\Models\VerifyUser;
use App\Models\Vote;
use App\Models\VoteResult;
use App\Models\Setting;
use App\Models\AllNodeData2;

use App\Repositories\OwnerNodeRepository;
use App\Repositories\ProfileRepository;
use App\Repositories\UserRepository;
use App\Repositories\VerifyUserRepository;

use App\Services\CasperSignature;
use App\Services\CasperSigVerify;
use App\Services\NodeHelper;
use App\Services\Test;
use App\Services\ChecksumValidator;
use App\Services\ShuftiproCheck as ServicesShuftiproCheck;

use Carbon\Carbon;

use Illuminate\Support\Facades\Http;
use Illuminate\Validation\Rule;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

use Aws\S3\S3Client;

use Exception;

class UserController extends Controller
{
    private $userRepo;
    private $verifyUserRepo;
    private $profileRepo;
    private $ownerNodeRepo;
    public  $failed_verification_response;

    public function __construct(
        UserRepository       $userRepo, 
        VerifyUserRepository $verifyUserRepo, 
        ProfileRepository    $profileRepo, 
        OwnerNodeRepository  $ownerNodeRepo
    ) {
        $this->userRepo = $userRepo;
        $this->verifyUserRepo = $verifyUserRepo;
        $this->profileRepo = $profileRepo;
        $this->ownerNodeRepo = $ownerNodeRepo;
        $this->failed_verification_response = 'Failed verification';
    }

    public function getUserDashboard() {
        $user    = auth()->user();
        $user_id = $user->id ?? 0;

        $current_era_id = Helper::getCurrentERAId();

        // Define complete return object
        $return = [
            "node_rank" => 0,
            "node_rank_total" => 100,
            "total_stake" => 0,
            "total_self_stake" => 0,
            "total_delegators" => 0,
            "uptime" => 0,
            "eras_active" => 0,
            "eras_since_bad_mark" => $current_era_id,
            "total_bad_marks" => 0,
            "update_responsiveness" => 100,
            "peers" => 0,
            "total_members" => 0,
            "verified_members" => 0,
            "association_members" => [],
            "ranking" => []
        ];

        // get all active members
        $association_members = DB::select("
            SELECT
            a.public_key,
            c.id, c.pseudonym, c.node_status,
            d.status, d.extra_status
            FROM all_node_data2 AS a
            JOIN user_addresses AS b
            ON a.public_key = b.public_address_node
            JOIN users AS c
            ON b.user_id = c.id
            JOIN profile AS d
            ON c.id = d.user_id
            WHERE a.era_id = $current_era_id and d.status = 'approved'
        ");
        $return["association_members"] = $association_members ?? [];

        // get verified members count
        $verified_members = DB::select("
            SELECT a.pseudonym, b.status
            FROM users AS a
            JOIN profile AS b
            ON a.id = b.user_id
            WHERE b.status = 'approved'
        ");
        $return["verified_members"] = $verified_members ? count($verified_members) : 0;

        // get total members count
        $total_members = DB::select("
            SELECT pseudonym
            FROM users
            WHERE role = 'member'
        ");
        $return["total_members"] = $total_members ? count($total_members) : 0;

        // find rank
        $ranking = DB::select("
            SELECT
            public_key, uptime,
            bid_delegators_count,
            bid_delegation_rate,
            bid_total_staked_amount
            FROM all_node_data2
            WHERE era_id = $current_era_id
            AND in_current_era = 1
            AND in_next_era = 1
            AND in_auction = 1
        ");
        $max_delegators = 0;
        $max_stake_amount = 0;

        foreach ($ranking as $r) {
            if ((int) $r->bid_delegators_count > $max_delegators) {
                $max_delegators   = (int) $r->bid_delegators_count;
            }
            if ((int) $r->bid_total_staked_amount > $max_stake_amount) {
                $max_stake_amount = (int) $r->bid_total_staked_amount;
            }
        }

        foreach ($ranking as $r) {
            $uptime_score = (float) (25 * (float) $r->uptime / 100);
            $uptime_score = $uptime_score < 0 ? 0 : $uptime_score;

            $fee_score = 25 * (1 - (float) ((float) $r->bid_delegation_rate / 100));
            $fee_score = $fee_score < 0 ? 0 : $fee_score;

            $count_score = (float) ((float) $r->bid_delegators_count / $max_delegators) * 25;
            $count_score = $count_score < 0 ? 0 : $count_score;

            $stake_score = (float) ((float) $r->bid_total_staked_amount / $max_stake_amount) * 25;
            $stake_score = $stake_score < 0 ? 0 : $stake_score;

            $return["ranking"][$r->public_key] = $uptime_score + $fee_score + $count_score + $stake_score;
        }

        uasort($return["ranking"], function($x, $y) {
            if ($x == $y) {
                return 0;
            }
            return ($x > $y) ? -1 : 1;
        });

        $sorted_ranking = [];
        $i = 1;
        foreach ($return["ranking"] as $public_key => $score) {
            $sorted_ranking[$public_key] = $i;
            $i += 1;
        }
        $return["ranking"] = $sorted_ranking;
        $return["node_rank_total"] = count($sorted_ranking);

        // parse node addresses
        $addresses = DB::select("
            SELECT 
            a.public_key,
            a.uptime,
            a.bid_delegators_count AS delegators,
            a.port8888_peers AS peers,
            a.bid_self_staked_amount, a.bid_total_staked_amount
            FROM all_node_data2 AS a
            JOIN user_addresses AS b
            ON a.public_key = b.public_address_node
            JOIN users AS c
            ON b.user_id    = c.id
            WHERE a.era_id  = $current_era_id
            AND b.user_id   = $user_id
        ");

        if (!$addresses) $addresses = [];

        $settings = Helper::getSettings();
        
        $voting_eras_to_vote = isset($settings['voting_eras_to_vote']) ? (int) $settings['voting_eras_to_vote'] : 0;
        $uptime_calc_size = isset($settings['uptime_calc_size']) ? (int) $settings['uptime_calc_size'] : 0;

        // for each address belonging to a user
        foreach ($addresses as $address) {
            $p = $address->public_key ?? '';

            $temp = DB::select("
                SELECT era_id
                FROM all_node_data2
                WHERE public_key = '$p'
                AND (
                    in_current_era = 0 OR
                    bid_inactive   = 1
                )
                ORDER BY era_id DESC
            ");

            $total_bad_marks = 0;
            $eras_since_bad_mark = $current_era_id;
            if ($temp && isset($temp[0])) {
                $total_bad_marks = count($temp);
                $eras_since_bad_mark = $current_era_id - (int) ($temp[0]->era_id ?? 0);
            }
            if ($eras_since_bad_mark < $return["eras_since_bad_mark"]) {
                $return["eras_since_bad_mark"] = $eras_since_bad_mark;
            }

            $temp = DB::select("
                SELECT era_id
                FROM all_node_data2
                WHERE public_key = '$p'
                ORDER BY era_id ASC
                LIMIT 1
            ");
            $eras_active = 0;
            if ($temp && isset($temp[0])) {
                $eras_active = (int) ($temp[0]->era_id ?? 0);
            }
            if ($current_era_id - $eras_active > $return["eras_active"]) {
                $return["eras_active"] = $current_era_id - $eras_active;
            }

            // Calculate historical_performance from past $uptime_calc_size eras
            $missed = 0;
            $temp = DB::select("
                SELECT in_current_era
                FROM all_node_data2
                WHERE public_key = '$p'
                ORDER BY era_id DESC
                LIMIT $uptime_calc_size
            ");
            if (!$temp) $temp = [];

            $window = count($temp);
            foreach ($temp as $c) {
                $in = (bool) ($c->in_current_era ?? 0);
                if (!$in) {
                    $missed += 1;
                }
            }

            $uptime = (float) ($address->uptime ?? 0);
            $historical_performance = round((float) ($uptime * ($window - $missed) / $window), 2);

            if (
                array_key_exists($p, $return["ranking"]) &&
                ($return["node_rank"] == 0 || $return["ranking"][$p] < $return["node_rank"])
            ) {
                $return["node_rank"] = $return["ranking"][$p];
            }

            $return["total_bad_marks"] += $total_bad_marks;
            $return["total_stake"] += (int) ($address->bid_total_staked_amount ?? 0);
            $return["total_self_stake"] += (int) ($address->bid_self_staked_amount ?? 0);
            $return["total_delegators"] += (int) ($address->delegators ?? 0);
            $return["peers"] += (int) ($address->peers ?? 0);
            $return["uptime"] += $historical_performance;
        }

        $addresses_count = count($addresses);
        $addresses_count = $addresses_count ? $addresses_count : 1;
        $return["uptime"] = round((float) ($return["uptime"] / $addresses_count), 2);

        unset($return["ranking"]);

        return $this->successResponse($return);
    }

    public function getMembershipPage() {
        $user = auth()->user()->load(['profile']);
        $user_id = $user->id ?? 0;

        $current_era_id = Helper::getCurrentERAId();
        $settings = Helper::getSettings();

        $return = [
            "node_status" => $user->node_status ?? '',
            "kyc_status" => "Not Verified",
            "uptime" => [],
            "avg_uptime" => 0,
            "total_eras" => 0,
            "eras_since_bad_mark" => $current_era_id,
            "total_bad_marks" => 0,
            "update_responsiveness" => 100,
            "peers" => 0
        ];
        if (isset($user->profile) && $user->profile->status == 'approved') {
            $return['kyc_status'] = 'Verified';
        }

        $addresses = DB::select("
            SELECT 
            a.public_key, a.uptime,
            a.port8888_peers AS peers,
            a.bid_inactive, a.in_current_era,
            c.kyc_verified_at AS kyc_status
            FROM all_node_data2 AS a
            JOIN user_addresses AS b
            ON a.public_key = b.public_address_node
            LEFT JOIN users AS c
            ON b.user_id    = c.id
            WHERE a.era_id  = $current_era_id
            AND b.user_id   = $user_id
        ");
        if (!$addresses) $addresses = [];

        $uptime_calc_size = isset($settings['uptime_calc_size']) ? (int) ($settings['uptime_calc_size']) : 0;

        foreach ($addresses as $address) {
            $p = $address->public_key ?? '';

            $temp = DB::select("
                SELECT era_id
                FROM all_node_data2
                WHERE public_key = '$p'
                AND (
                    in_current_era = 0 OR
                    bid_inactive   = 1
                )
                ORDER BY era_id DESC
            ");
            if (!$temp) $temp = [];

            $eras_since_bad_mark = $current_era_id;
            if ($temp && isset($temp[0])) {
                $eras_since_bad_mark = $current_era_id - (int) ($temp[0]->era_id ?? 0);
            }
            $total_bad_marks = count($temp);
            if ($eras_since_bad_mark < $return["eras_since_bad_mark"]) {
                $return["eras_since_bad_mark"] = $eras_since_bad_mark;
            }

            $temp = DB::select("
                SELECT era_id
                FROM all_node_data2
                WHERE public_key = '$p'
                ORDER BY era_id ASC
                LIMIT 1
            ");
            if (!$temp) $temp = [];
            $total_eras = 0;
            if ($temp && isset($temp[0])) {
                $total_eras = (int) ($temp[0]->era_id ?? 0);
            }
            if ($current_era_id - $total_eras > $return["total_eras"]) {
                $return["total_eras"] = $current_era_id - $total_eras;
            }

            // Calculate historical_performance from past $uptime_calc_size eras
            $missed = 0;
            $temp = DB::select("
                SELECT in_current_era
                FROM all_node_data2
                WHERE public_key = '$p'
                ORDER BY era_id DESC
                LIMIT $uptime_calc_size
            ");
            if (!$temp) $temp = [];

            $window = count($temp);

            foreach ($temp as $c) {
                $in = (bool) ($c->in_current_era ?? 0);
                if (!$in) {
                    $missed += 1;
                }
            }

            $uptime = (float) ($address->uptime ?? 0);
            $historical_performance = round((float) ($uptime * ($window - $missed) / $window), 2);

            $return["total_bad_marks"] += $total_bad_marks;
            $return["peers"] += (int) ($address->peers ?? 0);
            $return["uptime"][$p] = $historical_performance;
            $return["avg_uptime"] += $historical_performance;
        }

        $addresses_count = count($addresses);
        $addresses_count = $addresses_count ? $addresses_count : 1;
        $return["avg_uptime"] = round((float) ($return["avg_uptime"] / $addresses_count), 2);

        return $this->successResponse($return);
    }

    public function getNodesPage() {
        $user = auth()->user();
        $user_id = $user->id ?? 0;

        $nodeHelper = new NodeHelper();
        $current_era_id = Helper::getCurrentERAId();
        $settings = Helper::getSettings();

        // Define complete return object
        $return = [
            "mbs" => 0,
            "ranking" => [],
            "addresses" => []
        ];

        // get ranking
        $ranking = DB::select("
            SELECT
            public_key, uptime,
            bid_delegators_count,
            bid_delegation_rate,
            bid_total_staked_amount
            FROM all_node_data2
            WHERE era_id       = $current_era_id
            AND in_current_era = 1
            AND in_next_era    = 1
            AND in_auction     = 1
        ");
        if (!$ranking) $ranking = [];

        $max_delegators   = 0;
        $max_stake_amount = 0;
        foreach ($ranking as $r) {
            if ((int) $r->bid_delegators_count > $max_delegators) {
                $max_delegators = (int)$r->bid_delegators_count;
            }
            if ((int) $r->bid_total_staked_amount > $max_stake_amount) {
                $max_stake_amount = (int)$r->bid_total_staked_amount;
            }
        }

        foreach ($ranking as $r) {
            $uptime_score = (float) (25 * (float) $r->uptime / 100);
            $uptime_score = $uptime_score < 0 ? 0 : $uptime_score;

            $fee_score = 25 * (1 - (float) ($r->bid_delegation_rate / 100));
            $fee_score = $fee_score < 0 ? 0 : $fee_score;

            $count_score = (float) ($r->bid_delegators_count / $max_delegators) * 25;
            $count_score = $count_score < 0 ? 0 : $count_score;

            $stake_score = (float) ($r->bid_total_staked_amount / $max_stake_amount) * 25;
            $stake_score = $stake_score < 0 ? 0 : $stake_score;

            $return["ranking"][$r->public_key] = $uptime_score + $fee_score + $count_score + $stake_score;
        }

        uasort($return["ranking"], function($x, $y) {
            if ($x == $y) {
                return 0;
            }
            return ($x > $y) ? -1 : 1;
        });

        $sorted_ranking = [];
        $i = 1;
        foreach ($return["ranking"] as $public_key => $score) {
            $sorted_ranking[$public_key] = $i;
            $i += 1;
        }
        $return["ranking"] = $sorted_ranking;

        $addresses = DB::select("
            SELECT 
            a.public_key, a.bid_delegators_count,
            a.bid_total_staked_amount, a.bid_self_staked_amount,
            a.uptime, a.bid_inactive, a.in_current_era,
            a.port8888_peers AS peers
            FROM all_node_data2 AS a
            JOIN user_addresses AS b
            ON a.public_key = b.public_address_node
            JOIN users AS c
            ON b.user_id    = c.id
            WHERE a.era_id  = $current_era_id
            AND b.user_id   = $user_id
        ");
        if (!$addresses) $addresses = [];

        $uptime_calc_size = isset($settings['uptime_calc_size']) ? (int) $settings['uptime_calc_size'] : 0;

        // for each member's node address
        foreach ($addresses as $address) {
            $p = $address->public_key ?? '';

            $temp = DB::select("
                SELECT era_id
                FROM all_node_data2
                WHERE public_key = '$p'
                AND (
                    in_current_era = 0 OR
                    bid_inactive   = 1
                )
                ORDER BY era_id DESC
            ");
            if (!$temp) $temp = [];

            $eras_since_bad_mark = $current_era_id;
            if (isset($temp[0])) {
                $eras_since_bad_mark = $current_era_id - (int) ($temp[0]->era_id ?? 0);
            }
            $total_bad_marks = count($temp);

            $temp = DB::select("
                SELECT era_id
                FROM all_node_data2
                WHERE public_key = '$p'
                ORDER BY era_id ASC
                LIMIT 1
            ");
            if (!$temp) $temp = [];

            $total_eras = 0;
            if (isset($temp[0])) $total_eras = (int) ($temp[0]->era_id ?? 0);
            if ($current_era_id > $total_eras) $total_eras = $current_era_id - $total_eras;

            // Calculate historical_performance from past $uptime_calc_size eras
            $missed = 0;
            $temp = DB::select("
                SELECT in_current_era
                FROM all_node_data2
                WHERE public_key = '$p'
                ORDER BY era_id DESC
                LIMIT $uptime_calc_size
            ");
            if (!$temp) $temp = [];

            $window = count($temp);

            foreach ($temp as $c) {
                $in = (bool) ($c->in_current_era ?? 0);
                if (!$in) {
                    $missed += 1;
                }
            }

            $uptime = (float) ($address->uptime ?? 0);
            $historical_performance = round((float) ($uptime * ($window - $missed) / $window), 2);

            $one_day_ago = Carbon::now('UTC')->subHours(24);
            $temp = DB::select("
                SELECT bid_self_staked_amount
                FROM all_node_data2
                WHERE public_key = '$p'
                AND created_at < '$one_day_ago'
                ORDER BY era_id DESC
                LIMIT 1
            ");
            if (!$temp) $temp = [];
            $daily_earning = 0;
            if (isset($temp[0])) $daily_earning = (float) ($temp[0]->bid_self_staked_amount ?? 0);
            $daily_earning = (float) $address->bid_self_staked_amount - $daily_earning;
            $daily_earning = $daily_earning < 0 ? 0 : $daily_earning;

            $earning_day   = $nodeHelper->getValidatorRewards($p, 'day');
            $earning_week  = $nodeHelper->getValidatorRewards($p, 'week');
            $earning_month = $nodeHelper->getValidatorRewards($p, 'month');
            $earning_year  = $nodeHelper->getValidatorRewards($p, 'year');

            $return["addresses"][$p] = [
                "stake_amount"          => $address->bid_total_staked_amount,
                "delegators"            => $address->bid_delegators_count,
                "uptime"                => $historical_performance,
                "update_responsiveness" => 100,
                "peers"                 => (int) ($address->peers ?? 0),
                "daily_earning"         => $daily_earning,
                "total_eras"            => $total_eras,
                "eras_since_bad_mark"   => $eras_since_bad_mark,
                "total_bad_marks"       => $total_bad_marks,
                "validator_rewards"     => [
                    "day" => $earning_day,
                    "week" => $earning_week,
                    "month" => $earning_month,
                    "year" => $earning_year
                ]
            ];
        }

        // get mbs
        $temp = DB::select("
            SELECT mbs
            FROM mbs
            ORDER BY era_id DESC
            LIMIT 1
        ");
        if (!$temp) $temp = [];
        $return['mbs'] = 0;
        if (isset($temp[0])) $return['mbs'] = (int) ($temp[0]->mbs ?? 0);

        return $this->successResponse($return);
    }

    public function getMyEras() {
        $user = auth()->user();
        $user_id = $user->id ?? 0;

        $current_era_id = Helper::getCurrentERAId();
        $settings = Helper::getSettings();

        // define return object
        $return = [
            "addresses" => [],
            "column_count" => 2,
            "eras" => []
        ];
        
        // get addresses data
        $addresses = DB::select("
            SELECT 
            a.public_key, a.uptime
            FROM all_node_data2 AS a
            JOIN user_addresses AS b
            ON a.public_key = b.public_address_node
            JOIN users AS c
            ON b.user_id    = c.id
            WHERE a.era_id  = $current_era_id
            AND b.user_id   = $user_id
        ");
        if (!$addresses) $addresses = [];

        // get settings
        $voting_eras_to_vote = isset($settings['voting_eras_to_vote']) ? (int) $settings['voting_eras_to_vote'] : 0;
        $uptime_calc_size = isset($settings['uptime_calc_size']) ? (int) $settings['uptime_calc_size'] : 0;

        // for each member's node address
        foreach ($addresses as $address) {
            $p = $address->public_key ?? '';

            $temp = DB::select("
                SELECT era_id
                FROM all_node_data2
                WHERE public_key = '$p'
                AND (
                    in_current_era = 0 OR
                    bid_inactive   = 1
                )
                ORDER BY era_id DESC
            ");
            if (!$temp) $temp = [];

            $eras_since_bad_mark = $current_era_id;
            if (isset($temp[0])) {
                $eras_since_bad_mark = $current_era_id - (int) ($temp[0]->era_id ?? 0);
            }
            $total_bad_marks = count($temp);

            $temp = DB::select("
                SELECT era_id
                FROM all_node_data2
                WHERE public_key = '$p'
                ORDER BY era_id ASC
                LIMIT 1
            ");
            if (!$temp) $temp = [];

            $eras_active = 0;
            if (isset($temp[0])) $eras_active = (int) ($temp[0]->era_id ?? 0);
            if ($eras_active < $current_era_id) $eras_active = $current_era_id - $eras_active;

            // Calculate historical_performance from past $uptime_calc_size eras
            $missed = 0;
            $temp = DB::select("
                SELECT in_current_era
                FROM all_node_data2
                WHERE public_key = '$p'
                ORDER BY era_id DESC
                LIMIT $uptime_calc_size
            ");
            if (!$temp) $temp = [];

            $window = count($temp);

            foreach ($temp as $c) {
                $in = (bool) ($c->in_current_era ?? 0);
                if (!$in) {
                    $missed += 1;
                }
            }

            $uptime = (float) ($address->uptime ?? 0);
            $historical_performance = round((float) ($uptime * ($window - $missed) / $window), 2);

            $return["addresses"][$p] = [
                "uptime" => round($historical_performance, 2),
                "eras_active" => $eras_active,
                "eras_since_bad_mark" => $eras_since_bad_mark,
                "total_bad_marks" => $total_bad_marks
            ];
        }

        // get eras table data
        $era_minus_360 = $current_era_id - $uptime_calc_size;
        if ($era_minus_360 < 1) {
            $era_minus_360 = 1;
        }

        $eras = DB::select("
            SELECT
            a.public_key, a.era_id, a.created_at, 
            a.in_current_era, a.in_auction,
            a.bid_inactive, a.uptime
            FROM all_node_data2 AS a
            JOIN user_addresses
            ON a.public_key = user_addresses.public_address_node
            JOIN users
            ON user_addresses.user_id = users.id
            WHERE users.id = $user_id
            AND era_id > $era_minus_360
            ORDER BY a.era_id DESC
        ");
        if (!$eras) $eras = [];

        $sorted_eras = [];

        // for each node address's era
        foreach ($eras as $era) {
            $era_id = $era->era_id ?? 0;
            $era_start_time = $era->created_at ?? '';
            $public_key = $era->public_key;

            if (!isset($sorted_eras[$era_id])) {
                $sorted_eras[$era_id] = [
                    "era_start_time"  => $era_start_time,
                    "addresses"       => []
                ];
            }

            $sorted_eras[$era_id]["addresses"][$public_key] = [
                "in_pool" => $era->in_auction,
                "rewards" => $era->uptime
            ];
        }

        $return["eras"] = $sorted_eras;
        $column_count = 0;

        foreach ($return["eras"] as $era) {
            $count = $era["addresses"] ? count($era["addresses"]) : 0;
            if ($count > $column_count) {
                $column_count = $count;
            }
        }

        $return["column_count"] = $column_count + 1;

        return $this->successResponse($return);
    }

    // Shuftipro Webhook
    public function updateShuftiproStatus() {
        $json = file_get_contents('php://input');

        if ($json) {
            $data = json_decode($json, true);

            if ($data && isset($data['reference'])) {
                $shuftiproCheck = new ServicesShuftiproCheck();

                $reference_id = $data['reference'];

                $record = Shuftipro::where('reference_id', $reference_id)->first();
                $recordTemp = ShuftiproTemp::where('reference_id', $reference_id)->first();

                if (!$recordTemp) return;

                if ($record) {
                    if (isset($data['event']) && $data['event'] == 'request.deleted') {
                        // Reset Action
                        $user = User::find($record->user_id);

                        if ($user) {
                            $user_id = $user->id;
                            $profile = Profile::where('user_id', $user_id)->first();

                            if ($profile) {
                                $profile->status = null;
                                $profile->save();
                            }

                            Shuftipro::where('user_id', $user->id)->delete();
                            ShuftiproTemp::where('user_id', $user->id)->delete();
                        }
                        return;
                    }
                    $shuftiproCheck->handleExisting($record);
                } else {
                    $events = [
                        'verification.accepted',
                        'verification.declined'
                    ];

                    if (isset($data['event']) && in_array($data['event'], $events)) {
                        $user = User::find($recordTemp->user_id);

                        if ($user) {
                            $user_id = $user->id;
                            $profile = Profile::where('user_id', $user_id)->first();

                            if ($profile) {
                                $profile->status = 'pending';
                                $profile->save();
                            }

                            $recordTemp->status = 'booked';
                            $recordTemp->save();

                            $shuftiproCheck->handle($recordTemp);
                        }
                    }
                }
            }
        }
    }

    public function changeEmail(ChangeEmailRequest $request)
    {
        try {
            DB::beginTransaction();
            $user = auth()->user();
            $user->update(['email' => $request->email, 'email_verified_at' => null]);
            $code = generateString(7);
            $userVerify = $this->verifyUserRepo->updateOrCreate(
                [
                    'email' => $request->email,
                    'type' => VerifyUser::TYPE_VERIFY_EMAIL,
                ],
                [
                    'code' => $code,
                    'created_at' => now()
                ]
            );

            if ($userVerify) Mail::to($request->email)->send(new UserVerifyMail($code));
            DB::commit();
            return $this->metaSuccess();
        } catch (\Exception $ex) {
            return $this->errorResponse(__('api.error.internal_error'), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function getProfile()
    {
        $user = auth()->user()->load(['profile', 'pagePermissions', 'permissions', 'shuftipro', 'shuftiproTemp']);
        Helper::getAccountInfoStandard($user);
        // $user->metric = Helper::getNodeInfo($user);
        $user->globalSettings = Helper::getSettings();
        return $this->successResponse($user);
    }

    public function uploadLetter(Request $request)
    {
        try {
            // Validator
            $validator = Validator::make($request->all(), [
                'file' => 'required|mimes:pdf,jpeg,jpg,png,txt,rtf|max:200000'
            ]);

            if ($validator->fails()) {
                return $this->validateResponse($validator->errors());
            }

            $user = auth()->user();
            $extension = $request->file('file')->getClientOriginalExtension();
            $filenamehash = md5(Str::random(10).'_'.(string)time());
            $fileNameToStore = $filenamehash.'.'.$extension;

            $S3client = new S3Client([
                'version' => 'latest',
                'region' => getenv('AWS_DEFAULT_REGION'),
                'credentials' => [
                    'key' => getenv('AWS_ACCESS_KEY_ID'),
                    'secret' => getenv('AWS_SECRET_ACCESS_KEY'),
                ],
            ]);

            $s3result = $S3client->putObject([
                'Bucket' => getenv('AWS_BUCKET'),
                'Key' => 'letters_of_motivation/'.$fileNameToStore,
                'SourceFile' => $request->file('file')
            ]);

            $ObjectURL = $s3result['ObjectURL'] ?? getenv('SITE_URL').'/not-found';
            $user->letter_file = $ObjectURL;
            $user->letter_rejected_at = null;
            $user->save();
            $emailerData = EmailerHelper::getEmailerData();
            EmailerHelper::triggerAdminEmail('User uploads a letter', $emailerData, $user);
            EmailerHelper::triggerUserEmail($user->email, 'Your letter of motivation is received', $emailerData, $user);
            return $this->metaSuccess();
        } catch (\Exception $ex) {
            return $this->errorResponse(__('Failed upload file'), Response::HTTP_BAD_REQUEST, $ex->getMessage());
        }
    }

    public function sendHellosignRequest()
    {
        $user = auth()->user();
        if ($user) {
            $client_key = config('services.hellosign.api_key');
            $client_id = config('services.hellosign.client_id');
            $template_id = '80392797521f1adb88743f75ea04203a6504ef81';
            $client = new \HelloSign\Client($client_key);
            $request = new \HelloSign\TemplateSignatureRequest;

            $whitelist = [
                'http://casper.local',
                'http://casper.local/',
                'https://backend.caspermember.com',
                'https://backend.caspermember.com/',
                'https://members-backend-staging.casper.network',
                'https://members-backend-staging.casper.network/',
            ];

            if (in_array(env('APP_URL'), $whitelist)) $request->enableTestMode();

            $request->setTemplateId($template_id);
            $request->setSubject('Member Agreement');
            $request->setSigner('Member', $user->email, $user->first_name . ' ' . $user->last_name);
            $request->setCustomFieldValue('FullName', $user->first_name . ' ' . $user->last_name);
            $request->setCustomFieldValue('FullName2', $user->first_name . ' ' . $user->last_name);
            $request->setClientId($client_id);

            $initial = strtoupper(substr($user->first_name, 0, 1)) . strtoupper(substr($user->last_name, 0, 1));
            $request->setCustomFieldValue('Initial', $initial);

            $embedded_request = new \HelloSign\EmbeddedSignatureRequest($request, $client_id);
            $response = $client->createEmbeddedSignatureRequest($embedded_request);

            $signature_request_id = $response->getId();

            $signatures = $response->getSignatures();
            $signature_id = $signatures[0]->getId();

            $response = $client->getEmbeddedSignUrl($signature_id);
            $sign_url = $response->getSignUrl();

            $user->update(['signature_request_id' => $signature_request_id]);
            $emailerData = EmailerHelper::getEmailerData();
            if ($user->letter_verified_at && $user->signature_request_id && $user->node_verified_at)
                EmailerHelper::triggerUserEmail($user->email, 'Congratulations', $emailerData, $user);
            return $this->successResponse([
                'signature_request_id' => $signature_request_id,
                'url' => $sign_url,
            ]);
        }
        return $this->errorResponse(__('Hellosign request fail'), Response::HTTP_BAD_REQUEST);
    }

    public function submitPublicAddress(SubmitPublicAddressRequest $request)
    {
        $user = auth()->user();
        $address = strtolower($request->public_address);
        $public_address = strtolower($address);
        $public_address_temp = (new ChecksumValidator())->do($address);

        if (!$public_address_temp) {
            return $this->errorResponse(
                __('The validator ID is invalid'), 
                Response::HTTP_BAD_REQUEST
            );
        }

        $correct_checksum = (int) (new ChecksumValidator($public_address_temp))->do();

        if (!$correct_checksum) {
            return $this->errorResponse(
                __('The validator ID is invalid'), 
                Response::HTTP_BAD_REQUEST
            );
        }

        // User Check
        $tempUser = User::where('public_address_node', $public_address)->first();

        if (
            $tempUser && 
            $tempUser->id != $user->id && 
            $tempUser->node_verified_at
        ) {
            return $this->errorResponse(
                __('The validator ID you specified is already associated with an Association member'), 
                Response::HTTP_BAD_REQUEST
            );
        }
        
        // User Address Check
        $tempUserAddress = UserAddress::where('public_address_node', $public_address)->first();

        if (
            $tempUserAddress && 
            $tempUserAddress->user_id != $user->id && 
            $tempUserAddress->node_verified_at
        ) {
            return $this->errorResponse(
                __('The validator ID you specified is already associated with an Association member'), 
                Response::HTTP_BAD_REQUEST
            );
        }

        // Pool Check
        $nodeHelper = new NodeHelper();
        $addresses = $nodeHelper->getValidAddresses();

        if (!in_array($public_address, $addresses)) {
            return $this->errorResponse(
                __('The validator ID specified could not be found in the Casper validator pool'), 
                Response::HTTP_BAD_REQUEST
            );
        }
        
        // Remove Other User's Same Address
        UserAddress::where('public_address_node', $public_address)->where('user_id', '!=', $user->id)->whereNull('node_verified_at')->delete();
        User::where('public_address_node', $public_address)->where('id', '!=', $user->id)->whereNull('node_verified_at')->update(['public_address_node' => null]);
        
        if (
            !$tempUserAddress || 
            $tempUserAddress->user_id != $user->id
        ) {
            $userAddress = new UserAddress;
            $userAddress->user_id = $user->id;
            $userAddress->public_address_node = $public_address;
            $userAddress->save();
        }

        $user->update([
            'has_address' => 1,
            'public_address_node' => $public_address,
        ]);

        return $this->metaSuccess();
    }

    public function checkValidatorAddress(SubmitPublicAddressRequest $request)
    {
        $address = strtolower($request->public_address);
        $public_address_temp = (new ChecksumValidator())->do($address);
        $public_address = strtolower($address);

        if (!$public_address_temp) {
            return $this->successResponse(
                ['message' => __('The validator ID is invalid')]
            );
        }

        $correct_checksum = (int) (new ChecksumValidator($public_address_temp))->do();

        if (!$correct_checksum) {
            return $this->successResponse(
                ['message' => __('The validator ID is invalid')]
            );
        }

        // User Check
        $tempUser = User::where('public_address_node', $public_address)->first();

        if ($tempUser && $tempUser->node_verified_at) {
            return $this->successResponse(
                ['message' => __('The validator ID you specified is already associated with an Association member')]
            );
        }

        // User Address Check
        $tempUserAddress = UserAddress::where('public_address_node', $public_address)->first();

        if ($tempUserAddress && $tempUserAddress->node_verified_at) {
            return $this->successResponse(
                ['message' => __('The validator ID you specified is already associated with an Association member')]
            );
        }

        $nodeHelper = new NodeHelper();
        $addresses = $nodeHelper->getValidAddresses();

        if (!in_array($public_address, $addresses)) {
            return $this->successResponse(
                ['message' => __('The validator ID specified could not be found in the Casper validator pool')]
            );
        }
        return $this->metaSuccess();
    }

    public function checkPublicAddress(SubmitPublicAddressRequest $request)
    {
        $address = strtolower($request->public_address);
        $public_address = strtolower($address);
        $public_address_temp = (new ChecksumValidator())->do($address);

        if (!$public_address_temp) {
            return $this->errorResponse(
                __('The validator ID is invalid'), 
                Response::HTTP_BAD_REQUEST
            );
        }

        $correct_checksum = (int) (new ChecksumValidator($public_address_temp))->do();

        if (!$correct_checksum) {
            return $this->errorResponse(
                __('The validator ID is invalid'), 
                Response::HTTP_BAD_REQUEST
            );
        }

        // User Check
        $tempUser = User::where('public_address_node', $public_address)->first();

        if ($tempUser && $tempUser->node_verified_at) {
            return $this->errorResponse(
                __('The validator ID you specified is already associated with an Association member'), 
                Response::HTTP_BAD_REQUEST
            );
        }

        // User Address Check
        $tempUserAddress = UserAddress::where('public_address_node', $public_address)->first();

        if ($tempUserAddress && $tempUserAddress->node_verified_at) {
            return $this->errorResponse(
                __('The validator ID you specified is already associated with an Association member'), 
                Response::HTTP_BAD_REQUEST
            );
        }

        // Pool Check
        $nodeHelper = new NodeHelper();
        $addresses = $nodeHelper->getValidAddresses();

        if (!in_array($public_address, $addresses)) {
            return $this->errorResponse(
                __('The validator ID specified could not be found in the Casper validator pool'), 
                Response::HTTP_BAD_REQUEST
            );
        }

        return $this->metaSuccess();
    }

    public function getMessageContent()
    {
        $user      = auth()->user();
        $timestamp = date('d/m/Y');
        $message   = "Please use the Casper Signature python tool to sign this message! " . $timestamp;
        $user->update(['message_content' => $message]);
        $filename  = 'message.txt';

        return response()->streamDownload(function () use ($message) {
            echo $message;
        }, $filename);
    }

    public function verifyFileCasperSigner2(VerifyFileCasperSignerRequest $request)
    {
        try {
            $casperSigVerify      = new CasperSigVerify();
            $user                 = auth()->user();
            $message              = $user->message_content;
            $public_validator_key = strtolower($request->address);

            $userRecord = User::where(
                'public_address_node', 
                $public_validator_key
            )->first();

            $userAddress = UserAddress::where(
                'public_address_node', 
                $public_validator_key
            )->first();

            if ($userRecord && $userRecord->node_verified_at) {
                return $this->errorResponse(
                    __($this->failed_verification_response), 
                    Response::HTTP_BAD_REQUEST
                );
            }

            if ($userAddress && $userAddress->node_verified_at) {
                return $this->errorResponse(
                    __($this->failed_verification_response), 
                    Response::HTTP_BAD_REQUEST
                );
            }

            $file      = $request->file;
            $name      = $file->getClientOriginalName();
            $hexstring = $file->get();

            if ($hexstring && $name == 'signature') {
                $verified = $casperSigVerify->verify(
                    trim($hexstring),
                    $public_validator_key,
                    $message
                );

                if ($verified) {
                    $filenamehash = md5(Str::random(10) . '_' . (string)time());

                    $S3 = new S3Client([
                        'version'     => 'latest',
                        'region'      => getenv('AWS_DEFAULT_REGION'),
                        'credentials' => [
                            'key'     => getenv('AWS_ACCESS_KEY_ID'),
                            'secret'  => getenv('AWS_SECRET_ACCESS_KEY'),
                        ],
                    ]);

                    $s3result = $S3->putObject([
                        'Bucket'      => getenv('AWS_BUCKET'),
                        'Key'         => 'signatures/' . $filenamehash,
                        'SourceFile'  => $request->file('file')
                    ]);

                    $ObjectURL = $s3result['ObjectURL'] ?? getenv('SITE_URL').'/not-found';

                    // Remove Other User's Same Address
                    UserAddress::where('public_address_node', $public_validator_key)
                        ->where('user_id', '!=', $user->id)
                        ->whereNull('node_verified_at')
                        ->delete();

                    User::where('public_address_node', $public_validator_key)
                        ->where('id', '!=', $user->id)
                        ->whereNull('node_verified_at')
                        ->update(['public_address_node' => null]);

                    $userAddress = UserAddress::where(
                        'public_address_node', 
                        $public_validator_key)
                        ->where('user_id', $user->id)
                        ->first();

                    if (!$userAddress) {
                        $userAddress = new UserAddress;
                    }

                    $userAddress->user_id             = $user->id;
                    $userAddress->public_address_node = $public_validator_key;
                    $userAddress->signed_file         = $ObjectURL;
                    $userAddress->node_verified_at    = now();
                    $userAddress->save();

                    $emailerData = EmailerHelper::getEmailerData();

                    EmailerHelper::triggerUserEmail(
                        $user->email, 
                        'Your Node is Verified', 
                        $emailerData, 
                        $user, 
                        $userAddress
                    );
                    return $this->metaSuccess();
                } else {
                    return $this->errorResponse(
                        __($this->failed_verification_response), 
                        Response::HTTP_BAD_REQUEST
                    );
                }
            }
            return $this->errorResponse(
                __($this->failed_verification_response), 
                Response::HTTP_BAD_REQUEST
            );
        } catch (\Exception $ex) {
            return $this->errorResponse(
                __($this->failed_verification_response), 
                Response::HTTP_BAD_REQUEST, 
                $ex->getMessage()
            );
        }
    }

    public function verifyFileCasperSigner(VerifyFileCasperSignerRequest $request)
    {
        try {
            $casperSigVerify      = new CasperSigVerify();
            $user                 = auth()->user();
            $message              = $user->message_content;
            $public_validator_key = strtolower($request->address);

            $userRecord = User::where('id', $user->id)
                ->where('public_address_node', $public_validator_key)
                ->first();

            $userAddress = UserAddress::where('user_id', $user->id)
                ->where('public_address_node', $public_validator_key)
                ->first();

            if (!$userRecord || !$userAddress) {
                return $this->errorResponse(
                    __($this->failed_verification_response), 
                    Response::HTTP_BAD_REQUEST
                );
            }

            $file      = $request->file;
            $name      = $file->getClientOriginalName();
            $hexstring = $file->get();

            if ($hexstring && $name == 'signature') {
                $verified = $casperSigVerify->verify(
                    trim($hexstring),
                    $public_validator_key,
                    $message
                );

                if ($verified) {
                    $filenamehash = md5(Str::random(10) . '_' . (string)time());

                    // S3 file upload
                    $S3 = new S3Client([
                        'version'     => 'latest',
                        'region'      => getenv('AWS_DEFAULT_REGION'),
                        'credentials' => [
                            'key'     => getenv('AWS_ACCESS_KEY_ID'),
                            'secret'  => getenv('AWS_SECRET_ACCESS_KEY'),
                        ],
                    ]);

                    $s3result = $S3->putObject([
                        'Bucket'      => getenv('AWS_BUCKET'),
                        'Key'         => 'signatures/' . $filenamehash,
                        'SourceFile'  => $request->file('file')
                    ]);

                    $ObjectURL = $s3result['ObjectURL'] ?? getenv('SITE_URL').'/not-found';

                    $user->signed_file             = $ObjectURL;
                    $user->has_verified_address    = 1;
                    $user->node_verified_at        = now();
                    $user->save();

                    $userAddress->signed_file      = $ObjectURL;
                    $userAddress->node_verified_at = now();
                    $userAddress->save();

                    $emailerData = EmailerHelper::getEmailerData();

                    EmailerHelper::triggerUserEmail(
                        $user->email, 
                        'Your Node is Verified', 
                        $emailerData, 
                        $user
                    );

                    if (
                        $user->letter_verified_at && 
                        $user->signature_request_id && 
                        $user->node_verified_at
                    ) {
                        EmailerHelper::triggerUserEmail(
                            $user->email, 
                            'Congratulations', 
                            $emailerData, 
                            $user
                        );
                    }
                    return $this->metaSuccess();
                } else {
                    return $this->errorResponse(
                        __($this->failed_verification_response), 
                        Response::HTTP_BAD_REQUEST
                    );
                }
            }
            return $this->errorResponse(
                __($this->failed_verification_response), 
                Response::HTTP_BAD_REQUEST
            );
        } catch (\Exception $ex) {
            return $this->errorResponse(
                __($this->failed_verification_response), 
                Response::HTTP_BAD_REQUEST, 
                $ex->getMessage()
            );
        }
    }

    public function functionSubmitKYC(SubmitKYCRequest $request)
    {
        $user        = auth()->user();
        $data        = $request->validated();
        $data['dob'] = \Carbon\Carbon::parse($request->dob)->format('Y-m-d');
        $user->update(['member_status' => User::STATUS_INCOMPLETE]);

        $this->profileRepo->updateOrCreate(
            [
                'user_id' => $user->id,
            ],
            $data
        );

        $user->reset_kyc = 0;
        $user->save();
        return $this->metaSuccess();
    }

    // Save Shuftipro Temp
    public function saveShuftiproTemp(Request $request)
    {
        $user = auth()->user();

        // Validator
        $validator = Validator::make($request->all(), [
            'reference_id' => 'required'
        ]);

        if ($validator->fails()) {
            return $this->validateResponse($validator->errors());
        }

        $user_id              = $user->id;
        $reference_id         = $request->reference_id;

        ShuftiproTemp::where('user_id', $user_id)->delete();

        $record               = new ShuftiproTemp;
        $record->user_id      = $user_id;
        $record->reference_id = $reference_id;
        $record->save();

        return $this->metaSuccess();
    }

    // Delete Shuftipro Temp Status
    public function deleteShuftiproTemp(Request $request)
    {
        $user = auth()->user();

        // Validator
        $validator = Validator::make($request->all(), [
            'reference_id' => 'required'
        ]);

        if ($validator->fails()) {
            return $this->validateResponse($validator->errors());
        }

        $user_id      = $user->id;
        $reference_id = $request->reference_id;
        $profile      = Profile::where('user_id', $user_id)->first();

        if ($profile) {
            $profile->status = null;
            $profile->save();
        }

        Shuftipro::where('user_id', $user_id)->delete();
        ShuftiproTemp::where('user_id', $user_id)->delete();
        
        return $this->metaSuccess();
    }

    // Update Shuftipro Temp Status
    /*
    public function updateShuftiProTemp(Request $request)
    {
        $user = auth()->user();

        // Validator
        $validator = Validator::make($request->all(), [
            'reference_id' => 'required'
        ]);

        if ($validator->fails()) {
            return $this->validateResponse($validator->errors());
        }

        $user_id      = $user->id;
        $reference_id = $request->reference_id;
        $profile      = Profile::where('user_id', $user_id)->first();

        if ($profile) {
            $profile->status = 'pending';
            $profile->save();
        }

        $record = ShuftiproTemp::where('user_id', $user_id)
            ->where('reference_id', $reference_id)
            ->first();

        if ($record) {
            $record->status = 'booked';
            $record->save();
            $emailerData    = EmailerHelper::getEmailerData();

            EmailerHelper::triggerAdminEmail(
                'KYC or AML need review', 
                $emailerData, 
                $user
            );

            return $this->metaSuccess();
        }
        return $this->errorResponse(
            'Fail submit AML', 
            Response::HTTP_BAD_REQUEST
        );
    }
    */

    // get vote list
    public function getVotes(Request $request)
    {
        $user = auth()->user()->load(['pagePermissions']);
        if (Helper::isAccessBlocked($user, 'votes'))
            return $this->successResponse(['data' => []]);

        $status = $request->status ?? 'active';
        $limit = $request->limit ?? 50;
        $sort_key = $request->sort_key ?? 'ballot.id';
        $sort_direction = $request->sort_direction ?? 'desc';

        if (
            $status != 'active' && 
            $status != 'finish' && 
            $status != 'scheduled'
        ) {
            return $this->errorResponse(
                'Paramater invalid (status is active or finish)', 
                Response::HTTP_BAD_REQUEST
            );
        }

        $now       = Carbon::now('EST');
        $startDate = $now->format('Y-m-d');
        $startTime = $now->format('H:i:s');

        if ($status == 'active') {
            $query = Ballot::where('status', 'active')
                ->where(function ($query) use ($startDate, $startTime) {
                    $query->where('start_date', '<', $startDate)
                        ->orWhere(function ($query) use ($startDate, $startTime) {
                            $query->where('start_date', $startDate)
                                ->where('start_time', '<=', $startTime);
                        });
                });
        }

        else if ($status == 'scheduled') {
            $query = Ballot::where('status', 'active')
                ->where(function ($query) use ($startDate, $startTime) {
                    $query->where('start_date', '>', $startDate)
                        ->orWhere(function ($query) use ($startDate, $startTime) {
                            $query->where('start_date', $startDate)
                                ->where('start_time', '>', $startTime);
                        });
                });
        }

        else {
            $query = Ballot::where('status', '<>', 'active');
        }

        $data = $query->with('vote')->orderBy(
            $sort_key, 
            $sort_direction
        )->paginate($limit);

        return $this->successResponse($data);
    }

    // get vote detail
    public function getVoteDetail($id)
    {
        $user = auth()->user()->load(['pagePermissions']);
        if (Helper::isAccessBlocked($user, 'votes'))
            return $this->errorResponse('Your access is blocked', Response::HTTP_BAD_REQUEST);

        $ballot = Ballot::with(['vote', 'voteResults.user', 'files'])->where('id', $id)->first();
        if (!$ballot)
            return $this->errorResponse('Not found ballot', Response::HTTP_BAD_REQUEST);
        foreach ($ballot->files as $file) {
            $ballotFileView = BallotFileView::where('ballot_file_id', $file->id)->where('user_id', $user->id)->first();
            $file->is_viewed =  $ballotFileView  ? 1 : 0;
        }

        foreach ($ballot->files as $file) {
            $ballotFileView  = BallotFileView::where('ballot_file_id', $file->id)
                ->where('user_id', $user->id)
                ->first();

            $file->is_viewed = $ballotFileView  ? 1 : 0;
        }

        $ballot->user_vote = VoteResult::where('user_id', $user->id)
            ->where('ballot_id', $ballot->id)
            ->first();

        return $this->successResponse($ballot);
    }

    public function canVote()
    {
        $user = auth()->user();
        $user_id = $user->id;

        $return = [
            'setting_voting_eras' => 0,
            'setting_good_standing_eras' => 0,
            'good_standing_eras' => 0,
            'total_active_eras' => 0,
            'can_vote' => false
        ];

        $current_era_id = Helper::getCurrentERAId();
        $settings = Helper::getSettings();

        $voting_eras_to_vote = isset($settings['voting_eras_to_vote']) ? (int) $settings['voting_eras_to_vote'] : 0;
        $voting_eras_since_redmark = isset($settings['voting_eras_since_redmark']) ? (int) $settings['voting_eras_since_redmark'] : 0;

        $return['setting_voting_eras'] = $voting_eras_to_vote;
        $return['setting_good_standing_eras'] = $voting_eras_since_redmark;

        $user_addresses = DB::select("
            SELECT public_address_node
            FROM user_addresses
            WHERE user_id = $user_id
        ");
        $user_addresses = $user_addresses ?? [];

        foreach ($user_addresses as $a) {
            $p = $a->public_address_node ?? '';

            // find smallest number of eras since public_key encountered a bad mark
            $temp = DB::select("
                SELECT era_id 
                FROM all_node_data2
                WHERE public_key = '$p'
                AND (
                    in_current_era = 0 OR
                    bid_inactive   = 1
                )
                ORDER BY era_id DESC
                LIMIT 1
            ");
            if (!$temp) $temp = [];

            $good_standing_eras = 0;
            if (isset($temp[0])) $good_standing_eras = (int) ($temp[0]->era_id ?? 0);
            if ($current_era_id > $good_standing_eras) $good_standing_eras = $current_era_id - $good_standing_eras;
            if ($good_standing_eras > $return['good_standing_eras']) {
                $return['good_standing_eras'] = $good_standing_eras;
            }

            // total_active_eras
            $temp = DB::select("
                SELECT count(id) as tCount
                FROM all_node_data2
                WHERE public_key = '$p'
            ");
            if (!$temp) $temp = [];
            $eras = 0;
            if (isset($temp[0])) $eras = (int) ($temp[0]->tCount ?? 0);
            if ($current_era_id - $eras > $return['total_active_eras']) {
                $return['total_active_eras'] = $current_era_id - $eras;
            }

            if (
                $return['total_active_eras']  >= $voting_eras_to_vote &&
                $return['good_standing_eras'] >= $voting_eras_since_redmark
            ) {
                $return['can_vote'] = true;
            }
        }

        return $this->successResponse($return);
    }

    // vote the ballot
    public function vote($id, Request $request)
    {
        $user = auth()->user()->load(['pagePermissions']);
        if (Helper::isAccessBlocked($user, 'votes'))
            return $this->errorResponse('Your access is blocked', Response::HTTP_BAD_REQUEST);

        $vote = $request->vote;

        if (
            !$vote || (
                $vote != 'for' && 
                $vote != 'against'
            )
        ) {
            return $this->errorResponse(
                'Paramater invalid (vote is for or against)', 
                Response::HTTP_BAD_REQUEST
            );
        }

        // New check for stable member validator
        $stable    = false;
        $addresses = UserAddress::where('user_id', $user->id)->get();

        if (!$addresses) {
            $addresses = array();
        }

        // get settings
        $voting_eras_to_vote = DB::select("
            SELECT value
            FROM settings
            WHERE name = 'voting_eras_to_vote'
        ");
        $voting_eras_to_vote = $voting_eras_to_vote[0] ?? array();
        $voting_eras_to_vote = (int)($voting_eras_to_vote->value ?? 0);

        $voting_eras_since_redmark = DB::select("
            SELECT value
            FROM settings
            WHERE name = 'voting_eras_since_redmark'
        ");
        $voting_eras_since_redmark = $voting_eras_since_redmark[0] ?? array();
        $voting_eras_since_redmark = (int)($voting_eras_since_redmark->value ?? 0);

        foreach ($addresses as $address) {
            $p = $address->public_address_node ?? '';

            // find smallest number of eras since public_key encountered a bad mark
            // good_standing_eras
            $good_standing_eras = DB::select("
                SELECT era_id 
                FROM all_node_data2
                WHERE public_key = '$p'
                AND (
                    in_current_era = 0 OR
                    bid_inactive   = 1
                )
                ORDER BY era_id DESC
                LIMIT 1
            ");
            $good_standing_eras = $good_standing_eras[0] ?? array();
            $good_standing_eras = (int)($good_standing_eras->era_id ?? 0);
            $good_standing_eras = $current_era_id - $good_standing_eras;

            if ($good_standing_eras < 0) {
                $good_standing_eras = 0;
            }

            // total_active_eras
            $total_active_eras = DB::select("
                SELECT count(id)
                FROM all_node_data2
                WHERE public_key = '$p'
            ");

            $total_active_eras = (array)($total_active_eras[0] ?? array());
            $total_active_eras = (int)($total_active_eras['count(id)'] ?? 0);
            $total_active_eras = $current_era_id - $total_active_eras;

            if (
                $total_active_eras  >= $voting_eras_to_vote &&
                $good_standing_eras >= $voting_eras_since_redmark
            ) {
                $stable = true;
            }
        }

        if (!$stable) {
            return $this->errorResponse(
                'Validator is not stable enough to vote', 
                Response::HTTP_BAD_REQUEST
            );
        }

        $ballot = Ballot::where('id', $id)->first();

        if (!$ballot) {
            return $this->errorResponse(
                'Not found ballot', 
                Response::HTTP_BAD_REQUEST
            );
        }

        $voteResult = VoteResult::where('user_id', $user->id)
            ->where('ballot_id', $ballot->id)
            ->first();

        if ($voteResult) {
            if ($vote == $voteResult->type) {
                return $this->metaSuccess();
            } else {
                $voteResult->type       = $vote;
                $voteResult->updated_at = now();

                if ($vote == 'for') {
                    $ballot->vote->for_value     = $ballot->vote->for_value + 1;
                    $ballot->vote->against_value = $ballot->vote->against_value - 1;
                } else {
                    $ballot->vote->for_value     = $ballot->vote->for_value - 1;
                    $ballot->vote->against_value = $ballot->vote->against_value + 1;
                }

                $ballot->vote->updated_at = now();
                $ballot->vote->save();
                $voteResult->save();
            }
        } else {
            $voteResult = new VoteResult();
            $voteResult->user_id   = $user->id;
            $voteResult->ballot_id = $ballot->id;
            $voteResult->vote_id   = $ballot->vote->id;
            $voteResult->type      = $vote;
            $voteResult->save();

            if ($vote == 'for') {
                $ballot->vote->for_value     = $ballot->vote->for_value + 1;
            } else {
                $ballot->vote->against_value = $ballot->vote->against_value + 1;
            }

            $ballot->vote->result_count = $ballot->vote->result_count + 1;
            $ballot->vote->updated_at   = now();
            $ballot->vote->save();
        }
        return $this->metaSuccess();
    }

    public function submitViewFileBallot(Request $request, $fileId)
    {
        $user       = auth()->user();
        $ballotFile = BallotFile::where('id', $fileId)->first();

        if (!$ballotFile) {
            return $this->errorResponse(
                'Not found ballot file', 
                Response::HTTP_BAD_REQUEST
            );
        }

        $ballotFileView = BallotFileView::where('ballot_file_id', $ballotFile->id)
            ->where('user_id', $user->id)
            ->first();

        if ($ballotFileView) {
            return $this->metaSuccess();
        }

        $ballotFileView                 = new BallotFileView();
        $ballotFileView->ballot_file_id = $ballotFile->id;
        $ballotFileView->ballot_id      = $ballotFile->ballot_id;
        $ballotFileView->user_id        = $user->id;
        $ballotFileView->save();
        return $this->metaSuccess();
    }
    /**
     * verify file casper singer
     */
    public function uploadAvatar(Request $request)
    {
        try {
            // Validator
            $validator = Validator::make($request->all(), [
                'avatar' => 'sometimes|mimes:jpeg,jpg,png,gif,webp|max:100000',
            ]);

            if ($validator->fails()) {
                return $this->validateResponse($validator->errors());
            }

            $user            = auth()->user();
            $filenameWithExt = $request->file('avatar')->getClientOriginalName();
            $filename        = pathinfo($filenameWithExt, PATHINFO_FILENAME);
            $extension       = $request->file('avatar')->getClientOriginalExtension();
            // new filename hash
            $filenamehash    = md5(Str::random(10) . '_' . (string)time());
            // Filename to store
            $fileNameToStore = $filenamehash . '.' . $extension;

            // S3 file upload
            $S3 = new S3Client([
                'version'     => 'latest',
                'region'      => getenv('AWS_DEFAULT_REGION'),
                'credentials' => [
                    'key'     => getenv('AWS_ACCESS_KEY_ID'),
                    'secret'  => getenv('AWS_SECRET_ACCESS_KEY'),
                ],
            ]);

            $s3result = $S3->putObject([
                'Bucket'      => getenv('AWS_BUCKET'),
                'Key'         => 'client_uploads/' . $fileNameToStore,
                'SourceFile'  => $request->file('avatar'),
            ]);

            // $ObjectURL = 'https://'.getenv('AWS_BUCKET').'.s3.amazonaws.com/client_uploads/'.$fileNameToStore;
            $user->avatar = $s3result['ObjectURL'] ?? getenv('SITE_URL') . '/not-found';
            $user->save();
            return $this->metaSuccess();
        } catch (\Exception $ex) {
            return $this->errorResponse(
                __('Failed upload avatar'), 
                Response::HTTP_BAD_REQUEST, 
                $ex->getMessage()
            );
        }
    }

    public function getMembers(Request $request)
    {
        $current_era_id = Helper::getCurrentERAId();
        $search = $request->search;

        $members = DB::table('users')
            ->select(
                'users.id',
                'users.pseudonym',
                'users.created_at',
                'user_addresses.node_verified_at',
                'all_node_data2.public_key',
                'all_node_data2.uptime',
                'all_node_data2.bid_delegators_count',
                'all_node_data2.bid_delegation_rate',
                'all_node_data2.bid_total_staked_amount'
            )
            ->join(
                'user_addresses',
                'user_addresses.user_id',
                '=',
                'users.id'
            )
            ->join(
                'all_node_data2', 
                'all_node_data2.public_key', 
                '=', 
                'user_addresses.public_address_node'
            )
            ->where([
                'users.banned' => 0,
                'all_node_data2.era_id' => $current_era_id
            ])
            ->where(function ($query) use ($search) {
                if ($search) {
                    $query->where('users.first_name', 'like', '%' . $search . '%')
                        ->orWhere('users.last_name', 'like', '%' . $search . '%')
                        ->orWhere('users.pseudonym', 'like', '%' . $search . '%');
                }
            })
            ->get();

        $max_delegators   = 0;
        $max_stake_amount = 0;

        foreach ($members as $member) {
            if ((int)$member->bid_delegators_count > $max_delegators) {
                $max_delegators = (int)$member->bid_delegators_count;
            }

            if ((int)$member->bid_total_staked_amount > $max_stake_amount) {
                $max_stake_amount = (int)$member->bid_total_staked_amount;
            }
        }

        foreach ($members as &$member) {
            $uptime_score     = (
                ($request->uptime ?? 0) * 
                (float) ($member->historical_performance ?? 0)
            ) / 100;
            $uptime_score     = $uptime_score < 0 ? 0 : $uptime_score;

            $fee_score        = (
                ($request->delegation_rate ?? 0) * 
                (1 - ((float)$member->bid_delegation_rate / 100))
            );
            $fee_score        = $fee_score < 0 ? 0 : $fee_score;

            $count_score      = (
                (float)$member->bid_delegators_count / 
                $max_delegators
            ) * ($request->delegators ?? 0);
            $count_score      = $count_score < 0 ? 0 : $count_score;

            $stake_score      = (
                (float)$member->bid_total_staked_amount / 
                $max_stake_amount
            ) * ($request->stake_amount ?? 0);
            $stake_score      = $stake_score < 0 ? 0 : $stake_score;

            $member->total_score = (
                $uptime_score     +
                $fee_score        +
                $count_score      + 
                $stake_score
            );
        }

        return $this->successResponse($members);
        
        /*
        $limit  = $request->limit ?? 50;

        $slide_value_uptime                = $request->uptime ?? 0;
        $slide_value_update_responsiveness = $request->update_responsiveness ?? 0;
        $slide_value_delegotors            = $request->delegators ?? 0;
        $slide_value_stake_amount          = $request->stake_amount ?? 0;
        $slide_delegation_rate             = $request->delegation_rate ?? 0;
        
        $max_uptime     = Node::max('uptime');
        $max_uptime     = $max_uptime * 100;
        $max_delegators = NodeInfo::max('delegators_count');

        if(!$max_delegators || $max_delegators < 1) {
            $max_delegators = 1;
        }

        $max_stake_amount = NodeInfo::max('total_staked_amount');

        if(!$max_stake_amount || $max_stake_amount < 1) {
            $max_stake_amount = 1;
        }

        $sort_key = $request->sort_key ?? 'created_at';

        $users = User::with(['metric', 'nodeInfo', 'profile'])
            ->whereHas('nodeInfo')
            ->where('role', 'member')
            ->where(function ($query) use ($search) {
                if ($search) {
                    $query->where('users.first_name', 'like', '%' . $search . '%')
                        ->orWhere('users.last_name',  'like', '%' . $search . '%');
                }
            })
            ->get();

        foreach ($users as $user) {
            unset($user['email_verified_at']);
            unset($user['last_login_at']);
            unset($user['last_login_ip_address']);
            unset($user['twoFA_login']);
            unset($user['twoFA_login_active']);

            $latest = Node::where(
                'node_address', 
                strtolower($user->public_address_node)
            )
                ->whereNotnull('protocol_version')
                ->orderBy('created_at', 'desc')
                ->first();

            if (!$latest) {
                $latest = new Node();
            }

            $user->status    = (
                isset($user->profile) && 
                isset($user->profile->status) ? 
                $user->profile->status : ''
            );

            $uptime_nodeInfo = $user->nodeInfo->uptime;
            $uptime_node     = (
                isset($latest->uptime) && 
                $latest->uptime ? 
                $latest->uptime * 100 : 
                null
            );

            $uptime_metric   = (
                isset($user->metric) && 
                isset($user->metric->uptime) ? 
                $user->metric->uptime : 
                null
            );

            $res_nodeInfo = $user->nodeInfo->update_responsiveness ?? null;
            $res_node     = $latest->update_responsiveness ?? null;
            $res_metric   = $user->metric->update_responsiveness ?? null;

            $uptime = (
                $uptime_nodeInfo ? 
                $uptime_nodeInfo : (
                    $uptime_node ? 
                    $uptime_node : (
                        $uptime_metric ? 
                        $uptime_metric : 
                        1
                    )
                )
            );

            $res = (
                $res_nodeInfo ? 
                $res_nodeInfo : (
                    $res_node ? 
                    $res_node : (
                        $res_metric ? 
                        $res_metric : 
                        0
                    )
                )
            );

            $delegation_rate        = (
                isset($user->nodeInfo->delegation_rate) && 
                $user->nodeInfo->delegation_rate ? 
                $user->nodeInfo->delegation_rate / 100 : 
                1
            );

            if ($delegation_rate    > 1) {
                $delegation_rate    = 1;
            }

            $delegators_count       = (
                isset($user->nodeInfo->delegators_count) && 
                $user->nodeInfo->delegators_count ? 
                $user->nodeInfo->delegators_count : 
                0
            );

            $total_staked_amount    = (
                isset($user->nodeInfo->total_staked_amount) && 
                $user->nodeInfo->total_staked_amount ? 
                $user->nodeInfo->total_staked_amount : 
                0
            );

            $uptime_score           = (float)(
                ($slide_value_uptime * $uptime) / 
                100
            );

            $delegation_rate_score  = (float)(
                (
                    $slide_delegation_rate * 
                    (1 - $delegation_rate)
                ) / 
                100
            );

            $delegators_count_score = (float)(
                ($delegators_count / $max_delegators) * 
                $slide_value_delegotors
            );

            $total_staked_amount_score = (float)(
                ($total_staked_amount / $max_stake_amount) * 
                $slide_value_stake_amount
            );

            $res_score = (float)(
                ($slide_value_update_responsiveness * $res) / 
                100
            );
            
            $user->uptime              = $uptime;
            $user->delegation_rate     = $delegation_rate;
            $user->delegators_count    = $delegators_count;
            $user->total_staked_amount = $total_staked_amount;
            $user->totalScore          = (
                $uptime_score              + 
                $delegation_rate_score     + 
                $delegators_count_score    + 
                $total_staked_amount_score + 
                $res_score
            );
        }

        $users = $users->sortByDesc($sort_key)->values();
        $users = Helper::paginate($users, $limit, $request->page);
        return $this->successResponse($users);
        */
    }

    public function getMemberDetail($id, Request $request) {
        $user = User::where('id', $id)->first();
        if (!$user || $user->role == 'admin') {
            return $this->errorResponse(
                __('api.error.not_found'), 
                Response::HTTP_NOT_FOUND
            );
        }

        Helper::getAccountInfoStandard($user);

        $current_era_id = Helper::getCurrentERAId();

        $response = [
            'id' => $user->id,
            'full_name' => $user->full_name,
            'avatar_url' => $user->avatar_url,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'email' => $user->email,
            'pseudonym' => $user->pseudonym,
            'telegram' => $user->telegram,
            'type' => $user->type,
            'entity_name' => $user->entity_name,
            'entity_type' => $user->entity_type,
            'entity_register_number' => $user->entity_register_number,
            'entity_register_country' => $user->entity_register_country,
            'entity_tax' => $user->entity_tax,
            'public_address_node' => $user->public_address_node,
            'node_verified_at' => $user->node_verified_at,
            'member_status' => $user->member_status,
            'message_content' => $user->message_content,
            'email_verified_at' => $user->email_verified_at,
            'kyc_verified_at' => $user->kyc_verified_at,
            'letter_verified_at' => $user->letter_verified_at,
            'letter_rejected_at' => $user->letter_rejected_at,
            'approve_at' => $user->approve_at,
            'role' => $user->role,
            'node_status' => $user->node_status
        ];

        if ($user->profile) {
            $response['profile'] = [
                'id' => $user->profile->id,
                'status' => $user->profile->status,
                'extra_status' => $user->profile->extra_status,
                'casper_association_kyc_hash' => $user->profile->casper_association_kyc_hash,
                'blockchain_name' => $user->profile->blockchain_name,
                'blockchain_desc' => $user->profile->blockchain_desc,
                'type' => $user->profile->type
            ];
        } else {
            $response['profile'] = [
                'type' => $user->type
            ];
        }

        $response['addresses'] = $user->addresses ?? [];

        foreach ($response['addresses'] as &$addressItem) {
            $temp = AllNodeData2::select([
                        'uptime',
                        'bid_delegators_count',
                        'bid_delegation_rate',
                        'bid_self_staked_amount',
                        'bid_total_staked_amount'
                    ])
                    ->where('public_key', $addressItem->public_address_node)
                    ->where('era_id', $current_era_id)
                    ->orderBy('id', 'desc')
                    ->first()
                    ->toArray();
            if ($temp) {
                foreach ($temp as $key => $value) {
                    if ($key == 'uptime') $value = round((float) $value, 2);
                    $addressItem->$key = $value;
                }
                $addressItem->update_responsiveness = 100;
            }
        }

        return $this->successResponse($response);
    }

    public function getCaKycHash($hash)
    {
        if (!ctype_xdigit($hash)) {
            return $this->errorResponse(
                __('api.error.not_found'), 
                Response::HTTP_NOT_FOUND
            );
        }

        $selection = DB::select("
            SELECT
            a.casper_association_kyc_hash AS proof_hash,
            b.reference_id, b.status, 
            c.pseudonym
            FROM profile AS a
            LEFT JOIN shuftipro AS b
            ON a.user_id = b.user_id
            LEFT JOIN users AS c
            ON b.user_id = c.id
            WHERE a.casper_association_kyc_hash = '$hash'
        ");
        
        return $this->successResponse($selection[0] ?? []);
    }

    public function getMyVotes(Request $request)
    {
        $user = auth()->user()->load(['pagePermissions']);
        if (Helper::isAccessBlocked($user, 'votes'))
            return $this->successResponse(['data' => []]);
        
        $limit = $request->limit ?? 50;
        $user  = auth()->user();
        $data  = VoteResult::where('vote_result.user_id', $user->id)
            ->join('ballot', function ($query) use ($user) {
                $query->on('vote_result.ballot_id', '=', 'ballot.id');
            })
            ->join('vote', function ($query) use ($user) {
                $query->on('vote.ballot_id', '=', 'vote_result.ballot_id');
            })
            ->select([
                'vote.*',
                'ballot.*',
                'vote_result.created_at as date_placed',
                'vote_result.type as voteType',
            ])->orderBy('vote_result.created_at', 'DESC')->paginate($limit);
        return $this->successResponse($data);
    }

    public function checkCurrentPassword(Request $request)
    {
        $user = auth()->user();

        if (Hash::check($request->current_password, $user->password)) {
            return $this->metaSuccess();
        } else {
            return $this->errorResponse(
                __('Invalid password'), 
                Response::HTTP_BAD_REQUEST
            );
        }
    }

    public function settingUser(Request $request)
    {
        $user = auth()->user();

        if ($request->new_password) {
            $user->password = bcrypt($request->new_password);
        }

        if ($request->username) {
            $checkUsername = User::where('username', $request->username)
                ->where('username', '!=', $user->username)
                ->first();

            if ($checkUsername) {
                return $this->errorResponse(
                    __('this username has already been taken'), 
                    Response::HTTP_BAD_REQUEST
                );
            }

            $user->username = $request->username;
        }
        
        if (isset($request->twoFA_login)) {
            $user->twoFA_login = $request->twoFA_login;
        }
        
        if ($request->email && $request->email != $user->email) {
            $emailParam = $request->email;

            $checkEmail = User::where(
                function ($query) use ($emailParam) {
                    $query->where('email', $emailParam)
                        ->orWhere('new_email', $emailParam);
                }
            )
                ->where('id', '!=', $user->id)
                ->first();
            
            $currentEmail = $user->email;
            $newEmail     = $request->email;

            if ($checkEmail) {
                return $this->errorResponse(
                    __('this email has already been taken'), 
                    Response::HTTP_BAD_REQUEST
                );
            }

            $user->new_email = $newEmail;

            // Current Email 
            $codeCurrentEmail = Str::random(6);
            $url              = $request->header('origin') ?? $request->root();
            $urlCurrentEmail  = (
                $url . 
                '/change-email/cancel-changes?code=' . 
                $codeCurrentEmail . 
                '&email=' . 
                urlencode($currentEmail)
            );

            $newMemberData = [
                'title'   => 'Are you trying to update your email?',
                'content' => 'You recently requested to update your email address with the Casper Association Portal. If this is correct, click the link sent to your new email address to activate it. <br> If you did not initiate this update, your account could be compromised. Click the button to cancel the change',
                'url'     => $urlCurrentEmail,
                'action'  => 'cancel'
            ];

            Mail::to($currentEmail)->send(
                new UserConfirmEmail(
                    $newMemberData['title'], 
                    $newMemberData['content'], 
                    $newMemberData['url'], 
                    $newMemberData['action']
                )
            );

            VerifyUser::where('email', $currentEmail)
                ->where('type', VerifyUser::TYPE_CANCEL_EMAIL)
                ->delete();

            $verify             = new VerifyUser();
            $verify->code       = $codeCurrentEmail;
            $verify->email      = $currentEmail;
            $verify->type       = VerifyUser::TYPE_CANCEL_EMAIL;
            $verify->created_at = now();
            $verify->save();

            // new email
            $codeNewEmail = Str::random(6);
            $urlNewEmail  = (
                $url . 
                '/change-email/confirm?code=' . 
                $codeNewEmail . 
                '&email=' . 
                urlencode($newEmail)
            );

            $newMemberData = [
                'title'   => 'You recently updated your email',
                'content' => 'You recently requested to update your email address with the Casper Association Portal. If this is correct, click the button below to confirm the change. <br> If you received this email in error, you can simply delete it',
                'url'     => $urlNewEmail,
                'action'  => 'confirm'
            ];

            Mail::to($newEmail)->send(
                new UserConfirmEmail(
                    $newMemberData['title'], 
                    $newMemberData['content'], 
                    $newMemberData['url'], 
                    $newMemberData['action']
                )
            );

            VerifyUser::where('email', $newEmail)
                ->where('type', VerifyUser::TYPE_CONFIRM_EMAIL)
                ->delete();

            $verify             = new VerifyUser();
            $verify->email      = $newEmail;
            $verify->code       = $codeNewEmail;
            $verify->type       = VerifyUser::TYPE_CONFIRM_EMAIL;
            $verify->created_at = now();
            $verify->save();
        }
        $user->save();

        return $this->successResponse($user);
    }

    public function cancelChangeEmail(Request $request)
    {
        $verify = VerifyUser::where('email', $request->email)
            ->where('type', VerifyUser::TYPE_CANCEL_EMAIL)
            ->where('code', $request->code)
            ->first();

        if ($verify) {
            $user = User::where('email', $request->email)->first();

            if ($user) {
                $user->new_email = null;
                $user->save();
                $verify->delete();

                VerifyUser::where('email', $user->new_email)
                    ->where('type', VerifyUser::TYPE_CONFIRM_EMAIL)
                    ->delete();

                return $this->successResponse($user);
            }
            return $this->errorResponse(
                __('Fail cancel change email'), 
                Response::HTTP_BAD_REQUEST
            );
        }
        return $this->errorResponse(
            __('Fail cancel change email'), 
            Response::HTTP_BAD_REQUEST
        );
    }

    public function confirmChangeEmail(Request $request)
    {
        $verify = VerifyUser::where('email', $request->email)
            ->where('type', VerifyUser::TYPE_CONFIRM_EMAIL)
            ->where('code', $request->code)
            ->first();

        if ($verify) {
            $user = User::where('new_email', $request->email)->first();

            if ($user) {
                VerifyUser::where('email',  $user->email)
                    ->where('type', VerifyUser::TYPE_CANCEL_EMAIL)
                    ->delete();

                $user->new_email = null;
                $user->email     = $request->email;
                $user->save();
                $verify->delete();
                return $this->successResponse($user);
            }
            return $this->errorResponse(
                __('Fail confirm change email'), 
                Response::HTTP_BAD_REQUEST
            );
        }
        return $this->errorResponse(
            __('Fail confirm change email'), 
            Response::HTTP_BAD_REQUEST
        );
    }

    public function checkLogin2FA(Request $request)
    {
        $user   = auth()->user();
        $verify = VerifyUser::where('email', $user->email)
            ->where('type', VerifyUser::TYPE_LOGIN_TWO_FA)
            ->where('code', $request->code)
            ->first();

        if ($verify) {
            $verify->delete();
            $user->twoFA_login_active = 0;
            $user->save();
            return $this->metaSuccess();
        }

        return $this->errorResponse(
            __('Fail check twoFA code'), 
            Response::HTTP_BAD_REQUEST
        );
    }

    public function resend2FA()
    {
        $user = auth()->user();

        if ($user->twoFA_login == 1) {
            VerifyUser::where('email', $user->email)
                ->where('type', VerifyUser::TYPE_LOGIN_TWO_FA)
                ->delete();

            $code               = Str::random(6);
            $verify             = new VerifyUser();
            $verify->email      = $user->email;
            $verify->type       = VerifyUser::TYPE_LOGIN_TWO_FA;
            $verify->code       = $code;
            $verify->created_at = now();
            $verify->save();
            Mail::to($user)->send(new LoginTwoFA($code));
            return $this->metaSuccess();
        }

        return $this->errorResponse(
            __('Please enable 2Fa setting'), 
            Response::HTTP_BAD_REQUEST
        );
    }

    public function getLockRules()
    {
        $user = auth()->user();

        $ruleKycNotVerify = LockRules::where('type', 'kyc_not_verify')
            ->where('is_lock', 1)
            ->orderBy('id', 'ASC')
            ->select(['id', 'screen'])
            ->get();

        $ruleKycNotVerify1 = array_map(
            function ($object) {
                return $object->screen;
            }, 
            $ruleKycNotVerify->all()
        );

        $ruleStatusIsPoor = LockRules::where('type', 'status_is_poor')
            ->where('is_lock', 1)
            ->orderBy('id', 'ASC')->select(['id', 'screen'])
            ->get();

        $ruleStatusIsPoor1 = array_map(
            function ($object) {
                return $object->screen;
            }, 
            $ruleStatusIsPoor->all()
        );

        $data = [
            'kyc_not_verify' => $ruleKycNotVerify1,
            'status_is_poor' => $ruleStatusIsPoor1,
            'node_status'    => $user->node_status
        ];
        return $this->successResponse($data);
    }

    public function getListNodesBy(Request $request)
    {
        $user = auth()->user();
        $addresses = $user->addresses ?? [];

        $current_era_id = Helper::getCurrentERAId();
        
        foreach ($addresses as &$addressItem) {
            $temp = AllNodeData2::select([
                        'uptime',
                        'bid_delegators_count',
                        'bid_delegation_rate',
                        'bid_self_staked_amount',
                        'bid_total_staked_amount'
                    ])
                    ->where('public_key', $addressItem->public_address_node)
                    ->where('era_id', $current_era_id)
                    ->orderBy('id', 'desc')
                    ->first()
                    ->toArray();
            if ($temp) {
                foreach ($temp as $key => $value) {
                    if ($key == 'uptime') $value = round((float) $value, 2);
                    $addressItem->$key = $value;
                }
                $addressItem->update_responsiveness = 100;
            }
        }

        return $this->successResponse([
            'addresses' => $addresses,
        ]);
    }

    /*
    public function getListNodes(Request $request)
    {
        $user    = auth()->user();
        $user_id = $user->id;

        $current_era_id = DB::select("
            SELECT era_id
            FROM all_node_data2
            ORDER BY era_id DESC
            LIMIT 1
        ");
        $current_era_id = (int) ($current_era_id[0]->era_id ?? 0);

        // define return object
        $return = array(
            "nodes"           => array(),
            "ranking"         => array(),
            "node_rank_total" => 100
        );

        $return["nodes"] = DB::select("
            SELECT
            a.public_address_node, 
            b.id,
            b.pseudonym, 
            c.blockchain_name, 
            c.blockchain_desc 
            FROM user_addresses AS a 
            JOIN users AS b
            ON a.user_id = b.id 
            JOIN profile AS c 
            ON b.id = c.user_id 
            WHERE b.id = $user_id
        ");

        // find rank
        $ranking = DB::select("
            SELECT
            public_key, uptime,
            bid_delegators_count,
            bid_delegation_rate,
            bid_total_staked_amount
            FROM all_node_data2
            WHERE era_id       = $current_era_id
            AND in_current_era = 1
            AND in_next_era    = 1
            AND in_auction     = 1
        ");
        $max_delegators       = 0;
        $max_stake_amount     = 0;

        foreach ($ranking as $r) {
            if ((int)$r->bid_delegators_count > $max_delegators) {
                $max_delegators   = (int)$r->bid_delegators_count;
            }

            if ((int)$r->bid_total_staked_amount > $max_stake_amount) {
                $max_stake_amount = (int)$r->bid_total_staked_amount;
            }
        }

        foreach ($ranking as $r) {
            $uptime_score     = (
                25 * (float)$r->uptime
            ) / 100;
            $uptime_score     = $uptime_score < 0 ? 0 : $uptime_score;

            $fee_score        = (
                25 * 
                (1 - ((float)$r->bid_delegation_rate / 100))
            );
            $fee_score        = $fee_score < 0 ? 0 : $fee_score;

            $count_score      = (
                (float)$r->bid_delegators_count / 
                $max_delegators
            ) * 25;
            $count_score      = $count_score < 0 ? 0 : $count_score;

            $stake_score      = (
                (float)$r->bid_total_staked_amount / 
                $max_stake_amount
            ) * 25;
            $stake_score      = $stake_score < 0 ? 0 : $stake_score;

            $return["ranking"][$r->public_key] = (
                $uptime_score +
                $fee_score    +
                $count_score  + 
                $stake_score
            );
        }

        uasort(
            $return["ranking"],
            function($x, $y) {
                if ($x == $y) {
                    return 0;
                }

                return ($x > $y) ? -1 : 1;
            }
        );

        $sorted_ranking = array();
        $i = 1;

        foreach ($return["ranking"] as $public_key => $score) {
            $sorted_ranking[$public_key] = $i;
            $i += 1;
        }

        $return["ranking"]         = $sorted_ranking;
        $return["node_rank_total"] = count($sorted_ranking);

        return $this->successResponse($return);


        $limit = $request->limit ?? 50;

        $nodes = UserAddress::select([
            'users.id as user_id',
            'users.pseudonym',
            'user_addresses.public_address_node',
            'user_addresses.is_fail_node',
            'user_addresses.rank',
            'profile.blockchain_name',
            'profile.blockchain_desc',
        ])
            ->leftJoin('users', 'users.id', '=', 'user_addresses.user_id')
            ->leftJoin('profile', 'profile.user_id', '=', 'users.id')
            ->where('users.banned', 0)
            ->whereNotNull('users.public_address_node')
            ->orderBy('user_addresses.rank', 'asc')
            ->paginate($limit);

        return $this->successResponse($nodes);
    }
    */

    public function infoDashboard()
    {
        $user          = auth()->user();
        $delegators    = 0;
        $stake_amount  = 0;
        $lower_address = strtolower($user->public_address_node);

        $nodeInfo = DB::select("
            SELECT *
            FROM all_node_data2
            WHERE public_key = '$lower_address'
            ORDER BY era_id DESC
            LIMIT 1
        ");
        $nodeInfo = $nodeInfo[0] ?? null;

        if ($nodeInfo) {
            $delegators   = $nodeInfo->bid_delegators_count;
            $stake_amount = $nodeInfo->bid_total_staked_amount;
        }

        $totalPin = DiscussionPin::where('user_id', $user->id)->count();

        $response['totalNewDiscusstion'] = $user->new_threads;
        $response['totalPinDiscusstion'] = $totalPin;
        $response['rank']                = $user->rank;
        $response['delegators']          = $delegators;
        $response['stake_amount']        = $stake_amount;
        return $this->successResponse($response);
    }

    /*
    public function getEarningByNode($node)
    {
        $node     = strtolower($node);
        $user     = User::where('public_address_node', $node)->first();

        $nodeInfo = DB::select("
            SELECT *
            FROM all_node_data2
            WHERE public_key = '$node'
            ORDER BY era_id DESC
            LIMIT 1
        ");
        $nodeInfo = $nodeInfo[0] ?? null;

        // Calc earning
        $one_day_ago   = Carbon::now('UTC')->subHours(24);
        $daily_earningObject = DB::select("
            SELECT bid_self_staked_amount
            FROM all_node_data2
            WHERE public_key = '$node'
            AND created_at < '$one_day_ago'
            ORDER BY era_id DESC
            LIMIT 1
        ");
        $daily_earning = 0;

        if ($daily_earningObject && count($daily_earningObject) > 0) {
            $daily_earning = $daily_earningObject[0]->bid_self_staked_amount ?? 0;
        }

        if ($nodeInfo) {
            $daily_earning = $nodeInfo->bid_self_staked_amount - $daily_earning;
        } else {
            $daily_earning = -$daily_earning;
        }

        $daily_earning = $daily_earning < 0 ? 0 : $daily_earning;
        
        $mbs      = DB::select("
            SELECT mbs
            FROM mbs
            ORDER BY era_id DESC
            LIMIT 1
        ");
        $mbs      = (int)($mbs[0]->mbs ?? 0);

        if ($user && $nodeInfo) {
            return $this->successResponse([
                'daily_earning' => $daily_earning,
                'total_earning' => $daily_earning,
                'mbs'           => $mbs,
            ]);
        } else {
            return $this->successResponse([
                'mbs'           => $mbs,
            ]);
        }
    }
    */

    /*
    public function getChartEarningByNode($node)
    {
        $node = strtolower($node);
        $user = User::where('public_address_node', $node)->first();

        if ($user) {
            $nodeHelper   = new NodeHelper();
            $result_day   = $nodeHelper->getValidatorRewards($node, 'day');
            $result_week  = $nodeHelper->getValidatorRewards($node, 'week');
            $result_month = $nodeHelper->getValidatorRewards($node, 'month');
            $result_year  = $nodeHelper->getValidatorRewards($node, 'year');

            return $this->successResponse([
                'day'     => $result_day,
                'week'    => $result_week,
                'month'   => $result_month,
                'year'    => $result_year,
            ]);
        } else {
            return $this->successResponse(null);
        }
    }
    */

    public function getMembershipFile()
    {
        $membershipAgreementFile = MembershipAgreementFile::first();
        return $this->successResponse($membershipAgreementFile);
    }

    public function membershipAgreement()
    {
        $user = auth()->user();
        $user->membership_agreement = 1;
        $user->save();
        return $this->metaSuccess();
    }
}