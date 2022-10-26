<?php

namespace App\Http\Controllers\Api\V1;

use App\Console\Helper;

use App\Http\Controllers\Controller;
use App\Http\EmailerHelper;

use App\Mail\AdminAlert;
use App\Mail\ResetKYC;
use App\Mail\ResetPasswordMail;
use App\Mail\InvitationMail;

use App\Models\NodeInfo;
use App\Models\Ballot;
use App\Models\BallotFile;
use App\Models\BallotFileView;
use App\Models\Discussion;
use App\Models\DiscussionComment;
use App\Models\DocumentFile;
use App\Models\EmailerAdmin;
use App\Models\EmailerTriggerAdmin;
use App\Models\EmailerTriggerUser;
use App\Models\IpHistory;
use App\Models\LockRules;
use App\Models\MembershipAgreementFile;
use App\Models\Metric;
use App\Models\MonitoringCriteria;
use App\Models\Node;
use App\Models\OwnerNode;
use App\Models\Perk;
use App\Models\Permission;
use App\Models\Profile;
use App\Models\Setting;
use App\Models\Shuftipro;
use App\Models\ShuftiproTemp;
use App\Models\TokenPrice;
use App\Models\User;
use App\Models\UserAddress;
use App\Models\VerifyUser;
use App\Models\Vote;
use App\Models\VoteResult;
use App\Models\ContactRecipient;
use App\Models\AllNodeData2;

use App\Services\NodeHelper;

use Illuminate\Http\Request;
use Illuminate\Http\Response;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

use Carbon\Carbon;

use Aws\S3\S3Client;

