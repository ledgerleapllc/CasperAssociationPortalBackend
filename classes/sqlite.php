<?php
/**
 * Sqlite database class. Purposed for unit test workflow
 *
 * @author @blockchainthomas
 *
 * @method array|null  do_select()
 * @method bool        do_query()
 * @method null        check_integrity()
 *
 */
class SqliteDB extends SQLite3 {
	function __construct() {
		$this->open(BASE_DIR.'/database.sqlite');
	}

	function __destruct() {
		$this->close();
	}

	/**
	 * Do DB selection
	 *
	 * @param string $query
	 * @return array $return
	 *
	 */
	public function do_select($query) {
		$return = null;
		$ret = $this->query($query);

		if ($ret) {
			while($row = $ret->fetchArray(SQLITE3_ASSOC)) {
				$return[] = $row;
			}
		}

		return $return;
	}

	/**
	 * Do DB query
	 *
	 * @param string $query
	 * @return bool
	 *
	 */
	public function do_query($query) {
		$flag = $this->exec($query);
		return $flag;
	}

	/**
	 * Check DB integrity
	 */
	public function check_integrity() {
		global $helper;

		$query = "
			SELECT name
			FROM sqlite_master
			WHERE type='table'
			ORDER BY name
		";
		$tables = $this->do_select($query);
		$all_tables = array();

		if($tables) {
			foreach ($tables as $table) {
				$all_tables[] = $table['Tables_in_'.DB_NAME] ?? $table['name'] ?? '';
			}
		}

		if(!in_array('schedule', $all_tables)) {
			$query = "
				CREATE TABLE `schedule` (
					`template_id` varchar(100) DEFAULT NULL,
					`subject` varchar(255) DEFAULT '',
					`body` text,
					`link` text,
					`email` varchar(255) DEFAULT NULL,
					`created_at` timestamp NULL DEFAULT NULL,
					`sent_at` timestamp NULL DEFAULT NULL,
					`complete` int DEFAULT '0'
				);
			";
			$this->do_query($query);
			elog('DB: Created schedule table');
		}

		if(!in_array('sessions', $all_tables)) {
			$query = "
				CREATE TABLE `sessions` (
					`guid` varchar(36) NOT NULL,
					`bearer` text,
					`created_at` timestamp NULL DEFAULT NULL,
					`expires_at` timestamp NULL DEFAULT NULL
				);
			";
			$this->do_query($query);
			elog('DB: Created sessions table');
		}

		if(!in_array('settings', $all_tables)) {
			$query = "
				CREATE TABLE `settings` (
					`name` varchar(64) DEFAULT NULL,
					`value` text
				);
			";
			$this->do_query($query);
			elog('DB: Created settings table');
		}

		if(!in_array('subscriptions', $all_tables)) {
			$query = "
				CREATE TABLE `subscriptions` (
					`guid` varchar(36) NOT NULL,
					`email` varchar(255) DEFAULT NULL,
					`created_at` timestamp NULL DEFAULT NULL,
					`source` varchar(32) DEFAULT NULL
				);
			";
			$this->do_query($query);
			elog('DB: Created subscriptions table');
		}

		if(!in_array('authorized_devices', $all_tables)) {
			$query = "
				CREATE TABLE `authorized_devices` (
					`guid` varchar(36) NOT NULL,
					`ip` varchar(256) DEFAULT NULL,
					`user_agent` text,
					`cookie` text,
					`created_at` timestamp NULL DEFAULT NULL
				);
			";
			$this->do_query($query);
			elog('DB: Created authorized_devices table');
		}

		if(!in_array('users', $all_tables)) {
			$query = "
				CREATE TABLE `users` (
					`guid` varchar(36) NOT NULL,
					`role` varchar(16) DEFAULT 'user',
					`email` varchar(255) DEFAULT NULL,
					`pseudonym` varchar(255) DEFAULT '',
					`pii_data` MEDIUMTEXT,
					`verified` int DEFAULT '0',
					`password` varchar(255) DEFAULT NULL,
					`created_at` timestamp NULL DEFAULT NULL,
					`confirmation_code` varchar(64) DEFAULT NULL,
					`admin_approved` int DEFAULT '0',
					`twofa` int DEFAULT '0',
					`totp` int DEFAULT '0',
					`badge_partner` int DEFAULT '0',
					`badge_partner_link` varchar(255) DEFAULT NULL,
					`avatar_url` varchar(255) DEFAULT NULL,
					`stripe_customer_id` varchar(64) DEFAULT NULL
				);
			";
			$this->do_query($query);
			elog('DB: Created user table');
			$created_email = getenv('ADMIN_EMAIL');
			$random_password = 'Pw12345$';
			$random_password_hash = hash('sha256', $random_password);
			$query = "
				INSERT INTO `users` VALUES (
					'5a199618-682d-2006-4c4c-c0cde9e672d5',
					'admin',
					'$created_email',
					'',
					1,
					'$random_password_hash',
					'2022-01-01 14:30:00',
					'ADMIN',
					1,
					0,
					0,
					0,
					'',
					'',
					''
				);
			";
			$this->do_query($query);
			elog('Created admin');
			elog('Email: '.$created_email);
			elog('Password: '.$random_password);

			// create test user
			$tester_email = getenv('INTEGRATION_TEST_EMAIL');
			$tester_password = getenv('INTEGRATION_TEST_PASSWORD');
			$tester_password_hash = hash('sha256', $tester_password);

			if(
				$tester_email &&
				$tester_password
			) {
				$query = "
					INSERT INTO `users` VALUES (
						'10000000-0000-0000-4c4c-c0cde9e672d5',
						'user',
						'$tester_email',
						'',
						1,
						'$tester_password_hash',
						'2022-01-01 14:30:00',
						'TESTUSER',
						1,
						0,
						0,
						0,
						'',
						'',
						''
					)
				";
				$this->do_query($query);
				elog('Created test user');
				elog('Email: '.$tester_email);
				elog('Password: '.$tester_password);
			}
		}

		if(!in_array('entities', $all_tables)) {
			$query = "
				CREATE TABLE `entities` (
					`entity_guid` varchar(36) NOT NULL,
					`pii_data` MEDIUMTEXT,
					`created_at` timestamp NULL DEFAULT NULL,
					`updated_at` timestamp NULL DEFAULT NULL
				);
			";
			$this->do_query($query);
			elog('DB: Created entities table');
		}

		if(!in_array('user_entity_relations', $all_tables)) {
			$query = "
				CREATE TABLE `user_entity_relations` (
					`user_guid` varchar(36) NOT NULL,
					`entity_guid` varchar(36) NOT NULL,
					`associated_at` timestamp NULL DEFAULT NULL
				);
			";
			$this->do_query($query);
			elog('DB: Created user_entity_relations table');
		}

		if(!in_array('twofa', $all_tables)) {
			$query = "
				CREATE TABLE `twofa` (
					`guid` varchar(36) NOT NULL,
					`created_at` timestamp NULL DEFAULT NULL,
					`code` varchar(12) NOT NULL
				);
			";
			$this->do_query($query);
			elog('DB: Created twofa table');
		}

		if(!in_array('mfa_allowance', $all_tables)) {
			$query = "
				CREATE TABLE `mfa_allowance` (
					`guid` varchar(36) NOT NULL,
					`expires_at` timestamp NULL DEFAULT NULL
				);
			";
			$this->do_query($query);
			elog('DB: Created mfa_allowance table');
		}

		if(!in_array('throttle', $all_tables)) {
			$query = "
				CREATE TABLE `throttle` (
					`ip` varchar(64) DEFAULT NULL,
					`uri` text,
					`hit` float DEFAULT NULL,
					`last_request` int DEFAULT '0'
				);
			";
			$this->do_query($query);
			elog('DB: Created throttle table');
		}

		if(!in_array('password_resets', $all_tables)) {
			$query = "
				CREATE TABLE `password_resets` (
					`guid` varchar(36) NOT NULL,
					`code` varchar(12) NOT NULL
				);
			";
			$this->do_query($query);
			elog('DB: Created password_resets table');
		}

		if(!in_array('email_changes', $all_tables)) {
			$query = "
				CREATE TABLE `email_changes` (
					`guid` varchar(36) NOT NULL,
					`new_email` varchar(255) DEFAULT NULL,
					`code` varchar(12) NOT NULL,
					`success` int DEFAULT '0',
					`dead` int DEFAULT '0',
					`created_at` timestamp NULL DEFAULT NULL
				);
			";
			$this->do_query($query);
			elog('DB: Created email_changes table');
		}

		if(!in_array('totp', $all_tables)) {
			$query = "
				CREATE TABLE `totp` (
					`guid` varchar(36) NOT NULL,
					`secret` text,
					`hash` varchar(64) DEFAULT NULL,
					`created_at` timestamp NULL DEFAULT NULL,
					`active` int DEFAULT '1'
				);
			";
			$this->do_query($query);
			elog('DB: Created totp table');
		}

		if(!in_array('totp_logins', $all_tables)) {
			$query = "
				CREATE TABLE `totp_logins` (
					`guid` varchar(36) NOT NULL,
					`expires_at` timestamp NULL DEFAULT NULL
				);
			";
			$this->do_query($query);
			elog('DB: Created totp_logins table');
		}

		if(!in_array('login_attempts', $all_tables)) {
			$query = "
				CREATE TABLE `login_attempts` (
					`guid` varchar(36) NOT NULL,
					`email` varchar(255) DEFAULT NULL,
					`logged_in_at` timestamp NULL DEFAULT NULL,
					`successful` int DEFAULT '0',
					`detail` varchar(64) DEFAULT NULL,
					`ip` varchar(64) DEFAULT NULL,
					`user_agent` text,
					`source` varchar(255) DEFAULT NULL
				);
			";
			$this->do_query($query);
			elog('DB: Created login_attempts table');
		}

		if(!in_array('avatar_changes', $all_tables)) {
			$query = "
				CREATE TABLE `avatar_changes` (
					`guid` varchar(36) NOT NULL,
					`updated_at` timestamp NULL DEFAULT NULL
				);
			";
			$this->do_query($query);
			elog('DB: Created avatar_changes table');
		}

		if(!in_array('warnings', $all_tables)) {
			$query = "
				CREATE TABLE `warnings` (
					`guid` varchar(36) NOT NULL,
					`created_at` timestamp NULL DEFAULT NULL,
					`dismissed_at` timestamp NULL DEFAULT NULL,
					`message` text,
					`link` varchar(255) DEFAULT NULL,
					`btn_text` varchar(64) DEFAULT NULL
				);
			";
			$this->do_query($query);
			elog('DB: Created warnings table');
		}

		if(!in_array('all_node_data', $all_tables)) {
			$query = "
				CREATE TABLE `all_node_data` (
					`id` int NOT NULL AUTO_INCREMENT,
					`public_key` varchar(70) NOT NULL,
					`era_id` int NOT NULL,
					`uptime` float DEFAULT NULL,
					`historical_performance` float DEFAULT NULL,
					`current_era_weight` bigint DEFAULT NULL,
					`next_era_weight` bigint DEFAULT NULL,
					`in_current_era` int(1) DEFAULT '0',
					`in_next_era` int(1) DEFAULT '0',
					`in_auction` int(1) DEFAULT '0',
					`bid_delegators_count` int DEFAULT '0',
					`bid_delegation_rate` int DEFAULT '0',
					`bid_inactive` int(1) DEFAULT '0',
					`bid_self_staked_amount` bigint DEFAULT NULL,
					`bid_delegators_staked_amount` bigint DEFAULT NULL,
					`bid_total_staked_amount` bigint DEFAULT NULL,
					`port8888_peers` int DEFAULT NULL,
					`port8888_block_height` int DEFAULT NULL,
					`port8888_build_version` varchar(64) DEFAULT NULL,
					`port8888_next_upgrade` varchar(64) DEFAULT NULL,
					`node_rank` int DEFAULT '0',
					`status` enum('offline','online','probation','suspended') DEFAULT 'online',
					`created_at` timestamp NULL DEFAULT NULL,
					PRIMARY KEY (`id`)
				);
			";
			$this->do_query($query);
			elog('DB: Created all_node_data table');
		}

		if(!in_array('discussions', $all_tables)) {
			$query = "
				CREATE TABLE `discussions` (
					`id` int NOT NULL AUTO_INCREMENT,
					`title` text,
					`description` MEDIUMTEXT,
					`guid` varchar(36) DEFAULT NULL,
					`comments` int DEFAULT '0',
					`likes` int DEFAULT '0',
					`dislikes` int DEFAULT '0',
					`read` int DEFAULT '0',
					`draft` int(1) DEFAULT '0',
					`locked` int(1) DEFAULT '0',
					`created_at` timestamp NULL DEFAULT NULL,
					`updated_at` timestamp NULL DEFAULT NULL,
					PRIMARY KEY (`id`)
				);
			";
			$this->do_query($query);
			elog('DB: Created discussions table');
		}

		if(!in_array('discussion_pins', $all_tables)) {
			$query = "
				CREATE TABLE `discussion_pins` (
					`id` int NOT NULL AUTO_INCREMENT,
					`guid` varchar(36) DEFAULT NULL,
					`discussion_id` int NOT NULL,
					`created_at` timestamp NULL DEFAULT NULL,
					`updated_at` timestamp NULL DEFAULT NULL,
					PRIMARY KEY (`id`)
				);
			";
			$this->do_query($query);
			elog('DB: Created discussion_pins table');
		}

		if(!in_array('discussion_comments', $all_tables)) {
			$query = "
				CREATE TABLE `discussion_comments` (
					`id` int NOT NULL AUTO_INCREMENT,
					`guid` varchar(36) DEFAULT NULL,
					`discussion_id` int NOT NULL,
					`content` MEDIUMTEXT,
					`created_at` timestamp NULL DEFAULT NULL,
					`updated_at` timestamp NULL DEFAULT NULL,
					PRIMARY KEY (`id`)
				);
			";
			$this->do_query($query);
			elog('DB: Created discussion_comments table');
		}

		if(!in_array('ballots', $all_tables)) {
			$query = "
				CREATE TABLE `ballots` (
					`id` int NOT NULL AUTO_INCREMENT,
					`guid` varchar(36) DEFAULT NULL,
					`title` text,
					`description` MEDIUMTEXT,
					`start_time` timestamp,
					`end_time` timestamp,
					`status` varchar(64),
					`created_at` timestamp NULL DEFAULT NULL,
					`updated_at` timestamp NULL DEFAULT NULL,
					PRIMARY KEY (`id`)
				);
			";
			$this->do_query($query);
			elog('DB: Created ballots table');
		}

		if(!in_array('votes', $all_tables)) {
			$query = "
				CREATE TABLE `votes` (
					`id` int NOT NULL AUTO_INCREMENT,
					`guid` varchar(36) DEFAULT NULL,
					`ballot_id` int NOT NULL,
					`direction` varchar(16) NOT NULL
					`created_at` timestamp NULL DEFAULT NULL,
					`updated_at` timestamp NULL DEFAULT NULL,
					PRIMARY KEY (`id`)
				);
			";
			$this->do_query($query);
			elog('DB: Created votes table');
		}

		if(!in_array('shufti', $all_tables)) {
			$query = "
				CREATE TABLE `shufti` (
					`id` int NOT NULL AUTO_INCREMENT,
					`guid` varchar(36) DEFAULT NULL,
					`reference_id` varchar(128) NOT NULL,
					`status` enum('pending','denied','approved') DEFAULT 'pending',
					`data` MEDIUMTEXT,
					`manual_review` int(1) DEFAULT '0',
					`reviewed_at` timestamp NULL DEFAULT NULL,
					`reviewed_by` varchar(36),
					`created_at` timestamp NULL DEFAULT NULL,
					`updated_at` timestamp NULL DEFAULT NULL,
					PRIMARY KEY (`id`)
				);
			";
			$this->do_query($query);
			elog('DB: Created shufti table');
		}
	}
}
?>