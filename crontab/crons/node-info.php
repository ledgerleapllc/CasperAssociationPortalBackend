<?php
include_once(__DIR__.'/../../core.php');

global $helper, $db;

date_default_timezone_set('UTC');

$get_block    = $helper->get_block();
$current_era  = (int)($get_block['era_id'] ?? 0);

$historic_era = $db->do_select("
	SELECT era_id
	FROM all_node_data
	ORDER BY era_id DESC
	LIMIT 1
");

$historic_era      = (int)($historic_era[0]['era_id'] ?? 7000);
$historic_block    = 1;
$test_era          = 0;
$timestamp         = '1';
// $window            = (int)$helper->fetch_setting('uptime_calc_size');
// $window            = $window == 0 ? 1 : $window;
$window            = 360;
$uptime_warning    = (float)$helper->fetch_setting('uptime_warning');
$uptime_probation  = (float)$helper->fetch_setting('uptime_probation');
$correction_units  = (int)$helper->fetch_setting('correction_units');
$correction_metric = $helper->fetch_setting('correction_metric');
$correction_time   = 0;

switch ($correction_metric) {
	case 'minutes': $correction_time = $correction_units * 60;
	case 'hours':   $correction_time = $correction_units * 60 * 60;
	case 'days':    $correction_time = $correction_units * 60 * 60 * 24;
	default:        $correction_time = $correction_units;
}

// reductive binary search algorithm
// $m = 1073741824;
$m = 1;

// find max in range first
while ($timestamp != '') {
	$m         *= 2;
	$get_block  = $helper->get_block($m);
	$test_era   = (int)($get_block['era_id'] ?? 0);
	$timestamp  = $get_block['timestamp'] ?? '';
}

// run search algo
$historic_block = $m;
elog('finding max block ... '.$historic_block);

while ($test_era != $historic_era) {
	$get_block  = $helper->get_block($historic_block);
	$test_era   = (int)($get_block['era_id'] ?? 0);
	$timestamp  = $get_block['timestamp'] ?? '';

	if (
		!$timestamp || $timestamp == '' ||
		$test_era > $historic_era
	) {
		// elog('historic_block too high');
		$historic_block -= $m;
		if ($historic_block < 1) $historic_block = 1;
		$m = (int)($m / 2);
		continue;
	}

	if ($test_era < $historic_era) {
		// elog('historic_block too low');
		$historic_block += $m;
		$m = (int)($m / 2);
		continue;
	}
}

elog('found era: '.$test_era);

while ($current_era >= $historic_era) {
	// first see if we have this era's auction info
	$node_data = $db->do_select("
		SELECT era_id
		FROM all_node_data
		WHERE era_id = $historic_era
	");

	if ($node_data) {
		// era's auction info exists. do not need to fetch.
		elog('Already have era '.$historic_era.' data. skipping');
		$historic_era += 1;
	} else {
		$switch_block = $helper->get_block($historic_block);
		$era_id       = (int)($switch_block['era_id'] ?? 0);
		elog('Checking block '.$historic_block.' for era '.$historic_era.'. Found era '.$era_id);
		$block_hash   = $switch_block['block_hash'] ?? '';
		$timestamp    = $switch_block['timestamp'] ?? '';
		$timestamp    = date("Y-m-d H:i:s", strtotime($timestamp));

		if ($era_id == 0) {
			break;
		}

		if ($era_id == $historic_era) {
			// start timer
			$start_time = (int)time();

			// get auction info for this new detected era switch
			elog($era_id.' '.$block_hash);
			$historic_era   += 1;

			// very large object. aprx 10MB
			$auction_state          = $helper->get_auction($block_hash);
			$bids                   = $auction_state->bids ?? array();

			// get era ID
			$era_validators         = $auction_state->era_validators ?? array();
			$current_era_validators = $era_validators[0] ?? array();
			$next_era_validators    = $era_validators[1] ?? array();
			$current_era_id         = (int)($current_era_validators->era_id ?? 0);
			$next_era_id            = (int)($next_era_validators->era_id ?? 0);

			elog('Data aquired for era: '.$current_era_id);

			// set MBS array. minimum bid slot amount
			$MBS_arr = array();

			// get global uptimes from MAKE
			$global_uptime = $helper->retrieve_global_uptime($current_era_id);

			// Set validator key object
			$data = array(
				"era_id"     => $era_id,
				"validators" => array()
			);

			// prepare object used to calculate historical_performance
			$uptime_calc_era = $current_era_id - $window;
			$missed_eras     = array();

			$current_mbs     = $db->do_select("
				SELECT mbs
				FROM mbs
				WHERE era_id = $current_era_id
			");
			$current_mbs     = (int)($current_mbs[0]['mbs'] ?? 0);

			// MEMORY ALERT (~75k records)
			$in_current_eras = $db->do_select("
				SELECT public_key, in_current_era, bid_inactive
				FROM all_node_data
				WHERE era_id > $uptime_calc_era
				AND bid_total_staked_amount >= $current_mbs
			");
			$in_current_eras = $in_current_eras ?? array();
			// DOCUMENT MEMORY CONSUMPTION

			foreach ($in_current_eras as $e) {
				$public_key     = $e['public_key'] ?? '';
				$in_current_era = (int)($e['in_current_era'] ?? 0);
				$bid_inactive   = (int)($e['bid_inactive'] ?? 0);
				$missed         = 0;

				if (
					$in_current_era == 0 ||
					$bid_inactive   == 1
				) {
					$missed = 1;
				}

				if (!isset($missed_eras[$public_key])) {
					$missed_eras[$public_key] = 0;
				}

				$missed_eras[$public_key] += $missed;
			}

			// loop auction era
			elog('Looping auction era - Appending uptime, bid, and daily earnings data');

			foreach ($bids as $b) {
				$public_key               = strtolower($b->public_key ?? 'nill');
				$bid                      = $b->bid ?? array();

				// get self
				$self_staked_amount       = (int)($bid->staked_amount ?? 0);
				$delegation_rate          = (int)($bid->delegation_rate ?? 0);
				$bid_inactive             = (int)($bid->inactive ?? false);

				// calculate total stake, delegators + self stake
				$delegators               = (array)($bid->delegators ?? array());
				$delegators_count         = count($delegators);
				$delegators_staked_amount = 0;

				foreach ($delegators as $delegator) {
					$delegators_staked_amount += (int)($delegator->staked_amount ?? 0);
				}

				// convert and calculate stake amounts
				$delegators_staked_amount = (int)($delegators_staked_amount / 1000000000);
				$self_staked_amount       = (int)($self_staked_amount / 1000000000);
				$total_staked_amount      = $delegators_staked_amount + $self_staked_amount;

				// append to MBS array and pluck 100th place later
				$MBS_arr[$public_key]     = $total_staked_amount;

				// get node uptime from MAKE object
				$uptime = 0;

				foreach ($global_uptime as $uptime_array) {
					$fvid = strtolower($uptime_array->public_key ?? '');

					if($fvid == $public_key) {
						$uptime = (float)($uptime_array->average_score ?? 0);
						break;
					}
				}

				// calculate historical_performance on the fly
				$public_key_missed = $missed_eras[$public_key] ?? 0;
				$historical_performance = round(
					(float)($uptime * ($window - $public_key_missed) / $window),
					3
				);

				$node_status = 'online';
				$selection   = $db->do_select("
					SELECT
					b.type,
					b.created_at
					FROM user_nodes    AS a
					LEFT JOIN warnings AS b
					ON    a.guid       = b.guid
					WHERE a.public_key = '$public_key'
					AND   b.dismissed_at IS NULL
				");

				if ($selection) {
					$warning_status = $selection[0]['type'] ?? '';
					$probation_at   = $selection[0]['created_at'] ?? '';
					$diff           = $start_time - strtotime($probation_at.' UTC');
					$delta          = $correction_time - $diff;
					$delta          = $delta < 0 ? 0 : $delta;

					if ($uptime < $uptime_probation) {
						if (
							$warning_status == 'suspension' &&
							$delta          == 0
						) {
							$node_status = 'suspended';
						}

						if ($warning_status == 'probation') {
							$node_status = 'probation';
						}
					}
				}

				// define DB insert object for all public keys in this era
				$data["validators"][$public_key]   = array(
					"public_key"                   => $public_key,
					"uptime"                       => $uptime,
					"historical_performance"       => $historical_performance,
					"current_era_weight"           => 0,
					"next_era_weight"              => 0,
					"in_current_era"               => 0,
					"in_next_era"                  => 0,
					"in_auction"                   => 1,
					"bid_delegators_count"         => $delegators_count,
					"bid_delegation_rate"          => $delegation_rate,
					"bid_inactive"                 => $bid_inactive,
					"bid_self_staked_amount"       => $self_staked_amount,
					"bid_delegators_staked_amount" => $delegators_staked_amount,
					"bid_total_staked_amount"      => $total_staked_amount,
					"status"                       => $node_status
				);
			}

			// loop current era
			$current_validator_weights = $current_era_validators->validator_weights ?? array();
			elog('Appending current era validator weights');

			foreach ($current_validator_weights as $v) {
				$public_key = strtolower($v->public_key ?? '');
				$weight     = (int)($v->weight / 1000000000 ?? 0);

				if (isset($data["validators"][$public_key])) {
					$data
						["validators"]
						[$public_key]
						["current_era_weight"] = $weight;

					$data
						["validators"]
						[$public_key]
						["in_current_era"] = 1;
				} else {
					$data["validators"][$public_key] = array(
						"public_key"                   => $public_key,
						"uptime"                       => 0,
						"historical_performance"       => 0,
						"current_era_weight"           => $weight,
						"next_era_weight"              => 0,
						"in_current_era"               => 1,
						"in_next_era"                  => 0,
						"in_auction"                   => 0,
						"bid_delegators_count"         => 0,
						"bid_delegation_rate"          => 0,
						"bid_inactive"                 => 1,
						"bid_self_staked_amount"       => 0,
						"bid_delegators_staked_amount" => 0,
						"bid_total_staked_amount"      => 0,
						"status"                       => 'online'
					);
				}
			}

			// loop next era
			$next_validator_weights = $next_era_validators->validator_weights ?? array();
			elog('Appending next era validator weights');

			foreach ($next_validator_weights as $v) {
				$public_key = $v->public_key ?? '';
				$weight     = (int)($v->weight / 1000000000 ?? 0);

				if (isset($data["validators"][$public_key])) {
					$data
						["validators"]
						[$public_key]
						["next_era_weight"] = $weight;

					$data
						["validators"]
						[$public_key]
						["in_next_era"] = 1;
				} else {
					$data["validators"][$public_key] = array(
						"public_key"                   => $public_key,
						"uptime"                       => 0,
						"historical_performance"       => 0,
						"current_era_weight"           => $weight,
						"next_era_weight"              => 0,
						"in_current_era"               => 0,
						"in_next_era"                  => 1,
						"in_auction"                   => 0,
						"bid_delegators_count"         => 0,
						"bid_delegation_rate"          => 0,
						"bid_inactive"                 => 1,
						"bid_self_staked_amount"       => 0,
						"bid_delegators_staked_amount" => 0,
						"bid_total_staked_amount"      => 0,
						"status"                       => 'offline'
					);
				}
			}

			// Primary DB insertion (time consuming)
			elog('Saving validator objects to DB...');

			foreach ($data["validators"] as $v) {
				$v_public_key                   = $v["public_key"];
				$v_era_id                       = $data["era_id"];
				$v_uptime                       = $v["uptime"];
				$v_historical_performance       = $v["historical_performance"];
				$v_current_era_weight           = $v["current_era_weight"];
				$v_next_era_weight              = $v["next_era_weight"];
				$v_in_current_era               = $v["in_current_era"];
				$v_in_next_era                  = $v["in_next_era"];
				$v_in_auction                   = $v["in_auction"];
				$v_bid_delegators_count         = $v["bid_delegators_count"];
				$v_bid_delegation_rate          = $v["bid_delegation_rate"];
				$v_bid_inactive                 = $v["bid_inactive"];
				$v_bid_self_staked_amount       = $v["bid_self_staked_amount"];
				$v_bid_delegators_staked_amount = $v["bid_delegators_staked_amount"];
				$v_bid_total_staked_amount      = $v["bid_total_staked_amount"];
				$v_status                       = $v["status"];

				$db->do_query("
					INSERT INTO all_node_data (
						public_key,
						era_id,
						uptime,
						historical_performance,
						current_era_weight,
						next_era_weight,
						in_current_era,
						in_next_era,
						in_auction,
						bid_delegators_count,
						bid_delegation_rate,
						bid_inactive,
						bid_self_staked_amount,
						bid_delegators_staked_amount,
						bid_total_staked_amount,
						status,
						created_at
					) VALUES (
						'$v_public_key',
						$v_era_id,
						$v_uptime,
						$v_historical_performance,
						$v_current_era_weight,
						$v_next_era_weight,
						$v_in_current_era,
						$v_in_next_era,
						$v_in_auction,
						$v_bid_delegators_count,
						$v_bid_delegation_rate,
						$v_bid_inactive,
						$v_bid_self_staked_amount,
						$v_bid_delegators_staked_amount,
						$v_bid_total_staked_amount,
						'$v_status',
						'$timestamp'
					)
				");

				elog($v["public_key"].' - done');
			}

			// find MBS
			elog('Finding MBS for this era');
			rsort($MBS_arr);
			$MBS = 0;

			if (count($MBS_arr) > 0) {
				$MBS = (float)($MBS_arr[99] ?? $MBS_arr[count($MBS_arr) - 1]);
			}

			// save MBS in new table by current_era
			$check = $db->do_select("
				SELECT mbs
				FROM mbs
				WHERE era_id = $current_era_id
			");
			$check = $check[0] ?? null;

			if (!$check) {
				$db->do_query("
					INSERT INTO mbs (
						era_id,
						mbs,
						created_at
					) VALUES (
						$current_era_id,
						$MBS,
						'$timestamp'
					)
				");
			} else {
				$db->do_query("
					UPDATE mbs
					SET
					mbs = $MBS,
					updated_at = '$timestamp'
					WHERE era_id = $current_era_id
				");
			}

			// calculate ranks
			elog('Calculating validator ranking for this era');
			$ranking = $db->do_select("
				SELECT
				public_key, uptime,
				bid_delegators_count,
				bid_delegation_rate,
				bid_total_staked_amount
				FROM all_node_data
				WHERE era_id       = $era_id
				AND in_current_era = 1
				AND bid_inactive   = 0
			");
			$ranking          = $ranking ?? array();
			$ranking_output   = array();
			$max_delegators   = 0;
			$max_stake_amount = 0;

			foreach ($ranking as $r) {
				if ((int)$r['bid_delegators_count'] > $max_delegators) {
					$max_delegators   = (int)$r['bid_delegators_count'];
				}
				if ((int)$r['bid_total_staked_amount'] > $max_stake_amount) {
					$max_stake_amount = (int)$r['bid_total_staked_amount'];
				}
			}

			foreach ($ranking as $r) {
				$uptime_score = (float)(25 * (float)$r['uptime'] / 100);
				$uptime_score = $uptime_score < 0 ? 0 : $uptime_score;

				$fee_score    = 25 * (1 - (float)((float)$r['bid_delegation_rate'] / 100));
				$fee_score    = $fee_score < 0 ? 0 : $fee_score;

				$count_score  = (float)((float)$r['bid_delegators_count'] / $max_delegators) * 25;
				$count_score  = $count_score < 0 ? 0 : $count_score;

				$stake_score  = (float)((float)$r['bid_total_staked_amount'] / $max_stake_amount) * 25;
				$stake_score  = $stake_score < 0 ? 0 : $stake_score;

				$ranking_output[$r['public_key']] = (
					$uptime_score +
					$fee_score    +
					$count_score  +
					$stake_score
				);
			}

			uasort($ranking_output, function($x, $y) {
				if ($x == $y) {
					return 0;
				}
				return ($x > $y) ? -1 : 1;
			});

			// conditional update (fast)
			$query = "
				UPDATE all_node_data
				SET node_rank =
				CASE public_key
			";

			$i       = 1;
			$implode = '';

			foreach ($ranking_output as $public_key => $score) {
				$query   .= "WHEN '$public_key' THEN $i ";
				$implode .= "'$public_key', ";
				$i       += 1;
			}

			$implode = rtrim($implode, ", ");

			$query .= "ELSE  node_rank END ";
			$query .= "WHERE era_id = $era_id ";
			$query .= "AND   public_key IN($implode)";
			$db->do_query($query);

			// end timer
			$end_time = (int)time();

			elog("Time spent on era: ".($end_time - $start_time));
		}

		$historic_block += 8;
	}
}

elog('done');
