<?php
include_once('../../core.php');
/**
 *
 * POST /user/create-general-assembly
 *
 * HEADER Authorization: Bearer
 *
 * @api
 * @param string $topic
 * @param string $description
 * @param string $proposed_time
 *
 */
class UserCreateGeneralAssembly extends Endpoints {
	function __construct(
		$topic         = '',
		$description   = '',
		$proposed_time = ''
	) {
		global $db, $helper;

		require_method('POST');

		$auth          = authenticate_session(1);
		$user_guid     = $auth['guid'] ?? '';
		$pseudonym     = $auth['pseudonym'] ?? '';
		$topic         = parent::$params['topic'] ?? '';
		$description   = parent::$params['description'] ?? '';
		$proposed_time = parent::$params['proposed_time'] ?? '';
		$created_at    = $helper->get_datetime();

		if (
			strlen($topic) > 255 ||
			strlen($topic) < 3
		) {
			_exit(
				'error',
				'Invalid assembly topic. Must be at least 3 chacters, and at most 255 chacters',
				400,
				'Invalid assembly topic. Must be at least 3 chacters, and at most 255 chacters'
			);
		}

		if (
			strlen($description) > 2048 ||
			strlen($description) < 3
		) {
			_exit(
				'error',
				'Invalid assembly description. Must be at least 3 chacters, and at most 2048 chacters',
				400,
				'Invalid assembly description. Must be at least 3 chacters, and at most 2048 chacters'
			);
		}

		$helper->sanitize_input(
			$proposed_time,
			true,
			Regex::$date_extended['char_limit'],
			Regex::$date_extended['char_limit'],
			Regex::$date_extended['pattern'],
			'Proposed date/time slot'
		);

		$check = $db->do_select("
			SELECT *
			FROM general_assemblies
			WHERE creator = '$user_guid'
			AND finished  = 0
		");

		if ($check) {
			_exit(
				'error',
				'You already have a similar assembly that is active',
				400,
				'You already have a similar assembly that is active'
			);
		}

		$db->do_query("
			INSERT INTO general_assemblies (
				creator,
				pseudonym,
				topic,
				description,
				created_at,
				updated_at
			) VALUES (
				'$user_guid',
				'$pseudonym',
				'$topic',
				'$description',
				'$created_at',
				'$created_at'
			)
		");

		// do self proposed date/time
		$assembly_id = $db->do_select("
			SELECT id
			FROM general_assemblies
			WHERE creator  = '$user_guid'
			AND created_at = '$created_at'
		");
		$assembly_id = (int)($assembly_id[0]['id'] ?? 0);

		if ($assembly_id) {
			$db->do_query("
				INSERT INTO assembly_times (
					assembly_id,
					guid,
					pseudonym,
					proposed_time
				) VALUES (
					$assembly_id,
					'$user_guid',
					'$pseudonym',
					'$proposed_time'
				)
			");
		}

		_exit(
			'success',
			'New General Assembly started'
		);
	}
}
new UserCreateGeneralAssembly();