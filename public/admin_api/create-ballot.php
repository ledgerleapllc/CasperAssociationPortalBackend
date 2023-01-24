<?php
include_once('../../core.php');
/**
 *
 * GET /admin/create-ballot
 *
 * HEADER Authorization: Bearer
 *
 * @api
 * @param string $title
 * @param string $description
 * @param string $start_time
 * @param string $end_time
 * @param string $file_url
 *
 */
class AdminCreateBallot extends Endpoints {
	function __construct(
		$title       = '',
		$description = '',
		$start_time  = '',
		$end_time    = '',
		$file_url    = ''
	) {
		global $db, $helper;

		require_method('POST');

		$auth         = authenticate_session(2);
		$admin_guid   = $auth['guid'] ?? '';
		$title        = parent::$params['title'] ?? '';
		$description  = parent::$params['description'] ?? '';
		$start_time   = parent::$params['start_time'] ?? '';
		$end_time     = parent::$params['end_time'] ?? '';
		$file_url     = parent::$params['file_url'] ?? '';
		$created_at   = $helper->get_datetime();

		$helper->sanitize_input(
			$title,
			true,
			5,
			256,
			Regex::$title['pattern'],
			'Title'
		);

		if (strlen($description) > 64000) {
			_exit(
				'error',
				'Ballot body text limited to 64000 characters',
				400
			);
		}

		if (strlen($description) < 10) {
			_exit(
				'error',
				'Ballot body text required. At least 10 characters',
				400
			);
		}

		if (strlen($file_url) > 255) {
			_exit(
				'error',
				'Ballot file url too long. Max 255 characters',
				400
			);
		}

		$helper->sanitize_input(
			$start_time,
			true,
			Regex::$date_extended['char_limit'],
			Regex::$date_extended['char_limit'],
			Regex::$date_extended['pattern'],
			'Start date'
		);

		$helper->sanitize_input(
			$end_time,
			true,
			Regex::$date_extended['char_limit'],
			Regex::$date_extended['char_limit'],
			Regex::$date_extended['pattern'],
			'End date'
		);

		if ($end_time <= $created_at) {
			_exit(
				'error',
				'Cannot start a ballot that ends in the past',
				400
			);
		}

		if ($start_time >= $end_time) {
			_exit(
				'error',
				'End time cannot take place on or before the start time of the ballot',
				400
			);
		}

		if ($start_time > $created_at) {
			$status = 'pending';
		} else {
			$status = 'active';
		}

		$db->do_query("
			INSERT INTO ballots (
				guid,
				title,
				description,
				start_time,
				end_time,
				status,
				file_url,
				created_at,
				updated_at
			) VALUES (
				'$admin_guid',
				'$title',
				'$description',
				'$start_time',
				'$end_time',
				'$status',
				'$file_url',
				'$created_at',
				'$created_at'
			)
		");
		
		_exit(
			'success',
			'Created a new ballot'
		);
	}
}
new AdminCreateBallot();