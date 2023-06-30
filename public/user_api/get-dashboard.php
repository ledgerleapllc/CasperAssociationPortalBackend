<?php
/**
 *
 * GET /user/get-dashboard
 *
 * HEADER Authorization: Bearer
 *
 * @api
 *
 */
class UserGetDashboard extends Endpoints {
	function __construct() {
		global $db, $helper;

		require_method('GET');

		$auth           = authenticate_session(1);
		$user_guid      = $auth['guid'] ?? '';
		$current_era_id = $helper->get_current_era_id();
		$one_week_ago   = $helper->get_datetime(-604800);

		// define return object
		$return = array(
			"pinned_discussions"   => 0,
			"new_discussions"      => 0,
			"rank"                 => 0,
			"rank_total"           => 100,
			"total_stake"          => 0,
			"self_stake"           => 0,
			"delegators"           => 0,
			"uptime"               => 0,
			"eras_active"          => 0,
			"eras_since_redmark"   => 0,
			"total_redmarks"       => 0,
			"total_members"        => 0,
			"verified_members"     => array(),
			"association_members"  => array(),
			"trending_discussions" => array()
		);

		// pinned discussions
		$pinned_discussions = $db->do_select("
			SELECT count(guid) AS dCount
			FROM discussion_pins
			WHERE guid = '$user_guid'
		");
		$return['pinned_discussions'] = (int)($pinned_discussions[0]['dCount'] ?? 0);

		// new dicussions
		$new_discussions = $db->do_select("
			SELECT count(id) AS dCount
			FROM discussions
			WHERE created_at > '$one_week_ago'
		");
		$return['new_discussions'] = (int)($new_discussions[0]['dCount'] ?? 0);

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

		// verified good standing members/nodes
		$association_members = $db->do_select("
			SELECT
			a.public_key, a.status AS node_status,
			c.guid, c.pseudonym,
			d.status AS kyc_status
			FROM all_node_data  AS a
			JOIN user_nodes AS b
			ON a.public_key = b.public_key
			JOIN users AS c
			ON b.guid = c.guid
			JOIN shufti AS d
			ON c.guid = d.guid
			WHERE a.era_id = $current_era_id
			AND d.status = 'approved'
			AND c.role = 'user'
			AND b.verified IS NOT NULL
		");
		$return['association_members'] = $association_members ?? array();

		// total members
		$total_members = $db->do_select("
			SELECT count(guid) AS mCount
			FROM users
			WHERE role = 'user'
		");
		$return['total_members'] = (int)($total_members[0]['mCount'] ?? 0);

		// verified members
		$verified_members = $db->do_select("
			SELECT count(users.guid) AS mCount
			FROM users
			JOIN shufti
			ON users.guid = shufti.guid
			WHERE users.role = 'user'
			AND shufti.status = 'approved'
		");
		$return['verified_members'] = (int)($verified_members[0]['mCount'] ?? 0);

		// node data
		$nodes = $db->do_select("
			SELECT
			a.node_rank,
			a.public_key,
			a.uptime,
			a.bid_delegators_count    AS delegators,
			a.bid_self_staked_amount  AS self_stake,
			a.bid_total_staked_amount AS total_stake
			FROM  all_node_data AS a
			JOIN  user_nodes    AS b
			ON    a.public_key     = b.public_key
			WHERE a.era_id         = $current_era_id
			AND   b.guid           = '$user_guid'
			AND   b.verified       IS NOT NULL
		");

		$nodes = $nodes ?? array();

		if ($nodes) {
			foreach ($nodes as $node) {
				$rank        = (int)($node['node_rank'] ?? 0);
				$uptime      = (float)($node['uptime'] ?? 0);
				$delegators  = (int)($node['delegators'] ?? 0);
				$self_stake  = (int)($node['self_stake'] ?? 0);
				$total_stake = (int)($node['total_stake'] ?? 0);
				$public_key  = $node['public_key'] ?? '';

				if ($rank > $return['rank']) {
					$return['rank'] = $rank;
				}

				$node_era_data      = $helper->get_era_data($public_key);
				$eras_active        = $node_era_data['total_eras'];
				$eras_since_redmark = $node_era_data['eras_since_redmark'];
				$total_redmarks     = $node_era_data['total_redmarks'];

				$return['uptime']            += $uptime;
				$return['total_stake']       += $total_stake;
				$return['self_stake']        += $self_stake;
				$return['delegators']        += $delegators;
				$return['eras_active']        = $eras_active;
				$return['eras_since_redmark'] = $eras_since_redmark;
				$return['total_redmarks']     = $total_redmarks;
			}

			$return['uptime'] = round($return['uptime'] / count($nodes), 2);
		}

		_exit(
			'success',
			$return
		);
	}
}
new UserGetDashboard();
