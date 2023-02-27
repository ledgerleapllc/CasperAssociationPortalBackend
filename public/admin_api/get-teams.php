<?php
/**
 *
 * GET /admin/get-teams
 *
 * HEADER Authorization: Bearer
 *
 * @api
 *
 */
class AdminGetTeams extends Endpoints {
	function __construct() {
		global $db, $helper;

		require_method('GET');

		$auth       = authenticate_session(2);
		$admin_guid = $auth['guid'] ?? '';
		$teams_q    = "
			SELECT
			a.guid,
			a.role,
			a.created_at,
			a.admin_approved,
			a.email,
			b.membership,
			b.nodes,
			b.eras,
			b.discussions,
			b.ballots,
			b.perks,
			b.intake,
			b.users,
			b.teams,
			b.global_settings
			FROM users AS a
			LEFT JOIN permissions AS b
			ON a.guid = b.guid
			WHERE a.role LIKE '%admin'
		";

		if ($auth['role'] != 'admin') {
			$teams_q .= " AND a.guid != '$admin_guid'";
		}

		$teams = $db->do_select($teams_q);
		$teams = $teams ?? array();

		foreach ($teams as &$admin) {
			$guid = $admin['guid'] ?? '';
			$login_info = $db->do_select("
				SELECT
				logged_in_at,
				ip
				FROM login_attempts
				WHERE guid = '$guid'
				AND successful = 1
				ORDER BY logged_in_at DESC
				LIMIT 1
			");

			$login_info          = $login_info[0] ?? array();
			$admin["last_login"] = $login_info["logged_in_at"] ?? '';
			$admin["ip"]         = $login_info["ip"] ?? '';
		}

		// find and add pending administrative members
		$pending = $db->do_select("
			SELECT
			a.*,
			b.membership,
			b.nodes,
			b.eras,
			b.discussions,
			b.ballots,
			b.perks,
			b.intake,
			b.users,
			b.teams,
			b.global_settings
			FROM team_invites AS a
			LEFT JOIN permissions AS b
			ON a.guid = b.guid
			WHERE a.accepted_at IS NULL
		");

		$pending = $pending ?? array();

		foreach ($pending as $pend) {
			$teams[]         = array(
				"guid"            => $pend['guid'] ?? '',
				"role"            => "sub-admin",
				"email"           => $pend['email'] ?? '',
				"pending"         => "Pending",
				"admin_approved"  => 0,
				"created_at"      => $pend['created_at'] ?? '',
				"membership"      => $pend['membership'] ?? '',
				"nodes"           => $pend['nodes'] ?? '',
				"eras"            => $pend['eras'] ?? '',
				"discussions"     => $pend['discussions'] ?? '',
				"ballots"         => $pend['ballots'] ?? '',
				"perks"           => $pend['perks'] ?? '',
				"intake"          => $pend['intake'] ?? '',
				"users"           => $pend['users'] ?? '',
				"teams"           => $pend['teams'] ?? '',
				"global_settings" => $pend['global_settings'] ?? ''
			);
		}

		_exit(
			'success',
			$teams
		);
	}
}
new AdminGetTeams();
