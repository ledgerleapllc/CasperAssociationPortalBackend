<?php
/**
 *
 * POST /user/register
 *
 * @api
 * @param string $account_type ENUM(individual, entity) default individual
 * @param string $first_name
 * @param string $last_name
 * @param string $email
 * @param string $password
 * @param string $pseudonym
 * @param string $telegram
 * @param string $validator_id
 * @param bool   $subscribe
 * @param string $entity_name
 * @param string $entity_type
 * @param string $registration_number
 * @param string $registration_country
 * @param string $tax_id
 *
 */
class UserRegister extends Endpoints {
	function __construct(
		$account_type = 'individual',
		$first_name   = '',
		$last_name    = '',
		$email        = '',
		$password     = '',
		$pseudonym    = '',
		$telegram     = '',
		$validator_id = '',
		$subscribe    = false,

		$entity_name            = '',
		$entity_type            = '',
		$registration_number    = '',
		$registration_country   = '',
		$tax_id                 = ''
	) {
		global $db, $helper, $authentication;

		require_method('POST');

		$account_type = parent::$params['account_type'] ?? 'individual';
		$email        = parent::$params['email'] ?? null;
		$first_name   = parent::$params['first_name'] ?? '';
		$last_name    = parent::$params['last_name'] ?? '';
		$password     = parent::$params['password'] ?? null;
		$pseudonym    = parent::$params['pseudonym'] ?? null;
		$telegram     = parent::$params['telegram'] ?? null;
		$validator_id = parent::$params['validator_id'] ?? null;
		$subscribe    = (bool)(parent::$params['subscribe'] ?? false);

		$entity_name          = parent::$params['entity_name'] ?? null;
		$entity_type          = parent::$params['entity_type'] ?? null;
		$registration_number  = parent::$params['registration_number'] ?? null;
		$registration_country = parent::$params['registration_country'] ?? null;
		$tax_id               = parent::$params['tax_id'] ?? null;

		/* For live tests */
		$phpunittesttoken = parent::$params['phpunittesttoken'] ?? '';

		/* Pre-check string formats and lengths */
		if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
			_exit(
				'error',
				'Invalid email address',
				400,
				'Invalid email address'
			);
		}

		if (
			!$first_name ||
			!trim($first_name)
		) {
			_exit(
				'error',
				'Please provide first name',
				400,
				'Failed to provide first name'
			);
		}

		if (
			!$last_name ||
			!trim($last_name)
		) {
			_exit(
				'error',
				'Please provide last name',
				400,
				'Failed to provide last name'
			);
		}

		$helper->sanitize_input(
			$first_name,
			true,
			2,
			Regex::$human_name['char_limit'],
			Regex::$human_name['pattern'],
			'First Name'
		);

		$helper->sanitize_input(
			$last_name,
			true,
			2,
			Regex::$human_name['char_limit'],
			Regex::$human_name['pattern'],
			'Last Name'
		);

		if (!$password) {
			_exit(
				'error',
				'Please provide a valid password',
				400,
				'Failed to provide password'
			);
		}

		if (
			strlen($password) < 8 ||
			!preg_match('/[\'\/~`\!@#\$%\^&\*\(\)_\-\+=\{\}\[\]\|;:"\<\>,\.\?\\\]/', $password) ||
			!preg_match('/[0-9]/', $password)
		) {
			_exit(
				'error',
				'Password must be at least 8 characters long, contain at least one (1) number, and one (1) special character',
				400,
				'Invalid password. Does not meet complexity requirements'
			);
		}

		if (
			$account_type != 'individual' &&
			$account_type != 'entity'
		) {
			_exit(
				'error',
				'Please provide valid account type',
				400,
				'Failed to provide valid account type: '.$account_type
			);
		}

		if ($account_type == 'entity') {
			$helper->sanitize_input(
				$entity_name,
				true,
				2,
				Regex::$company_name['char_limit'],
				Regex::$company_name['pattern'],
				'Entity Name'
			);

			$helper->sanitize_input(
				$entity_type,
				true,
				3,
				Regex::$company_name['char_limit'],
				Regex::$company_name['pattern'],
				'Entity Type'
			);

			if (!$helper->ISO3166_country($registration_country)) {
				_exit(
					'error',
					'Invalid registration country specified',
					400,
					'Invalid registration country specified'
				);
			}

			$helper->sanitize_input(
				$registration_number,
				true,
				2,
				Regex::$registration_number['char_limit'],
				Regex::$registration_number['pattern'],
				'Registration Number'
			);

			if (!$tax_id) {
				$tax_id = null;
			}

			$helper->sanitize_input(
				$tax_id,
				false,
				2,
				Regex::$registration_number['char_limit'],
				Regex::$registration_number['pattern'],
				'Tax ID'
			);
		}

		$helper->sanitize_input(
			$pseudonym,
			true,
			3,
			Regex::$pseudonym['char_limit'],
			Regex::$pseudonym['pattern'],
			'Pseudonym'
		);

		if (!$telegram) {
			$telegram = null;
		}

		$helper->sanitize_input(
			$telegram,
			false,
			4,
			Regex::$telegram['char_limit'],
			Regex::$telegram['pattern'],
			'Telegram handle'
		);

