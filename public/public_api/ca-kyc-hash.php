<?php
include_once('../../core.php');
/**
 *
 * GET /public/ca-kyc-hash
 *
 * @api
 * @param string $hash
 *
 */
class PublicCaKycHash extends Endpoints {
	function __construct(
		$hash = ''
	) {
		global $db, $helper;

		require_method('GET');

		$hash = parent::$params['hash'] ?? '';

		if (!ctype_xdigit($hash)) {
			$hash = '';
		}

		if ($hash) {
			$verification_data = $db->do_select("
				SELECT 
				a.pseudonym,
				b.reference_id,
				b.status
				FROM users AS a
				LEFT JOIN shufti AS b
				ON a.guid = b.guid
				WHERE a.kyc_hash = '$hash'
			");

			$verification_data = $verification_data[0] ?? null;

			if ($verification_data) {
				$verification_data["proof_hash"] = $hash;

				_exit(
					'success',
					$verification_data
				);
			}

			_exit(
				'error',
				array(
					"proof_hash"   => $hash,
					"reference_id" => "",
					"status"       => "Not found",
					"pseudonym"    => "",
				)
			);
		}

		_exit(
			'error',
			'Failed to find kyc proof hash'
		);
	}
}
new PublicCaKycHash();