<?php
/**
 * Mysql database class. Includes entire schemas that build/re-build tables if integrity check fails.
 *
 * @author @blockchainthomas
 *
 * @method array|null  do_select()
 * @method bool        do_query()
 * @method null        check_integrity()
 *
 */
class DB {
	public $connect = null;

	function __construct() {
		$this->connect = new mysqli(
			DB_HOST,
			DB_USER,
			DB_PASS,
			DB_NAME,
			DB_PORT
		);

		if ($this->connect->connect_error) {
			$this->connect = null;
		}
	}

	function __destruct() {
		if ($this->connect) {
			$this->connect->close();
		}
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

		if($this->connect) {
			$result = $this->connect->query($query);

			if($result != null && $result->num_rows > 0) {
				while($row = $result->fetch_assoc()) {
					$return[] = $row;
				}
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
		$flag = false;

		if ($this->connect) {
			$flag = $this->connect->query($query);
		}

		return $flag;
	}

	/**
	 * Check DB integrity
	 */
	public function check_integrity() {
		$query      = "SHOW TABLES";
		$db_tables2 = $this->do_select($query);
		$db_tables  = array();

		// default admin
		$admin_email         = getenv('ADMIN_EMAIL');
		$admin_password      = getenv('ADMIN_PASSWORD');
		$admin_password_hash = hash('sha256', $admin_password);

		// test user
		$tester_email         = getenv('INTEGRATION_TEST_EMAIL');
		$tester_password      = getenv('INTEGRATION_TEST_PASSWORD');
		$tester_password_hash = hash('sha256', $tester_password);

		if ($db_tables2) {
			foreach ($db_tables2 as $table) {
				$db_tables[] = $table['Tables_in_'.DB_NAME];
			}
		}

		$my_tables = array(
			"upgrades"            => array(
				"fields"          => array(
					"id"          => array(
						"type"    => "int",
						"default" => "NOT NULL AUTO_INCREMENT"
					),
					"version"     => array(
						"type"    => "varchar(32)",
						"default" => "NOT NULL"
					),
					"created_at"  => array(
						"type"    => "timestamp",
						"default" => "NULL DEFAULT NULL"
					),
					"updated_at"  => array(
						"type"    => "timestamp",
						"default" => "NULL DEFAULT NULL"
					),
					"visible"     => array(
						"type"    => "int(1)",
						"default" => "DEFAULT 0"
					),
					"activate_at" => array(
						"type"    => "timestamp",
						"default" => "NULL DEFAULT NULL"
					),
					"activate_era"=> array(
						"type"    => "int(11)",
						"default" => "NOT NULL"
					),
					"link"        => array(
						"type"    => "varchar(255)",
						"default" => "DEFAULT ''"
					),
					"notes"       => array(
						"type"    => "text",
						"default" => ""
					),
					"past_upgrade"=> array(
						"type"    => "int(1)",
						"default" => "DEFAULT 0"
					)
				),
				"primary"         => "id",
				"insert_records"  => array(),
			),
			"user_upgrades"       => array(
				"fields"          => array(
					"guid"        => array(
						"type"    => "varchar(36)",
						"default" => "NOT NULL"
					),
					"version"     => array(
						"type"    => "varchar(32)",
						"default" => "NOT NULL"
					),
					"status"      => array(
						"type"    => "enum('pending', 'complete')",
						"default" => "DEFAULT 'pending'"
					),
					"created_at"  => array(
						"type"    => "timestamp",
						"default" => "NULL DEFAULT NULL"
					)
				),
				"primary"         => "guid",
				"insert_records"  => array(),
			),
			"perks"               => array(
				"fields"          => array(
					"id"          => array(
						"type"    => "int",
						"default" => "NOT NULL AUTO_INCREMENT"
					),
					"creator"     => array(
						"type"    => "varchar(36)",
						"default" => "DEFAULT NULL"
					),
					"title"       => array(
						"type"    => "varchar(255)",
						"default" => ""
					),
					"content"     => array(
						"type"    => "MEDIUMTEXT",
						"default" => ""
					),
					"cta"         => array(
						"type"    => "varchar(255)",
						"default" => "DEFAULT ''"
					),
					"image"       => array(
						"type"    => "varchar(255)",
						"default" => "DEFAULT ''"
					),
					"start_time"  => array(
						"type"    => "timestamp",
						"default" => ""
					),
					"end_time"    => array(
						"type"    => "timestamp",
						"default" => ""
					),
					"status"      => array(
						"type"    => "enum('pending','active','expired')",
						"default" => "DEFAULT 'pending'"
					),
					"visible"     => array(
						"type"    => "int(1)",
						"default" => "DEFAULT 1"
					),
					"setting"     => array(
						"type"    => "int(1)",
						"default" => "DEFAULT 0"
					),
					"total_views" => array(
						"type"    => "int(11)",
						"default" => "DEFAULT 0"
					),
					"created_at"  => array(
						"type"    => "timestamp",
						"default" => "NULL DEFAULT NULL"
					),
					"updated_at"  => array(
						"type"    => "timestamp",
						"default" => "NULL DEFAULT NULL"
					)
				),
				"primary"         => "id",
				"insert_records"  => array()
			),
			"emailer_admins"      => array(
				"fields"          => array(
					"guid"        => array(
						"type"    => "varchar(36)",
						"default" => "DEFAULT NULL"
					),
					"email"       => array(
						"type"    => "varchar(255)",
						"default" => "NOT NULL"
					),
					"created_at"  => array(
						"type"    => "timestamp",
						"default" => "NULL DEFAULT NULL"
					),
				),
				"primary"         => "email",
				"insert_records"  => array()
			),
			"contact_recipients"  => array(
				"fields"          => array(
					"guid"        => array(
						"type"    => "varchar(36)",
						"default" => "DEFAULT NULL"
					),
					"email"       => array(
						"type"    => "varchar(255)",
						"default" => "NOT NULL"
					),
					"created_at"  => array(
						"type"    => "timestamp",
						"default" => "NULL DEFAULT NULL"
					),
				),
				"primary"         => "email",
				"insert_records"  => array()
			),
			"schedule"            => array(
				"fields"          => array(
					"id"          => array(
						"type"    => "int",
						"default" => "NOT NULL AUTO_INCREMENT"
					),
					"template_id" => array(
						"type"    => "varchar(255)",
						"default" => "DEFAULT NULL"
					),
					"subject"     => array(
						"type"    => "varchar(255)",
						"default" => "DEFAULT ''"
					),
					"body"        => array(
						"type"    => "text",
						"default" => ""
					),
					"link"        => array(
						"type"    => "text",
						"default" => ""
					),
					"email"       => array(
						"type"    => "varchar(255)",
						"default" => "DEFAULT NULL"
					),
					"created_at"  => array(
						"type"    => "timestamp",
						"default" => "NULL DEFAULT NULL"
					),
					"sent_at"     => array(
						"type"    => "timestamp",
						"default" => "NULL DEFAULT NULL"
					),
					"complete"    => array(
						"type"    => "int",
						"default" => "DEFAULT '0'"
					)
				),
				"primary"         => "id",
				"insert_records"  => array()
			),
			"instant_emails"      => array(
				"fields"          => array(
					"id"          => array(
						"type"    => "int",
						"default" => "NOT NULL AUTO_INCREMENT"
					),
					"template_id" => array(
						"type"    => "varchar(255)",
						"default" => "DEFAULT NULL"
					),
					"subject"     => array(
						"type"    => "varchar(255)",
						"default" => "DEFAULT ''"
					),
					"body"        => array(
						"type"    => "text",
						"default" => ""
					),
					"link"        => array(
						"type"    => "text",
						"default" => ""
					),
					"email"       => array(
						"type"    => "varchar(255)",
						"default" => "DEFAULT NULL"
					),
					"sent_at"     => array(
						"type"    => "varchar(64)",
						"default" => "DEFAULT NULL"
					)
				),
				"primary"         => "id",
				"insert_records"  => array()
			),
			"sessions"            => array(
				"fields"          => array(
					"id"          => array(
						"type"    => "int",
						"default" => "NOT NULL AUTO_INCREMENT"
					),
					"guid"        => array(
						"type"    => "varchar(36)",
						"default" => "NOT NULL"
					),
					"bearer"      => array(
						"type"    => "text",
						"default" => ""
					),
					"created_at"  => array(
						"type"    => "timestamp",
						"default" => "NULL DEFAULT NULL"
					),
					"expires_at"  => array(
						"type"    => "timestamp",
						"default" => "NULL DEFAULT NULL"
					),
					"limit_at"    => array(
						"type"    => "timestamp",
						"default" => "NULL DEFAULT NULL"
					)
				),
				"primary"         => "id",
				"insert_records"  => array()
			),
			"settings"            => array(
				"fields"          => array(
					"name"        => array(
						"type"    => "varchar(64)",
						"default" => "NOT NULL"
					),
					"value"       => array(
						"type"    => "text",
						"default" => ""
					)
				),
				"primary"         => "name",
				"insert_records"  => array()
			),
			"subscriptions"       => array(
				"fields"          => array(
					"guid"        => array(
						"type"    => "varchar(36)",
						"default" => "DEFAULT NULL"
					),
					"email"       => array(
						"type"    => "varchar(255)",
						"default" => "DEFAULT NULL"
					),
					"source"      => array(
						"type"    => "varchar(32)",
						"default" => "DEFAULT NULL"
					),
					"created_at"  => array(
						"type"    => "timestamp",
						"default" => "NULL DEFAULT NULL"
					)
				),
				"insert_records"  => array()
			),
			"authorized_devices"  => array(
				"fields"          => array(
					"guid"        => array(
						"type"    => "varchar(36)",
						"default" => "NOT NULL"
					),
					"ip"          => array(
						"type"    => "varchar(256)",
						"default" => "DEFAULT NULL"
					),
					"user_agent"  => array(
						"type"    => "text",
						"default" => ""
					),
					"cookie"      => array(
						"type"    => "text",
						"default" => ""
					),
					"created_at"  => array(
						"type"    => "timestamp",
						"default" => "NULL DEFAULT NULL"
					)
				),
				"insert_records"  => array()
			),
			"users"               => array(
				"fields"          => array(
					"guid"        => array(
						"type"    => "varchar(36)",
						"default" => "NOT NULL"
					),
					"role"        => array(
						"type"    => "varchar(12)",
						"default" => "DEFAULT 'user'"
					),
					"email"       => array(
						"type"    => "varchar(255)",
						"default" => "DEFAULT NULL"
					),
					"pseudonym"   => array(
						"type"    => "varchar(255)",
						"default" => "DEFAULT ''"
					),
					"telegram"    => array(
						"type"    => "varchar(255)",
						"default" => "DEFAULT ''"
					),
					"account_type" => array(
						"type"    => "enum('individual','entity')",
						"default" => "DEFAULT 'individual'"
					),
					"pii_data"    => array(
						"type"    => "MEDIUMTEXT",
						"default" => ""
					),
					"verified"    => array(
						"type"    => "int",
						"default" => "DEFAULT '0'"
					),
					"password"    => array(
						"type"    => "varchar(255)",
						"default" => "DEFAULT NULL"
					),
					"created_at"  => array(
						"type"    => "timestamp",
						"default" => "NULL DEFAULT NULL"
					),
					"confirmation_code" => array(
						"type"    => "varchar(64)",
						"default" => "DEFAULT NULL"
					),
					"admin_approved" => array(
						"type"    => "int",
						"default" => "DEFAULT '0'"
					),
					"twofa"       => array(
						"type"    => "int",
						"default" => "DEFAULT '0'"
					),
					"totp"        => array(
						"type"    => "int",
						"default" => "DEFAULT '0'"
					),
					"badge_partner" => array(
						"type"    => "int",
						"default" => "DEFAULT '0'"
					),
					"badge_partner_link" => array(
						"type"    => "varchar(255)",
						"default" => "DEFAULT NULL"
					),
					"avatar_url"  => array(
						"type"    => "varchar(255)",
						"default" => "DEFAULT NULL"
					),
					"stripe_customer_id" => array(
						"type"    => "varchar(64)",
						"default" => "DEFAULT NULL"
					),
					"kyc_hash"    => array(
						"type"    => "varchar(255)",
						"default" => "DEFAULT NULL"
					),
					"sig_message" => array(
						"type"    => "text",
						"default" => ""
					),
					"letter"      => array(
						"type"    => "text",
						"default" => ""
					),
					"hellosign_sig" => array(
						"type"    => "varchar(128)",
						"default" => "DEFAULT NULL"
					),
					"esigned"     => array(
						"type"    => "int",
						"default" => "DEFAULT '0'"
					)
				),
				"primary"         => "guid",
				"insert_records"  => array(
					array(
						'5a199618-682d-2006-4c4c-c0cde9e672d5',
						'admin',
						"$admin_email",
						'admin',
						'',
						'individual',
						'',
						1,
						"$admin_password_hash",
						'2022-01-01 14:30:00',
						'ADMIN',
						1,
						0,
						0,
						0,
						'',
						'',
						'',
						'',
						'',
						'',
						'',
						0
					),
					array(
						'10000000-0000-0000-4c4c-c0cde9e672d5',
						'test-user',
						"$tester_email",
						'test-user',
						'',
						'individual',
						'',
						1,
						"$tester_password_hash",
						'2022-01-01 14:30:00',
						'TESTUSER',
						1,
						0,
						0,
						0,
						'',
						'',
						'',
						'',
						'',
						'',
						'',
						0
					),
				)
			),
			"entities"            => array(
				"fields"          => array(
					"entity_guid" => array(
						"type"    => "varchar(36)",
						"default" => "NOT NULL"
					),
					"pii_data"    => array(
						"type"    => "MEDIUMTEXT",
						"default" => ""
					),
					"created_at"  => array(
						"type"    => "timestamp",
						"default" => "NULL DEFAULT NULL"
					),
					"updated_at"  => array(
						"type"    => "timestamp",
						"default" => "NULL DEFAULT NULL"
					)
				),
				"insert_records"  => array()
			),
			"user_entity_relations" => array(
				"fields"          => array(
					"user_guid"   => array(
						"type"    => "varchar(36)",
						"default" => "NOT NULL"
					),
					"entity_guid" => array(
						"type"    => "varchar(36)",
						"default" => "NOT NULL"
					),
					"associated_at" => array(
						"type"    => "timestamp",
						"default" => "NULL DEFAULT NULL"
					)
				),
				"insert_records"  => array()
			),
			"entity_docs"         => array(
				"fields"           => array(
					"user_guid"   => array(
						"type"    => "varchar(36)",
						"default" => ""
					),
					"entity_guid" => array(
						"type"    => "varchar(36)",
						"default" => ""
					),
					"file_name"   => array(
						"type"    => "varchar(255)",
						"default" => "NOT NULL"
					),
					"file_url"   => array(
						"type"    => "varchar(255)",
						"default" => "NOT NULL"
					),
					"created_at"  => array(
						"type"    => "timestamp",
						"default" => "NULL DEFAULT NULL"
					)
				),
				"insert_records"  => array()
			),
			"twofa"               => array(
				"fields"          => array(
					"id"          => array(
						"type"    => "int",
						"default" => "NOT NULL AUTO_INCREMENT"
					),
					"guid"        => array(
						"type"    => "varchar(36)",
						"default" => "NOT NULL"
					),
					"created_at"  => array(
						"type"    => "timestamp",
						"default" => "NULL DEFAULT NULL"
					),
					"code"        => array(
						"type"    => "varchar(12)",
						"default" => "NOT NULL"
					)
				),
				"primary"         => "id",
				"insert_records"  => array()
			),
			"mfa_allowance"       => array(
				"fields"          => array(
					"guid"        => array(
						"type"    => "varchar(36)",
						"default" => "NOT NULL"
					),
					"expires_at"  => array(
						"type"    => "timestamp",
						"default" => "NULL DEFAULT NULL"
					)
				),
				"insert_records"  => array()
			),
			"throttle"            => array(
				"fields"          => array(
					"ip"          => array(
						"type"    => "varchar(64)",
						"default" => "DEFAULT NULL"
					),
					"uri"         => array(
						"type"    => "text",
						"default" => ""
					),
					"hit"         => array(
						"type"    => "float",
						"default" => "DEFAULT NULL"
					),
					"last_request" => array(
						"type"    => "int",
						"default" => "DEFAULT '0'"
					)
				),
				"insert_records"  => array()
			),
			"password_resets"     => array(
				"fields"          => array(
					"guid"        => array(
						"type"    => "varchar(36)",
						"default" => "NOT NULL"
					),
					"code"        => array(
						"type"    => "varchar(12)",
						"default" => "NOT NULL"
					)
				),
				"insert_records"  => array()
			),
			"email_changes"       => array(
				"fields"          => array(
					"guid"        => array(
						"type"    => "varchar(36)",
						"default" => "NOT NULL"
					),
					"new_email"   => array(
						"type"    => "varchar(255)",
						"default" => "DEFAULT NULL"
					),
					"code"        => array(
						"type"    => "varchar(12)",
						"default" => "NOT NULL"
					),
					"success"     => array(
						"type"    => "int",
						"default" => "DEFAULT '0'"
					),
					"dead"        => array(
						"type"    => "int",
						"default" => "DEFAULT '0'"
					),
					"created_at"  => array(
						"type"    => "timestamp",
						"default" => "NULL DEFAULT NULL"
					)
				),
				"insert_records"  => array()
			),
			"totp"                => array(
				"fields"          => array(
					"guid"        => array(
						"type"    => "varchar(36)",
						"default" => "NOT NULL"
					),
					"secret"      => array(
						"type"    => "text",
						"default" => ""
					),
					"hash"        => array(
						"type"    => "varchar(64)",
						"default" => "DEFAULT NULL"
					),
					"created_at"  => array(
						"type"    => "timestamp",
						"default" => "NULL DEFAULT NULL"
					),
					"active"      => array(
						"type"    => "int",
						"default" => "DEFAULT '1'"
					)
				),
				"insert_records"  => array()
			),
			"totp_logins"         => array(
				"fields"          => array(
					"guid"        => array(
						"type"    => "varchar(36)",
						"default" => "NOT NULL"
					),
					"expires_at"  => array(
						"type"    => "timestamp",
						"default" => "NULL DEFAULT NULL"
					)
				),
				"insert_records"  => array()
			),
			"login_attempts"      => array(
				"fields"          => array(
					"guid"        => array(
						"type"    => "varchar(36)",
						"default" => "NOT NULL"
					),
					"email"       => array(
						"type"    => "varchar(255)",
						"default" => "DEFAULT NULL"
					),
					"logged_in_at" => array(
						"type"    => "timestamp",
						"default" => "NULL DEFAULT NULL"
					),
					"successful"  => array(
						"type"    => "int(1)",
						"default" => "DEFAULT '0'"
					),
					"detail"      => array(
						"type"    => "varchar(64)",
						"default" => "DEFAULT NULL"
					),
					"ip"          => array(
						"type"    => "varchar(64)",
						"default" => "DEFAULT NULL"
					),
					"user_agent"  => array(
						"type"    => "text",
						"default" => ""
					),
					"source"      => array(
						"type"    => "varchar(255)",
						"default" => "DEFAULT NULL"
					)
				),
				"insert_records"  => array()
			),
			"avatar_changes"      => array(
				"fields"          => array(
					"guid"        => array(
						"type"    => "varchar(36)",
						"default" => "NOT NULL"
					),
					"updated_at"  => array(
						"type"    => "timestamp",
						"default" => "NULL DEFAULT NULL"
					)
				),
				"primary"         => "guid",
				"insert_records"  => array()
			),
			"notifications"       => array(
				"fields"          => array(
					"id"          => array(
						"type"    => "int",
						"default" => "NOT NULL AUTO_INCREMENT"
					),
					"title"       => array(
						"type"    => "varchar(255)",
						"default" => "DEFAULT ''"
					),
					"message"     => array(
						"type"    => "text",
						"default" => ""
					),
					"type"        => array(
						"type"    => "enum('warning', 'error', 'question', 'info')",
						"default" => "DEFAULT 'info'"
					),
					"dismissable" => array(
						"type"    => "int(1)",
						"default" => "DEFAULT 1"
					),
					"priority"    => array(
						"type"    => "int",
						"default" => "DEFAULT 1"
					),
					"visible"     => array(
						"type"    => "int(1)",
						"default" => "DEFAULT 1"
					),
					"cta"         => array(
						"type"    => "varchar(255)",
						"default" => "DEFAULT ''"
					),
					"created_at"  => array(
						"type"    => "timestamp",
						"default" => "NULL DEFAULT NULL"
					),
					"activate_at" => array(
						"type"    => "timestamp",
						"default" => "NULL DEFAULT NULL"
					),
					"deactivate_at" => array(
						"type"    => "timestamp",
						"default" => "NULL DEFAULT NULL"
					)
				),
				"primary"         => "id",
				"insert_records"  => array()
			),
			"user_notifications"  => array(
				"fields"          => array(
					"notification_id" => array(
						"type"    => "int",
						"default" => "NOT NULL"
					),
					"guid"        => array(
						"type"    => "varchar(36)",
						"default" => "NOT NULL"
					),
					"created_at"  => array(
						"type"    => "timestamp",
						"default" => "NULL DEFAULT NULL"
					),
					"dismissed_at" => array(
						"type"    => "timestamp",
						"default" => "NULL DEFAULT NULL"
					)
				),
				"insert_records"  => array()
			),
			"warnings"            => array(
				"fields"          => array(
					"guid"        => array(
						"type"    => "varchar(36)",
						"default" => "NOT NULL"
					),
					"public_key"  => array(
						"type"    => "varchar(70)",
						"default" => "NOT NULL"
					),
					"type"        => array(
						"type"    => "enum('warning', 'probation', 'suspension')",
						"default" => "DEFAULT 'warning'"
					),
					"created_at"  => array(
						"type"    => "timestamp",
						"default" => "NULL DEFAULT NULL"
					),
					"dismissed_at"=> array(
						"type"    => "timestamp",
						"default" => "NULL DEFAULT NULL"
					),
					"message"     => array(
						"type"    => "text",
						"default" => ""
					)
				),
				"insert_records"  => array()
			),
			"warning_notifications"=> array(
				"fields"          => array(
					"guid"        => array(
						"type"    => "varchar(36)",
						"default" => "NOT NULL"
					),
					"public_key"  => array(
						"type"    => "varchar(70)",
						"default" => "NOT NULL"
					),
					"sent_at"     => array(
						"type"    => "timestamp",
						"default" => "NULL DEFAULT NULL"
					),
				),
				"insert_records"  => array()
			),
			"user_nodes"          => array(
				"fields"          => array(
					"guid"        => array(
						"type"    => "varchar(36)",
						"default" => "NOT NULL"
					),
					"public_key"  => array(
						"type"    => "varchar(70)",
						"default" => "NOT NULL"
					),
					"verified"    => array(
						"type"    => "timestamp",
						"default" => "NULL DEFAULT NULL"
					),
					"created_at"  => array(
						"type"    => "timestamp",
						"default" => "NULL DEFAULT NULL"
					),
					"updated_at"  => array(
						"type"    => "timestamp",
						"default" => "NULL DEFAULT NULL"
					)
				),
				"insert_records"  => array()
			),
			"all_node_data"       => array(
				"fields"          => array(
					"id"          => array(
						"type"    => "int",
						"default" => "NOT NULL AUTO_INCREMENT"
					),
					"public_key"  => array(
						"type"    => "varchar(70)",
						"default" => "NOT NULL"
					),
					"era_id"      => array(
						"type"    => "int",
						"default" => "NOT NULL"
					),
					"uptime"      => array(
						"type"    => "float",
						"default" => "DEFAULT NULL"
					),
					"historical_performance" => array(
						"type"    => "float",
						"default" => "DEFAULT NULL"
					),
					"current_era_weight" => array(
						"type"    => "bigint",
						"default" => "DEFAULT NULL"
					),
					"next_era_weight" => array(
						"type"    => "bigint",
						"default" => "DEFAULT NULL"
					),
					"in_current_era" => array(
						"type"    => "int(1)",
						"default" => "DEFAULT '1'"
					),
					"in_next_era" => array(
						"type"    => "int(1)",
						"default" => "DEFAULT '1'"
					),
					"in_auction"  => array(
						"type"    => "int(1)",
						"default" => "DEFAULT '1'"
					),
					"bid_delegators_count" => array(
						"type"    => "int",
						"default" => "DEFAULT '0'"
					),
					"bid_delegation_rate" => array(
						"type"    => "int",
						"default" => "DEFAULT '0'"
					),
					"bid_inactive" => array(
						"type"    => "int(1)",
						"default" => "DEFAULT '0'"
					),
					"bid_self_staked_amount" => array(
						"type"    => "bigint",
						"default" => "DEFAULT NULL"
					),
					"bid_delegators_staked_amount" => array(
						"type"    => "bigint",
						"default" => "DEFAULT NULL"
					),
					"bid_total_staked_amount" => array(
						"type"    => "bigint",
						"default" => "DEFAULT NULL"
					),
					"node_rank"   => array(
						"type"    => "int",
						"default" => "DEFAULT '0'"
					),
					"status"      => array(
						"type"    => "enum('offline','online','probation','suspended')",
						"default" => "DEFAULT 'online'"
					),
					"created_at"  => array(
						"type"    => "timestamp",
						"default" => "NULL DEFAULT NULL"
					)
				),
				"primary"         => "id",
				"insert_records"  => array()
			),
			"ballots"             => array(
				"fields"          => array(
					"id"          => array(
						"type"    => "int",
						"default" => "NOT NULL AUTO_INCREMENT"
					),
					"guid"        => array(
						"type"    => "varchar(36)",
						"default" => "NOT NULL"
					),
					"title"       => array(
						"type"    => "text",
						"default" => ""
					),
					"description" => array(
						"type"    => "MEDIUMTEXT",
						"default" => ""
					),
					"start_time"  => array(
						"type"    => "timestamp",
						"default" => ""
					),
					"end_time"    => array(
						"type"    => "timestamp",
						"default" => ""
					),
					"status"      => array(
						"type"    => "enum('pending','active','done')",
						"default" => "DEFAULT 'pending'"
					),
					"file_url"    => array(
						"type"    => "varchar(255)",
						"default" => "DEFAULT ''"
					),
					"file_name"   => array(
						"type"    => "varchar(255)",
						"default" => "NOT NULL"
					),
					"reminded"    => array(
						"type"    => "int(1)",
						"default" => "DEFAULT 0"
					),
					"created_at"  => array(
						"type"    => "timestamp",
						"default" => "NULL DEFAULT NULL"
					),
					"updated_at"  => array(
						"type"    => "timestamp",
						"default" => "NULL DEFAULT NULL"
					)
				),
				"primary"         => "id",
				"insert_records"  => array()
			),
			"votes"               => array(
				"fields"          => array(
					"id"          => array(
						"type"    => "int",
						"default" => "NOT NULL AUTO_INCREMENT"
					),
					"guid"        => array(
						"type"    => "varchar(36)",
						"default" => "NOT NULL"
					),
					"ballot_id"   => array(
						"type"    => "int",
						"default" => "NOT NULL"
					),
					"direction"   => array(
						"type"    => "varchar(16)",
						"default" => "NOT NULL"
					),
					"created_at"  => array(
						"type"    => "timestamp",
						"default" => "NULL DEFAULT NULL"
					),
					"updated_at"  => array(
						"type"    => "timestamp",
						"default" => "NULL DEFAULT NULL"
					)
				),
				"primary"         => "id",
				"insert_records"  => array()
			),
			"team_invites"        => array(
				"fields"          => array(
					"guid"        => array(
						"type"    => "varchar(36)",
						"default" => "NOT NULL"
					),
					"email"       => array(
						"type"    => "varchar(255)",
						"default" => "NOT NULL"
					),
					"created_at"  => array(
						"type"    => "timestamp",
						"default" => "DEFAULT NULL"
					),
					"accepted_at" => array(
						"type"    => "timestamp",
						"default" => "DEFAULT NULL"
					),
					"confirmation_code" => array(
						"type"    => "varchar(64)",
						"default" => "DEFAULT NULL"
					)
				),
				"primary"         => "email",
				"insert_records"  => array()
			),
			"permissions"         => array(
				"fields"          => array(
					"guid"        => array(
						"type"    => "varchar(36)",
						"default" => "NOT NULL"
					),
					"membership"  => array(
						"type"    => "int(1)",
						"default" => "DEFAULT 1"
					),
					"nodes"       => array(
						"type"    => "int(1)",
						"default" => "DEFAULT 1"
					),
					"eras"        => array(
						"type"    => "int(1)",
						"default" => "DEFAULT 1"
					),
					"discussions" => array(
						"type"    => "int(1)",
						"default" => "DEFAULT 1"
					),
					"ballots"     => array(
						"type"    => "int(1)",
						"default" => "DEFAULT 1"
					),
					"perks"       => array(
						"type"    => "int(1)",
						"default" => "DEFAULT 1"
					),
					"intake"      => array(
						"type"    => "int(1)",
						"default" => "DEFAULT 1"
					),
					"users"       => array(
						"type"    => "int(1)",
						"default" => "DEFAULT 1"
					),
					"teams"       => array(
						"type"    => "int(1)",
						"default" => "DEFAULT 1"
					),
					"global_settings" => array(
						"type"    => "int(1)",
						"default" => "DEFAULT 1"
					)
				),
				"primary"         => "guid",
				"insert_records"  => array()
			),
			"general_assemblies"  => array(
				"fields"          => array(
					"id"          => array(
						"type"    => "int",
						"default" => "NOT NULL AUTO_INCREMENT"
					),
					"creator"     => array(
						"type"    => "varchar(36)",
						"default" => "DEFAULT NULL"
					),
					"pseudonym"   => array(
						"type"    => "varchar(255)",
						"default" => "DEFAULT NULL"
					),
					"seconded_by" => array(
						"type"    => "varchar(36)",
						"default" => "DEFAULT NULL"
					),
					"topic"       => array(
						"type"    => "text",
						"default" => ""
					),
					"description" => array(
						"type"    => "MEDIUMTEXT",
						"default" => ""
					),
					"created_at"  => array(
						"type"    => "timestamp",
						"default" => "NULL DEFAULT NULL"
					),
					"updated_at"  => array(
						"type"    => "timestamp",
						"default" => "NULL DEFAULT NULL"
					),
					"conducted_at" => array(
						"type"    => "timestamp",
						"default" => "NULL DEFAULT NULL"
					),
					"locked"      => array(
						"type"    => "int(1)",
						"default" => "DEFAULT 0"
					),
					"finished"    => array(
						"type"    => "int(1)",
						"default" => "DEFAULT 0"
					)
				),
				"primary"         => "id",
				"insert_records"  => array()
			),
			"assembly_times"      => array(
				"fields"          => array(
					"assembly_id" => array(
						"type"    => "int",
						"default" => "NOT NULL"
					),
					"guid"        => array(
						"type"    => "varchar(36)",
						"default" => "DEFAULT NULL"
					),
					"pseudonym"   => array(
						"type"    => "varchar(255)",
						"default" => "DEFAULT ''"
					),
					"proposed_time" => array(
						"type"    => "timestamp",
						"default" => "NULL DEFAULT NULL"
					)
				),
				"insert_records"  => array()
			),
			"token_price"         => array(
				"fields"          => array(
					"id"          => array(
						"type"    => "int",
						"default" => "NOT NULL AUTO_INCREMENT"
					),
					"price"       => array(
						"type"    => "float",
						"default" => "NOT NULL"
					),
					"created_at"  => array(
						"type"    => "timestamp",
						"default" => "NULL DEFAULT NULL"
					),
					"updated_at"  => array(
						"type"    => "timestamp",
						"default" => "NULL DEFAULT NULL"
					)
				),
				"primary"         => "id",
				"insert_records"  => array(
					array(
						1,
						0.0275,
						'2022-06-30 13:30:02',
						'2022-06-30 13:30:02'
					),
					array(
						2,
						0.0274,
						'2022-06-30 14:00:02',
						'2022-06-30 14:00:02'
					),
					array(
						3,
						0.0271,
						'2022-06-30 14:30:02',
						'2022-06-30 14:30:02'
					),
					array(
						4,
						0.0273,
						'2022-06-30 15:00:02',
						'2022-06-30 15:00:02'
					),
					array(
						5,
						0.0268,
						'2022-06-30 15:30:02',
						'2022-06-30 15:30:02'
					),
					array(
						6,
						0.0273,
						'2022-06-30 16:00:02',
						'2022-06-30 16:00:02'
					),
					array(
						7,
						0.0277,
						'2022-06-30 16:30:02',
						'2022-06-30 16:30:02'
					),
					array(
						8,
						0.0278,
						'2022-06-30 17:00:02',
						'2022-06-30 17:00:02'
					),
					array(
						9,
						0.0282,
						'2022-06-30 17:30:02',
						'2022-06-30 17:30:02'
					),
					array(
						10,
						0.0285,
						'2022-06-30 18:00:02',
						'2022-06-30 18:00:02'
					),
				)
			),
			"mbs"                 => array(
				"fields"          => array(
					"id"          => array(
						"type"    => "int",
						"default" => "NOT NULL AUTO_INCREMENT"
					),
					"era_id"      => array(
						"type"    => "int(11)",
						"default" => "NOT NULL"
					),
					"mbs"         => array(
						"type"    => "int(11)",
						"default" => "DEFAULT 0"
					),
					"created_at"  => array(
						"type"    => "timestamp",
						"default" => "NULL DEFAULT NULL"
					),
					"updated_at"  => array(
						"type"    => "timestamp",
						"default" => "NULL DEFAULT NULL"
					)
				),
				"primary"         => "id",
				"insert_records"  => array()
			),
			"shufti"              => array(
				"fields"          => array(
					"id"          => array(
						"type"    => "int",
						"default" => "NOT NULL AUTO_INCREMENT"
					),
					"guid"        => array(
						"type"    => "varchar(36)",
						"default" => "DEFAULT NULL",
					),
					"reference_id" => array(
						"type"    => "varchar(128)",
						"default" => "NOT NULL"
					),
					"status"      => array(
						"type"    => "enum('pending','denied','approved')",
						"default" => "DEFAULT 'pending'"
					),
					"data"        => array(
						"type"    => "MEDIUMTEXT",
						"default" => ""
					),
					"declined_reason"=> array(
						"type"    => "TEXT",
						"default" => ""
					),
					"id_check"    => array(
						"type"    => "int(1)",
						"default" => "DEFAULT '0'"
					),
					"address_check" => array(
						"type"    => "int(1)",
						"default" => "DEFAULT '0'"
					),
					"background_check" => array(
						"type"    => "int(1)",
						"default" => "DEFAULT '0'"
					),
					"manual_review" => array(
						"type"    => "int(1)",
						"default" => "DEFAULT '0'"
					),
					"reviewed_at" => array(
						"type"    => "timestamp",
						"default" => "NULL DEFAULT NULL"
					),
					"reviewed_by" => array(
						"type"    => "varchar(36)",
						"default" => ""
					),
					"cmp_checked" => array(
						"type"    => "int(1)",
						"default" => "DEFAULT 0"
					),
					"created_at"  => array(
						"type"    => "timestamp",
						"default" => "NULL DEFAULT NULL"
					),
					"updated_at"  => array(
						"type"    => "timestamp",
						"default" => "NULL DEFAULT NULL"
					)
				),
				"primary"         => "id",
				"insert_records"  => array()
			),
			"discussions"         => array(
				"fields"          => array(
					"id"          => array(
						"type"    => "int",
						"default" => "NOT NULL AUTO_INCREMENT"
					),
					"title"       => array(
						"type"    => "text",
						"default" => "",
					),
					"description" => array(
						"type"    => "MEDIUMTEXT",
						"default" => "",
					),
					"guid"        => array(
						"type"    => "varchar(36)",
						"default" => "DEFAULT NULL",
					),
					"associated_ballot" => array(
						"type"    => "int",
						"default" => "DEFAULT NULL",
					),
					"for_upgrade" => array(
						"type"    => "int",
						"default" => "DEFAULT '0'",
					),
					"likes"       => array(
						"type"    => "int",
						"default" => "DEFAULT '0'",
					),
					"dislikes"    => array(
						"type"    => "int",
						"default" => "DEFAULT '0'",
					),
					"is_read"     => array(
						"type"    => "int",
						"default" => "DEFAULT '0'",
					),
					"draft"       => array(
						"type"    => "int(1)",
						"default" => "DEFAULT '0'",
					),
					"locked"      => array(
						"type"    => "int(1)",
						"default" => "DEFAULT '0'",
					),
					"created_at"  => array(
						"type"    => "timestamp",
						"default" => "NULL DEFAULT NULL"
					),
					"updated_at"  => array(
						"type"    => "timestamp",
						"default" => "NULL DEFAULT NULL"
					)
				),
				"primary"         => "id",
				"insert_records"  => array()
			),
			"discussion_drafts"   => array(
				"fields"          => array(
					"id"          => array(
						"type"    => "int",
						"default" => "NOT NULL AUTO_INCREMENT"
					),
					"title"       => array(
						"type"    => "text",
						"default" => "",
					),
					"description" => array(
						"type"    => "MEDIUMTEXT",
						"default" => "",
					),
					"guid"        => array(
						"type"    => "varchar(36)",
						"default" => "DEFAULT NULL",
					),
					"associated_ballot" => array(
						"type"    => "int",
						"default" => "DEFAULT NULL",
					),
					"for_upgrade" => array(
						"type"    => "int",
						"default" => "DEFAULT '0'",
					),
					"created_at"  => array(
						"type"    => "timestamp",
						"default" => "NULL DEFAULT NULL"
					),
					"updated_at"  => array(
						"type"    => "timestamp",
						"default" => "NULL DEFAULT NULL"
					)
				),
				"primary"         => "id",
				"insert_records"  => array()
			),
			"discussion_pins"     => array(
				"fields"          => array(
					"id"          => array(
						"type"    => "int",
						"default" => "NOT NULL AUTO_INCREMENT"
					),
					"guid"        => array(
						"type"    => "varchar(36)",
						"default" => "DEFAULT NULL",
					),
					"discussion_id" => array(
						"type"    => "int",
						"default" => "NOT NULL",
					),
					"created_at"  => array(
						"type"    => "timestamp",
						"default" => "NULL DEFAULT NULL"
					),
					"updated_at"  => array(
						"type"    => "timestamp",
						"default" => "NULL DEFAULT NULL"
					)
				),
				"primary"         => "id",
				"insert_records"  => array()
			),
			"discussion_likes"    => array(
				"fields"          => array(
					"id"          => array(
						"type"    => "int",
						"default" => "NOT NULL AUTO_INCREMENT"
					),
					"guid"        => array(
						"type"    => "varchar(36)",
						"default" => "DEFAULT NULL",
					),
					"discussion_id" => array(
						"type"    => "int",
						"default" => "NOT NULL",
					),
					"created_at"  => array(
						"type"    => "timestamp",
						"default" => "NULL DEFAULT NULL"
					),
					"updated_at"  => array(
						"type"    => "timestamp",
						"default" => "NULL DEFAULT NULL"
					)
				),
				"primary"         => "id",
				"insert_records"  => array()
			),
			"discussion_comments" => array(
				"fields"          => array(
					"id"          => array(
						"type"    => "int",
						"default" => "NOT NULL AUTO_INCREMENT"
					),
					"guid"        => array(
						"type"    => "varchar(36)",
						"default" => "DEFAULT NULL",
					),
					"discussion_id" => array(
						"type"    => "int",
						"default" => "NOT NULL",
					),
					"content"     => array(
						"type"    => "MEDIUMTEXT",
						"default" => "",
					),
					"flagged"     => array(
						"type"    => "int",
						"default" => "DEFAULT '0'"
					),
					"deleted"     => array(
						"type"    => "int",
						"default" => "DEFAULT '0'"
					),
					"created_at"  => array(
						"type"    => "timestamp",
						"default" => "NULL DEFAULT NULL"
					),
					"updated_at"  => array(
						"type"    => "timestamp",
						"default" => "NULL DEFAULT NULL"
					)
				),
				"primary"         => "id",
				"insert_records"  => array()
			),
			"suspensions"         => array(
				"fields"          => array(
					"id"          => array(
						"type"    => "int",
						"default" => "NOT NULL AUTO_INCREMENT"
					),
					"guid"        => array(
						"type"    => "varchar(36)",
						"default" => "NOT NULL",
					),
					"reason"      => array(
						"type"    => "varchar(255)",
						"default" => ""
					),
					"reinstatable"=> array(
						"type"    => "int",
						"default" => "DEFAULT '0'"
					),
					"reinstated"  => array(
						"type"    => "int",
						"default" => "DEFAULT '0'"
					),
					"letter"      => array(
						"type"    => "mediumtext",
						"default" => ""
					),
					"created_at"  => array(
						"type"    => "timestamp",
						"default" => "NULL DEFAULT NULL"
					),
					"updated_at"  => array(
						"type"    => "timestamp",
						"default" => "NULL DEFAULT NULL"
					),
					"reinstated_at"=> array(
						"type"    => "timestamp",
						"default" => "NULL DEFAULT NULL"
					),
					"decision"    => array(
						"type"    => "varchar(255)",
						"default" => ""
					)
				),
				"primary"         => "id",
				"insert_records"  => array()
			),
			"probations"          => array(
				"fields"          => array(
					"id"          => array(
						"type"    => "int",
						"default" => "NOT NULL AUTO_INCREMENT"
					),
					"guid"        => array(
						"type"    => "varchar(36)",
						"default" => "NOT NULL",
					),
					"public_key"  => array(
						"type"    => "varchar(70)",
						"default" => "NOT NULL"
					),
					"created_at"  => array(
						"type"    => "timestamp",
						"default" => "NULL DEFAULT NULL"
					),
					"updated_at"  => array(
						"type"    => "timestamp",
						"default" => "NULL DEFAULT NULL"
					)
				),
				"primary"         => "id",
				"insert_records"  => array()
			)
		);

		foreach ($my_tables as $table_name => $table) {
			if (!in_array($table_name, $db_tables)) {
				$fields  = $table['fields'] ?? array();
				$primary = $table['primary'] ?? '';
				$query   = "CREATE TABLE `$table_name` (\n";

				foreach ($fields as $field_name => $field) {
					$type    = $field['type'] ?? 'varchar(255)';
					$default = $field['default'] ?? '';
					$query  .= "`$field_name` $type".($default ? ' '.$default : '');

					if ($field_name != array_key_last($fields)) {
						$query .= ",\n";
					}
				}

				if ($primary) {
					$query .= ",\nPRIMARY KEY (`$primary`)";
				}

				$query .= "\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

				$this->do_query($query);
				elog("DB: Created $table_name table");

				$insert_records = $table['insert_records'] ?? array();

				foreach ($insert_records as $record) {
					$query = "INSERT INTO $table_name (\n";

					foreach ($fields as $field_name => $field_value) {
						$query .= $field_name;

						if ($field_name != array_key_last($fields)) {
							$query .= ",\n";
						}
					}

					$query .= "\n) VALUES (\n";

					foreach ($record as $field_index => $field_value) {
						if (gettype($field_value) === 'string') {
							$query .= "'$field_value'";
						} elseif (strtoupper(gettype($field_value)) === 'NULL') {
							$query .= 'NULL';
						} else {
							$query .= $field_value;
						}

						if ($field_index != array_key_last($record)) {
							$query .= ",\n";
						}
					}

					$query .= "\n)";

					$this->do_query($query);
					elog("DB: Inserted default record into $table_name table");
				}
			} else {
				$query      = "DESCRIBE $table_name";
				$desc       = $this->do_select($query);
				$desc       = $desc ?? array();
				$array_keys = array_keys($my_tables[$table_name]['fields']);
				$db_fields  = array();

				foreach ($desc as $column) {
					$field = $column['Field'] ?? '';

					if ($field) {
						$db_fields[] = $field;
					}
				}

				foreach ($array_keys as $array_key) {
					if (!in_array($array_key, $db_fields)) {
						elog("Detected change in $table_name table - Adding field '$array_key'");
						$type    = $my_tables[$table_name]['fields'][$array_key]['type']    ?? '';
						$default = $my_tables[$table_name]['fields'][$array_key]['default'] ?? '';
						$query   = "
							ALTER TABLE $table_name
							ADD COLUMN $array_key
							$type $default
						";
						$this->do_query($query);
					}
				}
			}
		}
	}
}
?>
