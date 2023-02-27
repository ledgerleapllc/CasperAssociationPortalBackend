<?php
/**
 *
 * PUT /user/edit-general-assembly
 *
 * HEADER Authorization: Bearer
 *
 * @api
 * @param int    $id
 * @param string $topic
 * @param string $description
 *
 */
class UserEditGeneralAssembly extends Endpoints {
	function __construct(
		$id            = 0,
		$topic         = '',
		$description   = ''
	) {
		global $db, $helper;

		require_method('PUT');

		$auth        = authenticate_session(1);
		$user_guid   = $auth['guid'] ?? '';
		$pseudonym   = $auth['pseudonym'] ?? '';
		$id          = (int)(parent::$params['id'] ?? 0);
		$topic       = parent::$params['topic'] ?? '';
		$description = parent::$params['description'] ?? '';
		$updated_at  = $helper->get_datetime();

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

		$check = $db->do_select("
			SELECT *
			FROM general_assemblies
			WHERE creator = '$user_guid'
			AND   id      = $id
		");

		if (!$check) {
			_exit(
				'error',
				'You are not authorized to do that',
				400,
				'You are not authorized to do that'
			);
		}

		$finished = $check[0]['finished'] ?? null;

		if ($finished) {
			_exit(
				'error',
				'Cannot edit the descriptors of a concluded general assembly.',
				400,
				'Cannot edit the descriptors of a concluded general assembly.'
			);
		}

		$locked = $check[0]['locked'] ?? null;

		if ($locked) {
			_exit(
				'error',
				'Cannot edit the descriptors of a locked general assembly.',
				400,
				'Cannot edit the descriptors of a locked general assembly.'
			);
		}

		$db->do_query("
			UPDATE general_assemblies
			SET
			topic       = '$topic',
			description = '$description',
			updated_at  = '$updated_at',
			pseudonym   = '$pseudonym'
			WHERE id    = $id
		");

		_exit(
			'success',
			'General Assembly modified'
		);
	}
}
new UserEditGeneralAssembly();