class AdminController extends Controller
{
    public function allErasUser($id) {
        $user = User::where('id', $id)->first();

        if (!$user || $user->role == 'admin') {
            return $this->errorResponse(
                __('api.error.not_found'),
                Response::HTTP_NOT_FOUND
            );
        }

        $user_id = $id;

        // Get current era
        $current_era_id = DB::select("
            SELECT era_id
            FROM all_node_data2
            ORDER BY era_id DESC
            LIMIT 1
        ");
        $current_era_id = (int)($current_era_id[0]->era_id ?? 0);

        // define return object
        $return  = array(
            "column_count" => 2,
            "eras"         => array(
                array(
                    "era_start_time"        => '',
                    "addresses"             => array(
                        "0123456789abcdef"  => array(
                            "in_pool"       => false,
                            "rewards"       => 0
                        )
                    )
                )
            ),
            "addresses" => []
        );

        unset($return["eras"][0]);

        // get settings
        $voting_eras_to_vote = DB::select("
            SELECT value
            FROM settings
            WHERE name = 'voting_eras_to_vote'
        ");
        $voting_eras_to_vote = $voting_eras_to_vote[0] ?? array();
        $voting_eras_to_vote = (int)($voting_eras_to_vote->value ?? 0);

        $uptime_calc_size = DB::select("
            SELECT value
            FROM settings
            WHERE name = 'uptime_calc_size'
        ");
        $uptime_calc_size = $uptime_calc_size[0] ?? array();
        $uptime_calc_size = (int)($uptime_calc_size->value ?? 0);

        // get eras table data
        $era_minus_360 = $current_era_id - $uptime_calc_size;

        if ($era_minus_360 < 1) {
            $era_minus_360 = 1;
        }

        // get addresses data
        $addresses = DB::select("
            SELECT 
            a.public_key
            FROM all_node_data2 AS a
            JOIN user_addresses AS b
            ON a.public_key = b.public_address_node
            WHERE a.era_id  = $current_era_id
            AND b.user_id   = $user_id
            ORDER BY a.era_id DESC
        ");

        if (!$addresses) {
            $addresses = [];
        }

        foreach ($addresses as $address) {
            $return['addresses'][] = $address->public_key;
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

        if (!$eras) {
            $eras = array();
        }

        $sorted_eras = array();

        // for each node address's era
        foreach ($eras as $era) {
            $era_id               = $era->era_id ?? 0;
            $era_start_time       = $era->created_at ?? '';
            $public_key           = $era->public_key;

            if (!isset($sorted_eras[$era_id])) {
                $sorted_eras[$era_id] = array(
                    "era_start_time"  => $era_start_time,
                    "addresses"       => array()
                );
            }

            $sorted_eras
            [$era_id]
            ["addresses"]
            [$public_key] = array(
                "in_pool" => $era->in_auction,
                "rewards" => $era->uptime,
            );
        }

        $return["eras"] = $sorted_eras;
        $column_count   = 0;

        foreach ($return["eras"] as $era) {
            $count = $era["addresses"] ? count($era["addresses"]) : 0;

            if ($count > $column_count) {
                $column_count = $count;
            }
        }

        $return["column_count"] = $column_count + 1;

        // info($return);
        return $this->successResponse($return);
    }

    public function allEras() {
        // Get current era
        $current_era_id = DB::select("
            SELECT era_id
            FROM all_node_data2
            ORDER BY era_id DESC
            LIMIT 1
        ");
        $current_era_id = (int)($current_era_id[0]->era_id ?? 0);

        // define return object
        $return = array(
            "addresses" => array(
                "0123456789abcdef"          => array(
                    "uptime"                => 0,
                    "eras_active"           => 0,
                    "eras_since_bad_mark"   => $current_era_id,
                    "total_bad_marks"       => 0
                )
            ),
            "users" => array(
                array(
                    "user_id"       => 0,
                    "name"          => "Jason Stone",
                    "pseudonym"     => "jsnstone"
                )
            )
        );

        unset($return["addresses"]["0123456789abcdef"]);
        unset($return['users']);
        
        // get addresses data
        $addresses = DB::select("
            SELECT 
            a.public_key, a.uptime, b.user_id
            FROM all_node_data2 AS a
            JOIN user_addresses AS b
            ON a.public_key = b.public_address_node
            WHERE a.era_id  = $current_era_id
        ");

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

        $uptime_calc_size = DB::select("
            SELECT value
            FROM settings
            WHERE name = 'uptime_calc_size'
        ");
        $uptime_calc_size = $uptime_calc_size[0] ?? array();
        $uptime_calc_size = (int)($uptime_calc_size->value ?? 0);

        // for each member's node address
        foreach ($addresses as $address) {
            $p = $address->public_key ?? '';

            $total_bad_marks = DB::select("
                SELECT era_id
                FROM all_node_data2
                WHERE public_key = '$p'
                AND (
                    in_current_era = 0 OR
                    bid_inactive   = 1
                )
                ORDER BY era_id DESC
            ");

            $eras_since_bad_mark = $total_bad_marks[0] ?? array();
            $eras_since_bad_mark = $current_era_id - (int)($eras_since_bad_mark->era_id ?? 0);
            $total_bad_marks     = count((array)$total_bad_marks);

            $eras_active = DB::select("
                SELECT era_id
                FROM all_node_data2
                WHERE public_key = '$p'
                ORDER BY era_id ASC
                LIMIT 1
            ");

            $eras_active = (int)($eras_active[0]->era_id ?? 0);

            // Calculate historical_performance from past $uptime_calc_size eras
            $missed = 0;
            $in_current_eras = DB::select("
                SELECT in_current_era
                FROM all_node_data2
                WHERE public_key = '$p'
                ORDER BY era_id DESC
                LIMIT $uptime_calc_size
            ");

            $in_current_eras = $in_current_eras ? $in_current_eras : array();
            $window          = $in_current_eras ? count($in_current_eras) : 0;

            foreach ($in_current_eras as $c) {
                $in = (bool)($c->in_current_era ?? 0);

                if (!$in) {
                    $missed += 1;
                }
            }

            $uptime                 = (float)($address->uptime ?? 0);
            $historical_performance = round(
                ($uptime * ($window - $missed)) / $window,
                2,
                PHP_ROUND_HALF_UP
            );

            $return["addresses"][$p]   = array(
                "uptime"              => $historical_performance,
                "eras_active"         => $current_era_id - $eras_active,
                "eras_since_bad_mark" => $eras_since_bad_mark,
                "total_bad_marks"     => $total_bad_marks
            );
        }

        $users = DB::select("
            SELECT
            a.id, a.first_name, a.last_name, a.pseudonym
            FROM users AS a
            WHERE a.role      = 'member'
            AND a.has_address = 1
            AND a.banned      = 0;
        ");

        foreach ($users as $user) {
            $return["users"][] = array(
                "user_id"   => $user->id,
                "name"      => $user->first_name . ' ' . $user->last_name,
                "pseudonym" => $user->pseudonym,
            );
        }

        if (!$users) {
            $users = array();
        }

        // info($return);
        return $this->successResponse($return);
    }

    public function getNodesPage() {
        // Get current era
        $current_era_id = DB::select("
            SELECT era_id
            FROM all_node_data2
            ORDER BY era_id DESC
            LIMIT 1
        ");
        $current_era_id = (int)($current_era_id[0]->era_id ?? 0);

        // Define complete return object
        $return = array(
            "mbs" => 0,
            "ranking" => array(
                "0123456789abcdef" => 0
            ),
            "addresses" => array(
                "0123456789abcdef"          => array(
                    "stake_amount"          => 0,
                    "delegators"            => 0,
                    "uptime"                => 0,
                    "update_responsiveness" => 100,
                    "peers"                 => 0,
                    "daily_earning"         => 0,
                    "total_eras"            => 0,
                    "eras_since_bad_mark"   => $current_era_id,
                    "total_bad_marks"       => 0,
                    "faling"                => 0,
                    "validator_rewards"     => array(
                        "day"               => array(),
                        "week"              => array(),
                        "month"             => array(),
                        "year"              => array()
                    )
                )
            )
        );

        unset($return["ranking"]["0123456789abcdef"]);
        unset($return["addresses"]["0123456789abcdef"]);
        $nodeHelper = new NodeHelper();

        // get ranking
        $ranking = DB::select("
            SELECT
            public_key, 
            uptime,
            bid_delegators_count,
            bid_delegation_rate,
            bid_total_staked_amount
            FROM  all_node_data2
            WHERE era_id         = $current_era_id
            AND   in_current_era = 1
            AND   in_next_era    = 1
            AND   in_auction     = 1
        ");
        $max_delegators   = 0;
        $max_stake_amount = 0;

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

        $return["ranking"] = $sorted_ranking;

        // get user addresses
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
        ");

        if (!$addresses) {
            $addresses = array();
        }

        // for each member's node address
        foreach ($addresses as $address) {
            $a = $address->public_key ?? '';

            $total_bad_marks = DB::select("
                SELECT era_id
                FROM all_node_data2
                WHERE public_key = '$a'
                AND (
                    in_current_era = 0 OR
                    bid_inactive   = 1
                )
                ORDER BY era_id DESC
            ");

            $eras_since_bad_mark = $total_bad_marks[0] ?? array();
            $eras_since_bad_mark = $current_era_id - (int)($eras_since_bad_mark->era_id ?? 0);
            $total_bad_marks     = count((array)$total_bad_marks);

            $total_eras = DB::select("
                SELECT era_id
                FROM all_node_data2
                WHERE public_key = '$a'
                ORDER BY era_id ASC
                LIMIT 1
            ");

            $total_eras = (int)($total_eras[0]->era_id ?? 0);
            $total_eras = $current_era_id - $total_eras;

            // Calc earning
            $one_day_ago   = Carbon::now('UTC')->subHours(24);
            $daily_earning = DB::select("
                SELECT bid_self_staked_amount
                FROM all_node_data2
                WHERE public_key = '$a'
                AND created_at < '$one_day_ago'
                ORDER BY era_id DESC
                LIMIT 1
            ");
            $daily_earning = $daily_earning[0]->bid_self_staked_amount ?? 0;
            $daily_earning = $address->bid_self_staked_amount - $daily_earning;
            $daily_earning = $daily_earning < 0 ? 0 : $daily_earning;

            $earning_day   = $nodeHelper->getValidatorRewards($a, 'day');
            $earning_week  = $nodeHelper->getValidatorRewards($a, 'week');
            $earning_month = $nodeHelper->getValidatorRewards($a, 'month');
            $earning_year  = $nodeHelper->getValidatorRewards($a, 'year');

            if (
                $address->in_current_era == 0 ||
                $address->bid_inactive   == 1
            ) {
                $failing = 1;
            } else {
                $failing = 0;
            }

            $return["addresses"][$a] = array(
                "stake_amount"          => $address->bid_total_staked_amount,
                "delegators"            => $address->bid_delegators_count,
                "uptime"                => $address->uptime,
                "update_responsiveness" => 100,
                "peers"                 => $address->peers,
                "daily_earning"         => $daily_earning,
                "total_eras"            => $total_eras,
                "eras_since_bad_mark"   => $eras_since_bad_mark,
                "total_bad_marks"       => $total_bad_marks,
                "failing"               => $failing,
                "validator_rewards"     => array(
                    "day"               => $earning_day,
                    "week"              => $earning_week,
                    "month"             => $earning_month,
                    "year"              => $earning_year
                )
            );
        }

        // get mbs
        $mbs = DB::select("
            SELECT mbs
            FROM mbs
            ORDER BY era_id DESC
            LIMIT 1
        ");
        $return["mbs"] = (int)($mbs[0]->mbs ?? 0);

        // info($return);
        return $this->successResponse($return);
    }

    public function getUsers(Request $request)
    {
        /*
        SELECT 
            a.id, a.first_name, a.last_name, a.email,
            a.pseudonym, a.telegram, a.email_verified_at,
            a.entity_name, a.last_login_at, a.created_at,
            a.signature_request_id, a.node_verified_at,
            a.member_status, a.kyc_verified_at,
            b.dob, b.country_citizenship, b.country_residence,
            b.status AS profile_status, b.extra_status,
            b.type, b.casper_association_kyc_hash,
            b.blockchain_name, b.blockchain_desc,
            c.bid_delegators_count,
            c.bid_delegation_rate,
            c.bid_self_staked_amount,
            c.bid_total_staked_amount
            FROM users AS a
            JOIN profile AS b
            ON a.id = b.user_id
            JOIN user_addresses
            ON user_addresses.user_id = a.id
            JOIN all_node_data AS c
            ON c.public_key = user_addresses.public_address_node
        */

        $current_era_id = Helper::getCurrentERAId();

        $query = "
            SELECT 
            a.id, a.first_name, a.last_name, a.email,
            a.pseudonym, a.telegram, a.email_verified_at,
            a.entity_name, a.last_login_at, a.created_at,
            a.signature_request_id, a.node_status, a.node_verified_at,
            a.member_status, a.kyc_verified_at,
            b.dob, b.country_citizenship, b.country_residence,
            b.status AS profile_status, b.extra_status,
            b.type, b.casper_association_kyc_hash,
            b.blockchain_name, b.blockchain_desc
            FROM users AS a
            LEFT JOIN profile AS b
            ON a.id = b.user_id
            WHERE a.role = 'member'
        ";

        $sort_key = $request->get('sort_key');
        $sort_direction = $request->get('sort_direction');

        if ($sort_key && $sort_direction) {
            switch ($sort_key) {
                case 'id':
                    $sort_key = 'a.id';
                break;
                case 'membership_status':
                    $sort_key = 'b.status';
                break;
                case 'node_status':
                    $sort_key = 'a.node_status';
                break;
                case 'email':
                    $sort_key = 'a.email';
                break;
                case 'entity_name':
                    $sort_key = 'a.entity_name';
                break;
                case 'full_name':
                    $sort_key = 'a.first_name';
                break;
                case 'created_at':
                    $sort_key = 'a.created_at';
                break;
                default:
                    $sort_key = 'a.id';
                break;
            }
            $query .= " ORDER BY " . $sort_key . " " . $sort_direction;
        } else {
            $query .= " ORDER BY a.id asc";
        }

        $users = DB::select($query);

        if ($users) {
            foreach ($users as &$user) {
                $status = 'Not Verified';

                if ($user->profile_status == 'approved') {
                    $status = 'Verified';

                    if ($user->extra_status) {
                        $status = $user->extra_status;
                    }
                }

                $user->membership_status = $status;
                $userId = (int) $user->id;

                $temp = DB::select("
                    SELECT sum(a.bid_self_staked_amount) as self_staked_amount
                    FROM all_node_data2 as a
                    JOIN user_addresses as b ON b.public_address_node = a.public_key
                    WHERE b.user_id = $userId and a.era_id = $current_era_id
                ");

                $self_staked_amount = 0;
                if ($temp && count($temp) > 0) $self_staked_amount = (float) $temp[0]->self_staked_amount;
                $user->self_staked_amount = round($self_staked_amount, 2);
            }
        }

        return $this->successResponse($users);
    }

    public function getUserDetail($id)
    {
        $user = User::where('id', $id)->first();

        if (!$user || $user->role == 'admin') {
            return $this->errorResponse(
                __('api.error.not_found'),
                Response::HTTP_NOT_FOUND
            );
        }

        $user = $user->load(['pagePermissions', 'profile', 'shuftipro', 'shuftiproTemp']);
        
        $status = 'Not Verified';
        if ($user->profile && $user->profile->status == 'approved') {
            $status = 'Verified';
            if ($user->profile->extra_status) {
                $status = $user->profile->extra_status;
            }
        }

        $user->membership_status = $status;
        $user->metric            = Helper::getNodeInfo($user);

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
                $p = $addressItem->public_address_node;

                $total_bad_marks = DB::select("
                    SELECT era_id
                    FROM all_node_data2
                    WHERE public_key = '$p'
                    AND (
                        in_current_era = 0 OR
                        bid_inactive   = 1
                    )
                    ORDER BY era_id DESC
                ");
                $eras_active = DB::select("
                    SELECT era_id
                    FROM all_node_data2
                    WHERE public_key = '$p'
                    ORDER BY era_id ASC
                    LIMIT 1
                ");
                $eras_active = (int)($eras_active[0]->era_id ?? 0);

                $eras_since_bad_mark = $total_bad_marks[0] ?? array();
                $eras_since_bad_mark = $current_era_id - (int) ($eras_since_bad_mark->era_id ?? 0);
                $total_bad_marks     = count((array)$total_bad_marks);
                
                $addressItem->eras_since_bad_mark = $eras_since_bad_mark;
                $addressItem->total_bad_marks     = $total_bad_marks;
                $addressItem->eras_active = 0;
                if ($current_era_id > $eras_active) {
                    $addressItem->eras_active = $current_era_id - $eras_active;
                }
            }
        }

        $user->addresses = $addresses;

        return $this->successResponse($user);
    }

    public function infoDashboard(Request $request)
    {
        $current_era_id = Helper::getCurrentERAId();

        // define return object
        $return = array(
            "total_users"        => 0,
            "total_stake"        => 0,
            "total_delegators"   => 0,
            "avg_uptime"         => 0,
            "avg_responsiveness" => 0,
            "peers"              => 0,
            "new_users_ready"    => 0,
            "id_to_review"       => 0,
            "failing_nodes"      => 0,
            "perks_active"       => 0,
            "perks_views"        => 0,
            "new_comments"       => 0,
            "new_threads"        => 0
        );

        // get total users
        $total_users        = DB::select("
            SELECT pseudonym
            FROM users
            WHERE role      = 'member'
        ");
        $total_users        = $total_users ? count($total_users) : 0;

        // get total member stake
        $total_stake        = DB::select("
            SELECT 
            SUM(a.bid_total_staked_amount)
            FROM all_node_data2 AS a
            LEFT JOIN user_addresses AS b
            ON a.public_key = b.public_address_node
            WHERE a.era_id  = $current_era_id
            AND b.user_id IS NOT NULL
        ");
        $total_stake        = (array)($total_stake[0] ?? array());
        $total_stake        = (int)($total_stake['SUM(a.bid_total_staked_amount)'] ?? 0);

        // get total delegators across all member nodes
        $total_delegators   = DB::select("
            SELECT 
            SUM(a.bid_delegators_count)
            FROM all_node_data2 AS a
            LEFT JOIN user_addresses AS b
            ON a.public_key = b.public_address_node
            WHERE a.era_id  = $current_era_id
            AND b.user_id IS NOT NULL
        ");
        $total_delegators   = (array)($total_delegators[0] ?? array());
        $total_delegators   = (int)($total_delegators['SUM(a.bid_delegators_count)'] ?? 0);

        // get avg uptime of all user nodes
        $uptime_nodes = DB::select("
            SELECT 
            SUM(a.uptime) AS numerator,
            COUNT(a.uptime) AS denominator
            FROM all_node_data2 AS a
            LEFT JOIN user_addresses AS b
            ON a.public_key = b.public_address_node
            WHERE era_id    = $current_era_id
            AND b.user_id IS NOT NULL
        ");
        $avg_uptime         = (array)($uptime_nodes[0] ?? array());
        $avg_uptime         = (
            ($avg_uptime['numerator'] ?? 0) / 
            ($avg_uptime['denominator'] ? $avg_uptime['denominator'] : 1)
        );

        // get avg responsiveness
        $avg_responsiveness = 100;
        
        // get max peers
        $max_peers          = DB::select("
            SELECT MAX(a.port8888_peers) AS max_peers
            FROM all_node_data2 AS a
            LEFT JOIN user_addresses AS b
            ON a.public_key = b.public_address_node
            WHERE era_id    = $current_era_id
            AND b.user_id IS NOT NULL
        ");
        $max_peers          = (array)($max_peers[0] ?? array());
        $max_peers          = $max_peers['max_peers'] ?? 0;

        // get new users ready for admin review
        $new_users_ready    = User::where('banned', 0)
            ->where('role', 'member')
            ->where(function ($q) {
                $q->where('users.node_verified_at', null)
                    ->orWhere('users.letter_verified_at', null)
                    ->orWhere('users.signature_request_id', null);
            })->count();

        // get new users ready for kyc review
        $id_to_review       = User::where('users.role', 'member')
            ->where('banned', 0)
            ->join('profile', function ($query) {
                $query->on('profile.user_id', '=', 'users.id')
                        ->where('profile.status', 'pending');
            })
            ->join('shuftipro', 'shuftipro.user_id', '=', 'users.id')
            ->count();

        // get failing member nodes
        $failing_nodes      = DB::select("
            SELECT 
            a.bid_inactive, a.in_current_era
            FROM all_node_data2 AS a
            JOIN user_addresses AS b
            ON a.public_key = b.public_address_node
            WHERE a.era_id  = $current_era_id
            AND b.user_id IS NOT NULL
            AND (
                a.bid_inactive   = 1 OR
                a.in_current_era = 0
            )
        ");

        $timeframe_perk        = $request->timeframe_perk ?? 'last_7days';
        $timeframe_comments    = $request->timeframe_comments ?? 'last_7days';
        $timeframe_discussions = $request->timeframe_discussions ?? 'last_7days';

        // last_24hs, last_7days, last_30days, last_year
        if ($timeframe_perk == 'last_24hs') {
            $timeframe_perk =  Carbon::now('UTC')->subHours(24);
        } else if ($timeframe_perk == 'last_30days') {
            $timeframe_perk =  Carbon::now('UTC')->subDays(30);
        } else if ($timeframe_perk == 'last_year') {
            $timeframe_perk =  Carbon::now('UTC')->subYear();
        } else {
            $timeframe_perk =  Carbon::now('UTC')->subDays(7);
        }

        if ($timeframe_comments == 'last_24hs') {
            $timeframe_comments =  Carbon::now('UTC')->subHours(24);
        } else if ($timeframe_comments == 'last_30days') {
            $timeframe_comments =  Carbon::now('UTC')->subDays(30);
        } else if ($timeframe_comments == 'last_year') {
            $timeframe_comments =  Carbon::now('UTC')->subYear();
        } else {
            $timeframe_comments =  Carbon::now('UTC')->subDays(7);
        }

        if ($timeframe_discussions == 'last_24hs') {
            $timeframe_discussions =  Carbon::now('UTC')->subHours(24);
        } else if ($timeframe_discussions == 'last_30days') {
            $timeframe_discussions =  Carbon::now('UTC')->subDays(30);
        } else if ($timeframe_discussions == 'last_year') {
            $timeframe_discussions =  Carbon::now('UTC')->subYear();
        } else {
            $timeframe_discussions =  Carbon::now('UTC')->subDays(7);
        }

        // get active perks
        $perks_active = Perk::where('status', 'active')
            ->where('created_at', '>=', $timeframe_perk)
            ->count();

        // get total perk views
        $perks_views  = Perk::where('status', 'active')
            ->where('created_at', '>=', $timeframe_perk)
            ->sum('total_views');

        // get comments
        $new_comments = DiscussionComment::where(
            'created_at',
            '>=',
            $timeframe_comments
        )->count();

        // get discussions
        $new_threads  = Discussion::where(
            'created_at',
            '>=',
            $timeframe_discussions
        )->count();


        $return["total_users"]        = $total_users;
        $return["total_stake"]        = $total_stake;
        $return["total_delegators"]   = $total_delegators;
        $return["avg_uptime"]         = $avg_uptime;
        $return["avg_responsiveness"] = $avg_responsiveness;
        $return["peers"]              = $max_peers;
        $return["new_users_ready"]    = $new_users_ready;
        $return["id_to_review"]       = $id_to_review;
        $return["failing_nodes"]      = $failing_nodes;
        $return["perks_active"]       = $perks_active;
        $return["perks_views"]        = $perks_views;
        $return["new_comments"]       = $new_comments;
        $return["new_threads"]        = $new_threads;

        return $this->successResponse($return);
    }

    public function bypassApproveKYC($user_id)
    {
        $user_id    = (int) $user_id;
        $user       = User::find($user_id);
        $now        = Carbon::now('UTC');
        $admin_user = auth()->user();

        if ($user && $user->role == 'member') {
            $user->kyc_verified_at = $now;
            $user->approve_at = $now;
            $user->kyc_bypass_approval = 1;
            $user->save();

            $profile = Profile::where('user_id', $user_id)->first();

            if (!$profile) {
                $profile = new Profile;
                $profile->user_id = $user_id;
                $profile->first_name = $user->first_name;
                $profile->last_name = $user->last_name;
                $profile->type = $user->type;
            }

            $profile->status = 'approved';
            $profile->save();
            $shuftipro = Shuftipro::where('user_id', $user_id)->first();

            if (!$shuftipro) {
                $shuftipro = new Shuftipro;
                $shuftipro->user_id = $user_id;
                $shuftipro->reference_id = 'ByPass#' . time();
            }

            $shuftipro->is_successful = 1;
            $shuftipro->status = 'approved';
            $shuftipro->manual_approved_at = $now;
            $shuftipro->manual_reviewer = $admin_user->pseudonym;
            $shuftipro->save();
        }

        return $this->metaSuccess();
    }

    // get intake
    public function getIntakes(Request $request)
    {
        $limit = $request->limit ?? 50;
        $search = $request->search ?? '';
        $users =  User::select([
            'id', 
            'email', 
            'node_verified_at', 
            'letter_verified_at', 
            'signature_request_id', 
            'created_at',
            'first_name', 
            'last_name', 
            'letter_file', 
            'letter_rejected_at'
        ])
            ->where('banned', 0)
            ->where('role', 'member')
            ->where(function ($q) {
                $q->where('users.node_verified_at', null)
                    ->orWhere('users.letter_verified_at', null)
                    ->orWhere('users.signature_request_id', null);
            })
            ->where(function ($query) use ($search) {
                if ($search) {
                    $query->where('users.email', 'like', '%' . $search . '%');
                }
            })
            ->orderBy('users.id', 'desc')
            ->paginate($limit);

        return $this->successResponse($users);
    }

    public function submitBallot(Request $request)
    {
        try {
            DB::beginTransaction();
            $user = auth()->user();
            // Validator
            $validator = Validator::make($request->all(), [
                'title'       => 'required',
                'description' => 'required',
                'files'       => 'array',
                'files.*'     => 'file|max:10240|mimes:pdf,docx,doc,txt,rtf',
                'start_date'  => 'required',
                'start_time'  => 'required',
                'end_date'    => 'required',
                'end_time'    => 'required',
            ]);

            if ($validator->fails()) {
                return $this->validateResponse($validator->errors());
            }

            $time     = $request->time;
            $timeUnit = $request->time_unit;
            $mins     = 0;

            if ($timeUnit == 'minutes') {
                $mins = $time;
            } else if ($timeUnit == 'hours') {
                $mins = $time * 60;
            } else if ($timeUnit == 'days') {
                $mins = $time * 60 * 24;
            }

            $start         = Carbon::createFromFormat("Y-m-d H:i:s", Carbon::now('UTC'), "UTC");
            $now           = Carbon::now('UTC');
            $timeEnd       = $start->addMinutes($mins);
            $endTime       = $request->end_date . ' ' . $request->end_time;
            $endTimeCarbon = Carbon::createFromFormat('Y-m-d H:i:s', $endTime, 'EST');
            $endTimeCarbon->setTimezone('UTC');

            $ballot = new Ballot();
            $ballot->user_id     = $user->id;
            $ballot->title       = $request->title;
            $ballot->description = $request->description;
            $ballot->time_end    = $endTimeCarbon;
            $ballot->start_date  = $request->start_date;
            $ballot->start_time  = $request->start_time;
            $ballot->end_date    = $request->end_date;
            $ballot->end_time    = $request->end_time;
            $ballot->status      = 'active';
            $ballot->created_at  = $now;
            $ballot->save();

            $vote = new Vote();
            $vote->ballot_id     = $ballot->id;
            $vote->save();

            if ($request->hasFile('files')) {
                $files = $request->file('files');

                foreach ($files as $file) {
                    $name            = $file->getClientOriginalName();
                    $extension       = $file->getClientOriginalExtension();
                    $filenamehash    = md5(Str::random(10) . '_' . (string)time());
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
                        'Bucket'     => getenv('AWS_BUCKET'),
                        'Key'        => 'perks/' . $fileNameToStore,
                        'SourceFile' => $file
                    ]);

                    $ObjectURL             = (
                        $s3result['ObjectURL'] ?? 
                        getenv('SITE_URL') . '/not-found')
                    ;

                    $ballotFile            = new BallotFile();
                    $ballotFile->ballot_id = $ballot->id;
                    $ballotFile->name      = $name;
                    $ballotFile->path      = $ObjectURL;
                    $ballotFile->url       = $ObjectURL;
                    $ballotFile->save();
                }
            }

            DB::commit();
            return $this->metaSuccess();
        } catch (\Exception $ex) {
            DB::rollBack();

            return $this->errorResponse(
                'Submit ballot fail', 
                Response::HTTP_BAD_REQUEST, 
                $ex->getMessage()
            );
        }
    }

    public function editBallot($id, Request $request)
    {
        try {
            DB::beginTransaction();
            // Validator
            $validator = Validator::make($request->all(), [
                'title' => 'nullable',
                'description' => 'nullable',
                'files' => 'array',
                'files.*' => 'file|max:10240|mimes:pdf,docx,doc,txt,rtf',
                'file_ids_remove' => 'array',
                'start_date' => 'required',
                'start_time' => 'required',
                'end_date' => 'required',
                'end_time' => 'required',
            ]);

            if ($validator->fails()) {
                return $this->validateResponse($validator->errors());
            }

            $time     = $request->time;
            $timeUnit = $request->time_unit;
            $ballot   = Ballot::where('id', $id)->first();

            if (!$ballot) {
                return $this->errorResponse('Not found ballot', Response::HTTP_BAD_REQUEST);
            }

            if ($request->title) {
                $ballot->title = $request->title;
            }

            if ($request->description) {
                $ballot->description = $request->description;
            }

            $now                = Carbon::now('UTC');
            $ballot->created_at = $now;
            $ballot->time_end   = $request->end_date . ' ' . $request->end_time;
            $ballot->start_date = $request->start_date;
            $ballot->start_time = $request->start_time;
            $ballot->end_date   = $request->end_date;
            $ballot->end_time   = $request->end_time;
            $ballot->save();

            if ($request->hasFile('files')) {
                $files = $request->file('files');

                foreach ($files as $file) {
                    $name            = $file->getClientOriginalName();
                    $extension       = $file->getClientOriginalExtension();
                    $filenamehash    = md5(Str::random(10) . '_' . (string)time());
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
                        'Bucket'     => getenv('AWS_BUCKET'),
                        'Key'        => 'perks/'.$fileNameToStore,
                        'SourceFile' => $file
                    ]);

                    $ObjectURL             = (
                        $s3result['ObjectURL'] ?? 
                        getenv('SITE_URL').'/not-found'
                    );

                    $ballotFile            = new BallotFile();
                    $ballotFile->ballot_id = $ballot->id;
                    $ballotFile->name      = $name;
                    $ballotFile->path      = $ObjectURL;
                    $ballotFile->url       = $ObjectURL;
                    $ballotFile->save();
                }
            }

            if ($request->file_ids_remove) {
                foreach($request->file_ids_remove as $file_id) {
                    BallotFile::where('id', $file_id)
                        ->where('ballot_id', $id)
                        ->delete();
                }
            }
            DB::commit();
            return $this->metaSuccess();
        } catch (\Exception $ex) {
            DB::rollBack();

            return $this->errorResponse(
                'Submit ballot fail', 
                Response::HTTP_BAD_REQUEST, 
                $ex->getMessage()
            );
        }
    }

    public function getBallots(Request $request)
    {
        $limit          = $request->limit ?? 50;
        $status         = $request->status;
        $sort_key       = $request->sort_key ?? 'ballot.id';
        $sort_direction = $request->sort_direction ?? 'desc';
        $now            = Carbon::now('EST');
        $startDate      = $now->format('Y-m-d');
        $startTime      = $now->format('H:i:s');

        if ($status == 'active') {
            $ballots = Ballot::with(['user', 'vote'])
                ->where('ballot.status', 'active')
                ->where(function ($query) use ($startDate, $startTime) {
                    $query->where('start_date', '<', $startDate)
                            ->orWhere(function ($query) use ($startDate, $startTime) {
                                $query->where('start_date', $startDate)
                                        ->where('start_time', '<=', $startTime);
                            });
                })
                ->orderBy($sort_key, $sort_direction)
                ->paginate($limit);
        } else if ($status && $status == 'scheduled') {
            $ballots = Ballot::with(['user', 'vote'])
                ->where('ballot.status', 'active')
                ->where(function ($query) use ($startDate, $startTime) {
                    $query->where('start_date', '>', $startDate)
                            ->orWhere(function ($query) use ($startDate, $startTime) {
                                $query->where('start_date', $startDate)
                                        ->where('start_time', '>', $startTime);
                            });
                })
                ->orderBy($sort_key, $sort_direction)
                ->paginate($limit);
        } else if ($status && $status != 'active' && $status != 'scheduled') {
            $ballots = Ballot::with(['user', 'vote'])
                ->where('ballot.status', '!=', 'active')
                ->orderBy($sort_key, $sort_direction)
                ->paginate($limit);
        } else {
            $ballots = Ballot::with(['user', 'vote'])
                ->orderBy($sort_key, $sort_direction)
                ->paginate($limit);
        }
        return $this->successResponse($ballots);
    }

    public function getDetailBallot($id)
    {
        $ballot = Ballot::with(['user', 'vote', 'files'])
            ->where('id', $id)
            ->first();

        if (!$ballot) {
            return $this->errorResponse(
                'Not found ballot', 
                Response::HTTP_BAD_REQUEST
            );
        }

        return $this->successResponse($ballot);
    }

    public function cancelBallot($id)
    {
        $ballot = Ballot::where('id', $id)->first();

        if (!$ballot || $ballot->status != 'active') {
            return $this->errorResponse(
                'Cannot cancle ballot', 
                Response::HTTP_BAD_REQUEST
            );
        }

        $ballot->time_end = now();
        $ballot->status   = 'cancelled';
        $ballot->save();
        return $this->metaSuccess();
    }

    public function getBallotVotes($id, Request $request)
    {
        $limit = $request->limit ?? 50;
        $data  = VoteResult::where('ballot_id', '=', $id)
            ->with(['user', 'user.profile'])
            ->orderBy('created_at', 'ASC')
            ->paginate($limit);

        return $this->successResponse($data);
    }

    public function getViewFileBallot(Request $request, $fileId)
    {
        $limit = $request->limit ?? 50;
        $data  = BallotFileView::where('ballot_file_id', '=',  $fileId)
            ->with(['user', 'user.profile'])
            ->orderBy('created_at', 'ASC')
            ->paginate($limit);

        return $this->successResponse($data);
    }

    // Get Global Settings
    public function getGlobalSettings()
    {
        $items    = Setting::get();
        $settings = [];
        if ($items) {
            foreach ($items as $item) {
                $settings[$item->name] = $item->value;
            }
        }

        $ruleKycNotVerify = LockRules::where('type', 'kyc_not_verify')
            ->orderBy('id', 'ASC')
            ->select(['id', 'screen', 'is_lock'])
            ->get();
        $ruleStatusIsPoor = LockRules::where('type', 'status_is_poor')
            ->orderBy('id', 'ASC')
            ->select(['id', 'screen', 'is_lock'])
            ->get();
        
        $membershipAgreementFile = MembershipAgreementFile::first();

        $contactRecipients = ContactRecipient::orderBy('created_at', 'desc')->get();

        $data = [
            'globalSettings' => $settings,
            'lockRules' => [
                'kyc_not_verify' => $ruleKycNotVerify,
                'status_is_poor' => $ruleStatusIsPoor
            ],
            'membershipAgreementFile' => $membershipAgreementFile,
            'contactRecipients' => $contactRecipients
        ];

        return $this->successResponse($data);
    }

    // Update Global Settings
    public function updateGlobalSettings(Request $request)
    {
        $items = [
            'quorum_rate_ballot'        => ($request->quorum_rate_ballot ?? null),
            'uptime_warning'            => ($request->uptime_warning ?? null),
            'uptime_probation'          => ($request->uptime_probation ?? null),
            'uptime_correction_unit'    => ($request->uptime_correction_unit ?? null),
            'uptime_correction_value'    => ($request->uptime_correction_value ?? null),
            'uptime_calc_size'          => ($request->uptime_calc_size ?? null),
            'voting_eras_to_vote'       => ($request->voting_eras_to_vote ?? null),
            'voting_eras_since_redmark' => ($request->voting_eras_since_redmark ?? null),
            'redmarks_revoke'           => ($request->redmarks_revoke ?? null),
            'redmarks_revoke_calc_size'  => ($request->redmarks_revoke_calc_size ?? null),
            'responsiveness_warning'    => ($request->responsiveness_warning ?? null),
            'responsiveness_probation'  => ($request->responsiveness_probation ?? null)
        ];

        foreach ($items as $name => $value) {
            if ($value !== null) {
                $setting = Setting::where('name', $name)->first();

                if ($setting) {
                    $setting->value = $value;
                    $setting->save();
                } else {
                    $setting = new Setting();
                    $setting->name = $name;
                    $setting->value = $value;
                    $setting->save();
                }
            }
        }

        return $this->metaSuccess();
    }

    public function getSubAdmins(Request $request)
    {
        $limit  = $request->limit ?? 50;
        $admins = User::with(['permissions'])
            ->where(['role' => 'sub-admin'])
            ->orderBy('created_at', 'DESC')
            ->paginate($limit);

        return $this->successResponse($admins);
    }

    public function inviteSubAdmin(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
        ]);

        if ($validator->fails()) {
            return $this->validateResponse($validator->errors());
        }

        $isExist = User::where(['email' => $request->email])->count() > 0;

        if ($isExist) {
            return $this->errorResponse(
                'This email has already been used to invite another admin.', 
                Response::HTTP_BAD_REQUEST
            );
        }

        $code      = Str::random(6);
        $url       = getenv('SITE_URL');

        $inviteUrl = (
            $url . 
            '/register-sub-admin?code=' . 
            $code . 
            '&email=' . 
            urlencode($request->email)
        );

        VerifyUser::where('email', $request->email)
            ->where('type', VerifyUser::TYPE_INVITE_ADMIN)
            ->delete();

        $verify             = new VerifyUser();
        $verify->email      = $request->email;
        $verify->type       = VerifyUser::TYPE_INVITE_ADMIN;
        $verify->code       = $code;
        $verify->created_at = now();
        $verify->save();

        $admin = User::create([
            'first_name'    => 'faker',
            'last_name'     => 'faker',
            'email'         => $request->email,
            'password'      => '',
            'type'          => '',
            'member_status' => 'invited',
            'role'          => 'sub-admin'
        ]);

        $data = [
            ['name' => 'intake',  'is_permission' => 0, 'user_id' => $admin->id],
            ['name' => 'users',   'is_permission' => 0, 'user_id' => $admin->id],
            ['name' => 'ballots', 'is_permission' => 0, 'user_id' => $admin->id],
            ['name' => 'perks',   'is_permission' => 0, 'user_id' => $admin->id],
            ['name' => 'teams',   'is_permission' => 0, 'user_id' => $admin->id],
        ];

        Permission::insert($data);
        Mail::to($request->email)->send(new InvitationMail($inviteUrl));

        return $this->successResponse($admin);
    }

    public function changeSubAdminPermissions(Request $request, $id)
    {
        $validator    = Validator::make($request->all(), [
            'intake'  => 'nullable|in:0,1',
            'users'   => 'nullable|in:0,1',
            'ballots' => 'nullable|in:0,1',
            'perks'   => 'nullable|in:0,1',
            'teams'   => 'nullable|in:0,1',
        ]);

        if ($validator->fails()) {
            return $this->validateResponse($validator->errors());
        }

        $admin = User::find($id);

        if (
            $admin == null || 
            $admin->role != 'sub-admin'
        ) {
            return $this->errorResponse(
                'There is no admin user with this email', 
                Response::HTTP_BAD_REQUEST
            );
        }

        if (isset($request->intake)) {
            $permisstion = Permission::where('user_id', $id)
                ->where('name', 'intake')
                ->first();

            if ($permisstion) {
                $permisstion->is_permission = $request->intake;
                $permisstion->save();
            }
        }

        if (isset($request->users)) {
            $permisstion = Permission::where('user_id', $id)
                ->where('name', 'users')
                ->first();

            if ($permisstion) {
                $permisstion->is_permission = $request->users;
                $permisstion->save();
            }
        }

        if (isset($request->ballots)) {
            $permisstion = Permission::where('user_id', $id)
                ->where('name', 'ballots')
                ->first();

            if ($permisstion) {
                $permisstion->is_permission = $request->ballots;
                $permisstion->save();
            }
        }

        if (isset($request->perks)) {
            $permisstion = Permission::where('user_id', $id)
                ->where('name', 'perks')
                ->first();

            if ($permisstion) {
                $permisstion->is_permission = $request->perks;
                $permisstion->save();
            }
        }

        if (isset($request->teams)) {
            $permisstion = Permission::where('user_id', $id)
                ->where('name', 'teams')
                ->first();

            if ($permisstion) {
                $permisstion->is_permission = $request->teams;
                $permisstion->save();
            } else {
                $permisstion                = new Permission();
                $permisstion->is_permission = $request->teams;
                $permisstion->user_id       = $id;
                $permisstion->name          = 'teams';
                $permisstion->save();
            }
        }

        return $this->metaSuccess();
    }

    public function resendLink(Request $request, $id)
    {
        $admin = User::find($id);

        if (
            $admin == null || 
            $admin->role != 'sub-admin'
        ) {
            return $this->errorResponse(
                'No admin to be send invite link', 
                Response::HTTP_BAD_REQUEST
            );
        }

        $code      = Str::random(6);
        $url       = getenv('SITE_URL');

        $inviteUrl = (
            $url . 
            '/register-sub-admin?code=' . 
            $code . 
            '&email=' . 
            urlencode($admin->email)
        );

        VerifyUser::where('email', $admin->email)
            ->where('type', VerifyUser::TYPE_INVITE_ADMIN)
            ->delete();

        $verify             = new VerifyUser();
        $verify->email      = $admin->email;
        $verify->type       = VerifyUser::TYPE_INVITE_ADMIN;
        $verify->code       = $code;
        $verify->created_at = now();
        $verify->save();

        Mail::to($admin->email)->send(new InvitationMail($inviteUrl));

        return $this->metaSuccess();
    }

    public function resetSubAdminResetPassword(Request $request, $id)
    {
        $admin = User::find($id);

        if (
            $admin == null || 
            $admin->role != 'sub-admin'
        ) {
            return $this->errorResponse(
                'No admin to be revoked', 
                Response::HTTP_BAD_REQUEST
            );
        }

        $code     = Str::random(6);
        $url      = getenv('SITE_URL');
        $resetUrl = (
            $url . 
            '/update-password?code=' . 
            $code . 
            '&email=' . 
            urlencode($admin->email)
        );

        VerifyUser::where('email', $admin->email)
            ->where('type', VerifyUser::TYPE_RESET_PASSWORD)
            ->delete();

        $verify             = new VerifyUser();
        $verify->email      = $admin->email;
        $verify->type       = VerifyUser::TYPE_RESET_PASSWORD;
        $verify->code       = $code;
        $verify->created_at = now();
        $verify->save();

        Mail::to($admin->email)->send(new ResetPasswordMail($resetUrl));

        return $this->metaSuccess();
    }

    public function revokeSubAdmin(Request $request, $id)
    {
        $admin = User::find($id);

        if (
            $admin == null || 
            $admin->role != 'sub-admin'
        ) {
            return $this->errorResponse(
                'No admin to be revoked', 
                Response::HTTP_BAD_REQUEST
            );
        }

        $admin->member_status = 'revoked';
        $admin->banned        = 1;
        $admin->save();

        return $this->metaSuccess();
    }

    public function undoRevokeSubAdmin(Request $request, $id)
    {
        $admin = User::find($id);

        if (
            $admin == null || 
            $admin->role != 'sub-admin'
        ) {
            return $this->errorResponse(
                'No admin to be revoked', 
                Response::HTTP_BAD_REQUEST
            );
        }

        if ($admin->password) {
            $admin->member_status = 'active';
        } else {
            $admin->member_status = 'invited';
        }

        $admin->banned = 0;
        $admin->save();

        return $this->successResponse($admin);
    }

    public function getIpHistories(Request $request, $id)
    {
        $admin = User::find($id);

        if (
            $admin == null || 
            $admin->role != 'sub-admin'
        ) {
            return $this->errorResponse(
                'Not found admin', 
                Response::HTTP_BAD_REQUEST
            );
        }

        $limit     = $request->limit ?? 50;
        $ipAddress = IpHistory::where(['user_id' => $admin->id])
            ->orderBy('created_at', 'DESC')
            ->paginate($limit);

        return $this->successResponse($ipAddress);
    }

    public function approveIntakeUser($id)
    {
        $admin = auth()->user();
        $user  = User::where('id', $id)
            ->where('banned', 0)
            ->where('role', 'member')
            ->first();

        if ($user && $user->letter_file) {
            $user->letter_verified_at = now();
            $user->save();

            $emailerData = EmailerHelper::getEmailerData();

            EmailerHelper::triggerUserEmail(
                $user->email, 
                'Your letter of motivation is APPROVED', 
                $emailerData, 
                $user
            );

            if (
                $user->letter_verified_at && 
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
        }

        return $this->errorResponse(
            'Fail approved User', 
            Response::HTTP_BAD_REQUEST
        );
    }

    public function resetIntakeUser($id, Request $request)
    {
        $admin = auth()->user();
        $user  = User::where('id', $id)
            ->where('banned', 0)
            ->where('role', 'member')
            ->first();

        if ($user) {
            $user->letter_verified_at = null;
            $user->letter_file        = null;
            $user->letter_rejected_at = now();
            $user->save();
            $message = trim($request->get('message'));

            if (!$message) {
                return $this->errorResponse(
                    'please input message', 
                    Response::HTTP_BAD_REQUEST
                );
            }

            Mail::to($user->email)->send(new AdminAlert(
                'You need to submit letter again', 
                $message
            ));

            return $this->metaSuccess();
        }

        return $this->errorResponse(
            'Fail reset User', 
            Response::HTTP_BAD_REQUEST
        );
    }

    public function banUser($id)
    {
        $admin = auth()->user();
        $user  = User::where('id', $id)
            ->where('banned', 0)
            ->first();

        if ($user) {
            $user->banned = 1;
            $user->save();
            return $this->metaSuccess();
        }
        return $this->errorResponse(
            'Fail Ban User', 
            Response::HTTP_BAD_REQUEST
        );
    }

    public function removeUser($id, Request $request)
    {
        $user = User::where('id', $id)
            ->where('role', 'member')
            ->first();

        if ($user) {
            Shuftipro::where('user_id', $user->id)->delete();
            ShuftiproTemp::where('user_id', $user->id)->delete();
            Profile::where('user_id', $user->id)->delete();
            $user->delete();
            return $this->metaSuccess();
        }
        return $this->errorResponse(
            'Fail remove User', 
            Response::HTTP_BAD_REQUEST
        );
    }

    public function getVerificationUsers(Request $request)
    {
        $limit = $request->limit ?? 50;
        $users = User::where('users.role', 'member')
            ->where('banned', 0)
            ->join('shuftipro', 'shuftipro.user_id', '=', 'users.id')
            ->where('shuftipro.status', 'denied')
            ->orWhere('shuftipro.status', 'pending')
            ->select([
                'users.id as user_id',
                'users.created_at',
                'users.email',
                'shuftipro.status as kyc_status',
                'shuftipro.background_checks_result',
                'shuftipro.manual_approved_at'
            ])->paginate($limit);

        return $this->successResponse($users);
    }

    // Reset KYC
    public function resetKYC($id, Request $request)
    {
        $admin   = auth()->user();
        $message = trim($request->get('message'));

        if (!$message) {
            return $this->errorResponse(
                'please input message', 
                Response::HTTP_BAD_REQUEST
            );
        }

        $user = User::with(['profile'])
            ->where('id', $id)
            ->first();

        if ($user && $user->profile) {
            $user->profile->status = null;
            $user->profile->save();

            Profile::where('user_id', $user->id)->delete();
            Shuftipro::where('user_id', $user->id)->delete();
            ShuftiproTemp::where('user_id', $user->id)->delete();
            DocumentFile::where('user_id', $user->id)->delete();

            $user->kyc_verified_at = null;
            $user->approve_at      = null;
            $user->reset_kyc       = 1;
            $user->save();

            Mail::to($user->email)->send(new AdminAlert(
                'You need to submit KYC again', 
                $message
            ));

            return $this->metaSuccess();
        }

        return $this->errorResponse(
            'Fail Reset KYC', 
            Response::HTTP_BAD_REQUEST
        );
    }

    /*
    public function refreshLinks($id)
    {
        $url       = 'https://api.shuftipro.com/status';
        $user      = User::with('shuftipro')->find($id);
        $shuftipro = $user->shuftipro;

        if ($shuftipro) {
            $client_id  = config('services.shufti.client_id');
            $secret_key = config('services.shufti.client_secret');

            $response   = Http::withBasicAuth(
                $client_id, 
                $secret_key
            )->post($url, [
                'reference' => $shuftipro->reference_id
            ]);

            $data = $response->json();

            if (!$data || !is_array($data)) {
                return;
            }

            if (
                !isset($data['reference']) || 
                !isset($data['event'])
            ) {
                return $this->successResponse([
                    'success' => false,
                ]);
            }

            $events = [
                'verification.accepted', 
                'verification.declined',
                'request.timeout'
            ];

            if (!in_array($data['event'], $events)) {
                return $this->successResponse([
                    'success' => false,
                ]);
            }

            $proofs = isset($data['proofs']) ? $data['proofs'] : null;

            if (
                $proofs && 
                isset($proofs['document']) && 
                isset($proofs['document']['proof'])
            ) {
                $shuftipro->document_proof = $proofs['document']['proof'];
            }

            // Address Proof
            if (
                $proofs && 
                isset($proofs['address']) && 
                isset($proofs['address']['proof'])
            ) {
                $shuftipro->address_proof = $proofs['address']['proof'];
            }

            $shuftipro->save();

            return $this->successResponse([
                'success' => true,
            ]);
        }

        return $this->successResponse([
            'success' => false,
        ]);
    }
    */

    public function getVerificationDetail($id)
    {
        $user = User::with(['shuftipro', 'profile', 'documentFiles'])
            ->leftJoin('shuftipro', 'shuftipro.user_id', '=', 'users.id')
            ->where('users.id', $id)
            ->select([
                'users.*',
                'shuftipro.status as kyc_status',
                'shuftipro.background_checks_result',
                'shuftipro.data'
            ])
            ->where('users.role', 'member')
            ->where('banned', 0)
            ->first();

        if ($user) {
            if (
                isset($user->shuftipro) && 
                isset($user->shuftipro->address_proof) && 
                $user->shuftipro->address_proof
            ) {
                $url = Storage::disk('local')->url($user->shuftipro->address_proof);
                $user->shuftipro->address_proof_link = asset($url);
            }

            $declined_reason = '';

            try {
                $declined_reason = json_decode(json_decode($user->data))->declined_reason;
            } catch (Exception $e) {}

            $user->declined_reason = $declined_reason;

            return $this->successResponse($user);
        }

        return $this->errorResponse(
            'Fail get verification user', 
            Response::HTTP_BAD_REQUEST
        );
    }

    public function approveDocument($id)
    {
        $user = User::with(['profile'])
            ->where('id', $id)
            ->where('users.role', 'member')
            ->where('banned', 0)
            ->first();

        if ($user && $user->profile) {
            $user->profile->document_verified_at = now();
            $user->profile->save();
            return $this->metaSuccess();
        }

        return $this->errorResponse(
            'Fail approve document', 
            Response::HTTP_BAD_REQUEST
        );
    }

    // Add Emailer Admin
    public function addEmailerAdmin(Request $request)
    {
        $user  = Auth::user();
        $email = $request->get('email');

        if (!$email) {
            return [
                'success' => false,
                'message' => 'Invalid email address'
            ];
        }

        $record = EmailerAdmin::where('email', $email)->first();

        if ($record) {
            return [
                'success' => false,
                'message' => 'This emailer admin email address is already in use'
            ];
        }

        $record        = new EmailerAdmin;
        $record->email = $email;
        $record->save();

        return ['success' => true];
    }

    // Delete Emailer Admin
    public function deleteEmailerAdmin($adminId, Request $request)
    {
        $user = Auth::user();
        EmailerAdmin::where('id', $adminId)->delete();
        return ['success' => true];
    }

    // Get Emailer Data
    public function getEmailerData(Request $request)
    {
        $user         = Auth::user();
        $data         = [];
        $admins       = EmailerAdmin::where('id', '>', 0)
            ->orderBy('email', 'asc')
            ->get();

        $triggerAdmin = EmailerTriggerAdmin::where('id', '>', 0)
            ->orderBy('id', 'asc')
            ->get();

        $triggerUser  = EmailerTriggerUser::where('id', '>', 0)
            ->orderBy('id', 'asc')
            ->get();

        $data = [
            'admins'       => $admins,
            'triggerAdmin' => $triggerAdmin,
            'triggerUser'  => $triggerUser,
        ];

        return [
            'success' => true,
            'data'    => $data
        ];
    }

    // Update Emailer Trigger Admin
    public function updateEmailerTriggerAdmin($recordId, Request $request)
    {
        $user   = Auth::user();
        $record = EmailerTriggerAdmin::find($recordId);

        if ($record) {
            $enabled         = (int)$request->get('enabled');
            $record->enabled = $enabled;
            $record->save();
            return ['success' => true];
        }

        return ['success' => false];
    }

    // Update Emailer Trigger User
    public function updateEmailerTriggerUser($recordId, Request $request)
    {
        $user   = Auth::user();
        $record = EmailerTriggerUser::find($recordId);

        if ($record) {
            $enabled         = (int)$request->get('enabled');
            $content         = $request->get('content');
            $record->enabled = $enabled;

            if ($content) {
                $record->content = $content;
            }

            $record->save();

            return ['success' => true];
        }

        return ['success' => false];
    }

    public function updateLockRules(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'is_lock' => 'required|boolean'
        ]);

        if ($validator->fails()) {
            return $this->validateResponse($validator->errors());
        }

        $rule          = LockRules::where('id', $id)->first();
        $rule->is_lock = $request->is_lock;
        $rule->save();

        return ['success' => true];
    }

    // Get GraphInfo
    public function getGraphInfo(Request $request)
    {
        $user           = Auth::user();
        $graphDataDay   = 
        $graphDataWeek  = 
        $graphDataMonth = 
        $graphDataYear  = [];

        $timeDay   = Carbon::now('UTC')->subHours(24);
        $timeWeek  = Carbon::now('UTC')->subDays(7);
        $timeMonth = Carbon::now('UTC')->subDays(30);
        $timeYear  = Carbon::now('UTC')->subYear();

        $items = TokenPrice::orderBy('created_at', 'desc')
            ->where('created_at', '>=', $timeDay)
            ->get();

        if ($items && count($items)) {
            foreach ($items as $item) {
                $name = strtotime($item->created_at);
                $graphDataDay[$name] = number_format($item->price, 4);
            }
        }

        $items = TokenPrice::orderBy('created_at', 'desc')
            ->where('created_at', '>=', $timeWeek)
            ->get();

        if ($items && count($items)) {
            foreach ($items as $item) {
                $name = strtotime($item->created_at);
                $graphDataWeek[$name] = number_format($item->price, 4);
            }
        }

        $items = TokenPrice::orderBy('created_at', 'desc')
            ->where('created_at', '>=', $timeMonth)
            ->get();

        if ($items && count($items)) {
            foreach ($items as $item) {
                $name = strtotime($item->created_at);
                $graphDataMonth[$name] = number_format($item->price, 4);
            }
        }

        $items = TokenPrice::orderBy('created_at', 'desc')
            ->where('created_at', '>=', $timeYear)
            ->get();

        if ($items && count($items)) {
            foreach ($items as $item) {
                $name = strtotime($item->created_at);
                $graphDataYear[$name] = number_format($item->price, 4);
            }
        }

        return $this->successResponse([
            'day'   => $graphDataDay,
            'week'  => $graphDataWeek,
            'month' => $graphDataMonth,
            'year'  => $graphDataYear,
        ]);
    }

    public function uploadMembershipFile(Request $request)
    {
        try {
            // Validator
            $validator = Validator::make($request->all(), [
                'file' => 'required|mimes:pdf,docx,doc,txt,rtf|max:5000',
            ]);

            if ($validator->fails()) {
                return $this->validateResponse($validator->errors());
            }

            $filenameWithExt = $request->file('file')->getClientOriginalName();
            $filename        = pathinfo($filenameWithExt, PATHINFO_FILENAME);
            $extension       = $request->file('file')->getClientOriginalExtension();
            $filenamehash    = md5(Str::random(10) . '_' . (string)time());
            $fileNameToStore = $filenamehash . '.' . $extension;

            // S3 File Upload
            $S3 = new S3Client([
                'version'     => 'latest',
                'region'      => getenv('AWS_DEFAULT_REGION'),
                'credentials' => [
                    'key'     => getenv('AWS_ACCESS_KEY_ID'),
                    'secret'  => getenv('AWS_SECRET_ACCESS_KEY'),
                ],
            ]);

            $s3result = $S3->putObject([
                'Bucket'     => getenv('AWS_BUCKET'),
                'Key'        => 'client_uploads/' . $fileNameToStore,
                'SourceFile' => $request->file('file')
            ]);

            $ObjectURL = $s3result['ObjectURL'] ?? getenv('SITE_URL') . '/not-found';
            MembershipAgreementFile::where('id', '>', 0)->delete();
            $membershipAgreementFile       = new MembershipAgreementFile();
            $membershipAgreementFile->name = $filenameWithExt;
            $membershipAgreementFile->path = $ObjectURL;
            $membershipAgreementFile->url  = $ObjectURL;
            $membershipAgreementFile->save();
            DB::table('users')->update(['membership_agreement' => 0]);

            return $this->successResponse($membershipAgreementFile);
        } catch (\Exception $ex) {
            return $this->errorResponse(
                __('Failed upload file'), 
                Response::HTTP_BAD_REQUEST, 
                $ex->getMessage()
            );
        }
    }
}