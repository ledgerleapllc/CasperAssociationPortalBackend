<?php
/**
 *
 * POST /user/save-shufti-ref
 *
 * HEADER Authorization: Bearer
 *
 * @api
 * @param string $reference_id
 * @param string $first_name
 * @param string $middle_name
 * @param string $last_name
 * @param string $dob
 * @param string $country
 * @param string $account_type
 *
 * @param string $entity_name
 * @param string $entity_type
 * @param string $entity_reg_number
 * @param string $entity_vat_number
 *
 * @param string $entity_document_name
 * @param string $entity_document_page
 *
 */
class UserSaveShuftiRef extends Endpoints {
	function __construct(
		$reference_id = '',
		$first_name   = '',
		$middle_name  = '',
		$last_name    = '',
		$dob          = '',
		$country      = '',
		$account_type = '',

		$entity_name       = '',
		$entity_type       = '',
		$entity_reg_number = '',
		$entity_vat_number = '',

		$entity_document_name = '',
		$entity_document_page = ''
	) {
		global $db, $helper;

		require_method('POST');

		$auth         = authenticate_session(1);
		$user_guid    = $auth['guid'] ?? '';
		$reference_id = parent::$params['reference_id'] ?? '';
		$first_name   = parent::$params['first_name'] ?? '';
		$middle_name  = parent::$params['middle_name'] ?? '';
		$last_name    = parent::$params['last_name'] ?? '';
		$dob          = parent::$params['dob'] ?? '';
		$country      = parent::$params['country'] ?? '';
		$account_type = parent::$params['account_type'] ?? '';

		$entity_name       = parent::$params['entity_name'] ?? '';
		$entity_type       = parent::$params['entity_type'] ?? '';
		$entity_reg_number = parent::$params['entity_reg_number'] ?? '';
		$entity_vat_number = parent::$params['entity_vat_number'] ?? '';

		$document_name = parent::$params['entity_document_name'] ?? '';
		$document_page = (int)(parent::$params['entity_document_page'] ?? 0);

		$created_at   = $helper->get_datetime();

		// check account type
		$account_type = strtolower((string)$account_type);

		if (
			$account_type != 'entity' &&
			$account_type != 'individual'
		) {
			$account_type = 'individual';
		}

		$helper->sanitize_input(
			$reference_id,
			true,
			Regex::$shufti['char_limit'] - 10,
			Regex::$shufti['char_limit'],
			Regex::$shufti['pattern'],
			'Reference ID'
		);

		// individual variables
		if (!$first_name) {
			$first_name = null;
		}

		$helper->sanitize_input(
			$first_name,
			true,
			2,
			Regex::$human_name['char_limit'],
			Regex::$human_name['pattern'],
			'First Name'
		);

		if (!$middle_name) {
			$middle_name = null;
		}

		$helper->sanitize_input(
			$middle_name,
			false,
			1,
			Regex::$human_name['char_limit'],
			Regex::$human_name['pattern'],
			'Middle Name or initial'
		);

		if (!$last_name) {
			$last_name = null;
		}

		$helper->sanitize_input(
			$last_name,
			true,
			2,
			Regex::$human_name['char_limit'],
			Regex::$human_name['pattern'],
			'Last Name'
		);

		if (!$dob) {
			$dob = null;
		}

		$helper->sanitize_input(
			$dob,
			$account_type == 'individual',
			Regex::$date['char_limit'],
			Regex::$date['char_limit'],
			Regex::$date['pattern'],
			'DOB'
		);

		if (!$helper->ISO3166_country($country)) {
			_exit(
				'error',
				'Invalid country specified',
				400,
				'Invalid country specified'
			);
		}

		// entity variables
		if ($account_type == 'entity') {
			// elog('ENTITY variables');
			$entity_types = array(
				'LLC',
				'Corporation',
				'Trust',
				'Foundation',
				'Association',
				'Sole Proprietorship',
				'Other'
			);

			if (!in_array($entity_type, $entity_types)) {
				_exit(
					'error',
					'Invalid entity type',
					400,
					'Invalid entity type'
				);
			}

			if (strlen($entity_name) > 255) {
				_exit(
					'error',
					'Entity name too long. Please limit to 255 characters',
					400,
					'Entity name too long. Please limit to 255 characters'
				);
			}

			if (strlen($entity_reg_number) > 255) {
				_exit(
					'error',
					'Entity registration number too long. Please limit to 255 characters',
					400,
					'Entity registration number too long. Please limit to 255 characters'
				);
			}

			if (strlen($entity_vat_number) > 255) {
				_exit(
					'error',
					'Entity VAT number too long. Please limit to 255 characters',
					400,
					'Entity VAT number too long. Please limit to 255 characters'
				);
			}

			// simply for sanitation
			if (strlen($document_name) > 255) {
				$document_name = '';
			}
		}

		// shufti check
		$check = $db->do_select("
			SELECT
			id,
			guid,
			reference_id,
			status
			FROM shufti
			WHERE guid = '$user_guid'
		");

		$status = $check[0]['status'] ?? null;

		if ($status == 'approved') {
			_exit(
				'error',
				'Your KYC is already approved',
				400,
				'KYC is already approved'
			);
		}

		if ($check) {
			$db->do_query("
				UPDATE shufti
				SET
				reference_id     = '$reference_id',
				updated_at       = '$created_at',
				status           = 'pending',
				reviewed_at      = NULL,
				reviewed_by      = NULL,
				cmp_checked      = 0,
				data             = NULL,
				declined_reason  = '',
				id_check         = 0,
				address_check    = 0,
				background_check = 0,
				manual_review    = 0
				WHERE guid       = '$user_guid'
			");
		} else {
			$db->do_query("
				INSERT INTO shufti (
					guid,
					reference_id,
					created_at
				) VALUES (
					'$user_guid',
					'$reference_id',
					'$created_at'
				)
			");
		}

		// isolate country from country code
		if (isset(Helper::$countries[$country])) {
			$country = Helper::$countries[$country];
		}

		// update user pii record with Shufti match
		$user = $helper->get_user($user_guid);
		$pii  = $user['pii_data'] ?? array();
		$kyc  = $user['kyc_status'] ?? '';

		if ($kyc != 'approved') {
			if ($first_name) {
				$pii['first_name'] = $first_name;
			}

			if ($middle_name) {
				$pii['middle_name'] = $middle_name;
			}

			if ($last_name) {
				$pii['last_name'] = $last_name;
			}

			if (
				$dob &&
				$account_type == 'individual'
			) {
				$pii['dob'] = $dob;
			}

			if (
				$country &&
				$account_type == 'individual'
			) {
				$pii['country_of_citizenship'] = $country;
			}
		}

		$enc_pii = $helper->encrypt_pii($pii);

		// update main user table with pii and account type changes
		$db->do_query("
			UPDATE users
			SET
			pii_data     = '$enc_pii',
			account_type = '$account_type'
			WHERE guid   = '$user_guid'
		");

		// add entity data if KYB is done
		if ($account_type == 'entity') {
			// elog('ENTITY KYB');
			$entities    = $helper->get_user_entities($user_guid);
			$entity      = Structs::entity_info;
			$entity_guid = '';

			foreach ($entities as $e_key => $e_val) {
				if (!empty($e_val)) {
					$entity      = $e_val;
					$entity_guid = $e_key;
					break;
				}
			}

			$entity['entity_name']          = $entity_name;
			$entity['entity_type']          = $entity_type;
			$entity['registration_number']  = $entity_reg_number;
			$entity['registration_country'] = $country;
			$entity['tax_id']               = $entity_vat_number;
			$entity['document_url']         = $document_name;
			$entity['document_page']        = $document_page;

			$entity_enc = $helper->encrypt_pii($entity);

			if ($entity_guid) {
				// entity exists, updating
				$db->do_query("
					UPDATE entities
					SET
					pii_data          = '$entity_enc',
					updated_at        = '$created_at'
					WHERE entity_guid = '$entity_guid'
				");
			} else {
				// entity doesnt exist yet, creating
				$entity_guid = $helper->generate_guid();

				$db->do_query("
					INSERT INTO entities (
						entity_guid,
						pii_data,
						created_at,
						updated_at
					) VALUES (
						'$entity_guid',
						'$entity_enc',
						'$created_at',
						'$created_at'
					)
				");

				// create entity relationship to user
				$db->do_query("
					INSERT INTO user_entity_relations (
						user_guid,
						entity_guid,
						associated_at
					) VALUES (
						'$user_guid',
						'$entity_guid',
						'$created_at'
					)
				");
			}

			// attach previously uploaded docs
			$db->do_query("
				UPDATE entity_docs
				SET   entity_guid = '$entity_guid'
				WHERE file_name   = '$document_name'
			");

			// remove unused document references
			$docs_for_removal = $db->do_select("
				SELECT file_url
				FROM  entity_docs
				WHERE user_guid = '$user_guid'
				AND entity_guid IS NULL
			");

			$docs_for_removal = $docs_for_removal ?? array();

			foreach ($docs_for_removal as $doc) {
				//// todo: also remove docs from S3
			}

			$db->do_query("
				DELETE FROM entity_docs
				WHERE user_guid = '$user_guid'
				AND entity_guid IS NULL
			");
		}

		_exit(
			'success',
			'Shufti data added to your account'
		);
	}
}
new UserSaveShuftiRef();
