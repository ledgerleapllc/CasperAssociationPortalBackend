<?php
/**
 *
 * GET /admin/get-dashboard
 *
 * HEADER Authorization: Bearer
 *
 * @api
 *
 */
class AdminGetDashboard extends Endpoints {
	function __construct() {
		global $db, $helper;

		require_method('GET');

		$auth           = authenticate_session(2);
		$user_guid      = $auth['guid'] ?? '';
		$current_era_id = $helper->get_current_era_id();
		$one_week_ago   = $helper->get_datetime(-604800);
		$one_day_ago    = $helper->get_datetime(-86400);

		// define return object
		$return = array(
			"pinned_discussions"   => 0,
			"key_intake"           => 0,
			"key_id_reviews"       => 0,
			"perks_viewed"         => 0,
			"new_comments"         => 0,
			"new_theads"           => 0,
			"total_users"          => 0,
			"total_stake"          => 0,
			"total_delegators"     => 0,
			"average_uptime"       => 0,
			"average_response"     => 100,
			"trending_discussions" => array()
		);

		// pinned discussions
		$pinned_discussions = $db->do_select("
			SELECT count(guid) AS dCount
			FROM discussion_pins
		");
		$return['pinned_discussions'] = (int)($pinned_discussions[0]['dCount'] ?? 0);

		$key_intake = $db->do_select("
			SELECT count(guid) AS uCount
			FROM  users
			WHERE admin_approved = 0
			AND   letter IS NOT NULL
		");
		$return['key_intake'] = (int)($key_intake[0]['uCount'] ?? 0);

		$key_id_reviews = $db->do_select("
			SELECT count(a.guid) AS kCount
			FROM shufti AS a
			JOIN users AS b
			ON a.guid = b.guid
			WHERE a.status = 'denied'
		");
		$return['key_id_reviews'] = (int)($key_id_reviews[0]['kCount'] ?? 0);

		//// $perks_viewed = ;

		$new_comments = $db->do_select("
			SELECT count(id) AS cCount
			FROM discussion_comments
			WHERE created_at > '$one_day_ago'
			OR updated_at    > '$one_day_ago'
		");
		$return['new_comments'] = (int)($new_comments[0]['cCount'] ?? 0);

		$new_theads = $db->do_select("
			SELECT count(id) AS tCount
			FROM discussions
			WHERE created_at > '$one_day_ago'
			OR updated_at    > '$one_day_ago'
		");
		$return['new_theads'] = (int)($new_theads[0]['tCount'] ?? 0);

		// trending discussions
		$trending_discussions = $db->do_select("
			SELECT
			a.id,
			a.title,
			a.created_at,
			a.updated_at,
			count(b.id) AS pins,
			count(d.id) AS comments
			FROM discussions AS a
			LEFT JOIN discussion_pins AS b
			ON a.id = b.discussion_id
			LEFT JOIN discussion_comments AS d
			ON a.id = d.discussion_id
			GROUP BY a.id
			ORDER BY comments DESC
			LIMIT 10
		");
		$return['trending_discussions'] = $trending_discussions ?? array();

		// total members
		$total_users = $db->do_select("
			SELECT count(guid) AS mCount
			FROM users
			WHERE role = 'user'
		");
		$return['total_users'] = (int)($total_users[0]['mCount'] ?? 0);

		// node data
		$nodes = $db->do_select("
			SELECT
			a.public_key,
			a.historical_performance,
			a.uptime,
			a.bid_delegators_count    AS delegators,
			a.bid_total_staked_amount AS total_stake
			FROM  all_node_data AS a
			JOIN  user_nodes    AS b
			ON    a.public_key     = b.public_key
			WHERE a.era_id         = $current_era_id
			AND   b.verified       IS NOT NULL
		");

		$nodes = $nodes ?? array();

		if ($nodes) {
			foreach ($nodes as $node) {
				$uptime      = (float)($node['uptime'] ?? 0);
				$delegators  = (int)($node['delegators'] ?? 0);
				$total_stake = (int)($node['total_stake'] ?? 0);
				$public_key  = $node['public_key'] ?? '';

				$return['average_uptime']   += $uptime;
				$return['total_stake']      += $total_stake;
				$return['total_delegators'] += $delegators;
			}

			$return['average_uptime'] = round($return['average_uptime'] / count($nodes), 2);
		}

		_exit(
			'success',
			$return
		);
	}
}
new AdminGetDashboard();