		$helper->sanitize_input(
			$validator_id,
			true,
			66,
			Regex::$validator_id['char_limit'],
			Regex::$validator_id['pattern'],
			'Validator ID'
		);

		/* Check validator availability first */
		$check = $db->do_select("
			SELECT
			a.public_key,
			b.guid
			FROM  user_nodes AS a
			JOIN  users AS b
			ON    a.guid       = b.guid
			WHERE a.public_key = '$validator_id'
			AND   a.verified   IS NOT NULL
		");

		if ($check) {
			_exit(
				'error',
				'The validator ID you specified has already been claimed by an association member',
				400,
				'The validator ID you specified has already been claimed by an association member'
			);
		}

		/* Check validator is in pool */
		$check = $db->do_select("
			SELECT era_id
			FROM all_node_data
			WHERE public_key = '$validator_id'
			LIMIT 1
		");

		if (!$check) {
			_exit(
				'error',
				'The validator ID you specified was not found in the Casper validator pool',
				400,
				'The validator ID you specified was not found in the Casper validator pool'
			);
		}

		/* check pre-existing email */
		$check = $db->do_select("
			SELECT guid
			FROM users
			WHERE email = '$email'
		");

		if ($check) {
			_exit(
				'error',
				'An account with this email address already exists',
				400,
				'An account with this email address already exists'
			);
		}

		/* check pre-existing pseudonym */
		$check = $db->do_select("
			SELECT guid
			FROM users
			WHERE pseudonym = '$pseudonym'
		");

		if ($check) {
			_exit(
				'error',
				'An account with this pseudonym already exists',
				400,
				'An account with this pseudonym already exists'
			);
		}

		if ($phpunittesttoken && $phpunittesttoken == 'phpunittesttoken') {
			$role = 'test-user';
		} else {
			$role = 'user';
		}

		$guid              = $helper->generate_guid();
		$created_at        = $helper->get_datetime();
		$confirmation_code = $helper->generate_hash(6);
		$password_hash     = hash('sha256', $password);
		$registration_ip   = $helper->get_real_ip();

		$pii_data = Structs::user_info;
		$pii_data["first_name"]       = $first_name;
		$pii_data["last_name"]        = $last_name;
		$pii_data["registration_ip"]  = $registration_ip;
		$pii_data_enc = $helper->encrypt_pii($pii_data);

		$db->do_query("
			INSERT INTO users (
				guid,
				role,
				email,
				pseudonym,
				telegram,
				account_type,
				pii_data,
				password,
				created_at,
				confirmation_code
			) VALUES (
				'$guid',
				'$role',
				'$email',
				'$pseudonym',
				'$telegram',
				'$account_type',
				'$pii_data_enc',
				'$password_hash',
				'$created_at',
				'$confirmation_code'
			)
		");

		$db->do_query("
			INSERT INTO user_nodes (
				guid,
				created_at,
				updated_at,
				public_key
			) VALUES (
				'$guid',
				'$created_at',
				'$created_at',
				'$validator_id'
			)
		");

		if ($account_type == 'entity') {
			$entity_guid = $helper->generate_guid();
			$pii_data    = Structs::entity_info;
			$pii_data["entity_name"]          = $entity_name;
			$pii_data["entity_type"]          = $entity_type;
			$pii_data["registration_number"]  = $registration_number;
			$pii_data["registration_country"] = $registration_country;
			$pii_data["tax_id"]               = $tax_id;
			$pii_data_enc = $helper->encrypt_pii($pii_data);

			$db->do_query("
				INSERT INTO entities (
					entity_guid,
					pii_data,
					created_at,
					updated_at
				) VALUES (
					'$entity_guid',
					'$pii_data_enc',
					'$created_at',
					'$created_at'
				)
			");

			$db->do_query("
				INSERT INTO user_entity_relations (
					user_guid,
					entity_guid,
					associated_at
				) VALUES (
					'$guid',
					'$entity_guid',
					'$created_at'
				)
			");
		}

		/* create session */
		$bearer     = $authentication->issue_session($guid);
		$user_agent = filter($_SERVER['HTTP_USER_AGENT'] ?? '');

		/* register new authorized device */
		$cookie = $helper->add_authorized_device(
			$guid,
			$registration_ip,
			$user_agent
		);

		/* log login */
		$helper->log_login(
			$guid,
			$email,
			1,
			'First login',
			$registration_ip,
			$user_agent
		);

		/* get new user */
		$me = $helper->get_user($guid);

		if ($me) {
			$recipient = $email;
			$subject   = 'Verify Your Casper Association Account';
			$body      = 'Hello and welcome to the Casper Association Portal. Your registration code is below:<br><br>';
			$link      = $confirmation_code;

			$helper->schedule_email(
				'verify-registration',
				$recipient,
				$subject,
				$body,
				$link
			);

			_exit(
				'success',
				array(
					'bearer' => $bearer,
					'cookie' => $cookie
				)
			);
		}

		_exit(
			'error',
			'Failed to register user',
			500,
			'Failed to register user'
		);
	}
}
new UserRegister();
