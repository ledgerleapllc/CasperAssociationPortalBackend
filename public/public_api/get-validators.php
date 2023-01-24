<?php
include_once('../../core.php');
/**
 *
 * GET /public/get-validators
 *
 * @api
 *
 */
class PublicGetValidators extends Endpoints {
	function __construct() {
		global $db, $helper;

		require_method('GET');

		$weight_uptime     = (int)(parent::$params['uptime'] ?? 0);
		$weight_fee        = (int)(parent::$params['fee'] ?? 0);
		$weight_delegators = (int)(parent::$params['delegators'] ?? 0);
		$weight_stake      = (int)(parent::$params['stake'] ?? 0);

		if ($weight_uptime == 0) {
			$weight_uptime = 1;
		}

		if ($weight_fee == 0) {
			$weight_fee = 1;
		}

		if ($weight_delegators == 0) {
			$weight_delegators = 1;
		}

		if ($weight_stake == 0) {
			$weight_stake = 1;
		}

		$current_era_id = $helper->get_current_era_id();
		$ranking        = array();

		$validators     = $db->do_select("
			SELECT
			a.public_key,
			a.uptime,
			a.bid_delegators_count     AS delegators,
			a.bid_delegation_rate      AS fee,
			a.bid_total_staked_amount  AS stake,
			c.pseudonym,
			c.created_at               AS registered_at,
			d.status                   AS kyc_status
			FROM all_node_data AS a
			JOIN user_nodes    AS b
			ON   a.public_key   = b.public_key
			JOIN users         AS c
			ON   b.guid         = c.guid
			LEFT JOIN shufti   AS d
			ON   c.guid         = d.guid
			WHERE a.era_id      = $current_era_id
			AND   b.verified   IS NOT NULL
		");

		$validators = $validators ?? array();

		$max_delegators   = 0;
		$max_stake_amount = 0;

		foreach ($validators as $v) {
			if ((int)$v['delegators'] > $max_delegators) {
				$max_delegators   = (int)$v['delegators'];
			}

			if ((int)$v['stake'] > $max_stake_amount) {
				$max_stake_amount = (int)$v['stake'];
			}
		}

		foreach ($validators as $v) {
			$uptime_score = (float)($weight_uptime * (float)$v['uptime'] / 100);
			$uptime_score = $uptime_score < 0 ? 0 : $uptime_score;

			$fee_score    = $weight_fee * (1 - (float)((float)$v['fee'] / 100));
			$fee_score    = $fee_score < 0 ? 0 : $fee_score;

			$count_score  = (float)((float)$v['delegators'] / $max_delegators) * $weight_delegators;
			$count_score  = $count_score < 0 ? 0 : $count_score;

			$stake_score  = (float)((float)$v['stake'] / $max_stake_amount) * $weight_stake;
			$stake_score  = $stake_score < 0 ? 0 : $stake_score;

			$ranking[$v['public_key']] = (
				$uptime_score + 
				$fee_score    + 
				$count_score  + 
				$stake_score
			);
		}

		uasort($ranking, function($x, $y) {
			if ($x == $y) {
				return 0;
			}
			return ($x > $y) ? -1 : 1;
		});

		$sorted_ranking = [];
		$i = 1;

		foreach ($ranking as $public_key => $score) {
			$sorted_ranking[$public_key] = $i;
			$i += 1;
		}

		$ranking = $sorted_ranking;


		foreach ($validators as &$v) {
			$v['rank'] = $ranking[$v['public_key']] ?? 0;
		}

		_exit(
			'success',
			$validators
		);
	}
}
new PublicGetValidators();