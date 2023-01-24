<?php
/**
 * User API endpoints integration tests.
 * Test public auth'd and non-auth'd endpoints, positive and negative, to ensure router integrity
 *
 * @group  integrationtests
 *
 * @static $user_guid      Standard GUID of test user.
 * @static $user_email     Randomly generated test user email.
 * @static $user_fname     User first name.
 * @static $user_lname     User last name.
 * @static $user_pseudonym Random pseudonym for user.
 * @static $user_validator Randomly picked validator ID.
 * @static $user_password  Randomly generated test password.
 * @static $bearer_token   Saved session bearer token.
 * @static $entity_guid    Saved entity guid.
 * @static $reference_id   Saved shufti reference.
 * @static $totp_key       Saved totp key.
 * @static $discussion_id  Mock discussion ID.
 *
 * @method void testDefineTemporaryUser()
 * @method void testRegister()
 * @method void testMe()
 * @method void testResendCode()
 * @method void testConfirmRegistration()
 * @method void testNameByEmail()
 * @method void testForgotPassword()
 * @method void testResetPassword()
 * @method void testLogin()
 * @method void testGetEsignDoc()
 * @method void testPostHellosign()
 * @method void testDownloadMessage()
 * @method void testUploadSignature()
 * @method void testVerifyNode()
 * @method void testUploadLetter()
 * @method void testUploadEntityDoc()
 * @method void testGetEntityDocs()
 * @method void testSavePii()
 * @method void testGetShuftiToken()
 * @method void testValidateShuftiSignature()
 * @method void testSaveShuftiRef()
 * @method void testHookShuftiStatus()
 * @method void testSendMfa()
 * @method void testConfirmMfa()
 * @method void testUpdateEmail()
 * @method void testConfirmUpdateEmail()
 * @method void testUpdateMfa()
 * @method void testUpdatePassword()
 * @method void testUpdateTotp()
 * @method void testConfirmTotp()
 * @method void testLoginMfa()
 * @method void testGetDashboard()
 * @method void testGetMembership()
 * @method void testGetNodes()
 * @method void testGetNodeData()
 * @method void testFindAffiliatedNodes()
 * @method void testAddAffiliatedNode()
 * @method void testRemoveNode()
 * @method void testGetAccountValidators()
 * @method void testGetMyEras()
 * @method void testPostDiscussion()
 * @method void testSaveDraftDiscussion()
 * @method void testGetDraftDiscussions()
 * @method void testGetDraftDiscussion()
 * @method void testDiscardDraft()
 * @method void testGetAllDiscussions()
 * @method void testGetMyDiscussions()
 * @method void testGetDiscussion()
 * @method void testEditDiscussion()
 * @method void testPinDiscussion()
 * @method void testLikeDiscussion()
 * @method void testGetPinnedDiscussions()
 * @method void testPostComment()
 * @method void testEditComment()
 * @method void testDeleteComment()
 * @method void testLockDiscussion()
 * @method void testDeleteDiscussion()
 * @method void testGetActiveBallots()
 * @method void testGetFinishedBallots()
 * @method void testGetBallot()
 * @method void testGetMyVotes()
 * @method void testGetVoteEligibility()
 * @method void testVote()
 * @method void testCreateGeneralAssembly()
 * @method void testEditGeneralAssembly()
 * @method void testGetGeneralAssemblies()
 * @method void testGetGeneralAssemblyProposedTimes()
 * @method void testCancelGeneralAssembly()
 * @method void testGetNotifications()
 * @method void testDismissNotification()
 * @method void testGetWarnings()
 * @method void testGetPerks()
 * @method void testGetPerk()
 * @method void testGetAvailableUpgrade()
 * @method void testCompleteUpgrade()
 * @method void testGetUpgradedUsers()
 * @method void testGetUpgrades()
 * @method void testGetProfile()
 * @method void testGetSuspensionStatus()
 * @method void testGetIplog()
 * @method void testGetEntityPii()
 * @method void testUpdateAvatar()
 * @method void testRequestReactivation()
 * @method void testLogout()
 * @method void testCleanUp()
 * @method void testAllOtherEndpoints()
 *
 */
use PHPUnit\Framework\TestCase;

include_once(__DIR__.'/../../core.php');

final class UserEndpointsTest extends TestCase
{
	private static $user_guid      = '';
	private static $user_email     = '';
	private static $user_fname     = '';
	private static $user_lname     = '';
	private static $user_pseudonym = '';
	private static $user_validator = '';
	private static $user_password  = '';
	private static $bearer_token   = '';
	private static $entity_guid    = '';
	private static $reference_id   = '';
	private static $totp_key       = '';
	private static $discussion_id  = 0;

	public function testDefineTemporaryUser()
	{
		global $db;

		self::$user_email     = 'user-'.Helper::generate_hash(10).'@dev.com';
		self::$user_fname     = 'billy';
		self::$user_lname     = 'zabka';
		self::$user_password  = Helper::generate_hash().'01*';
		self::$user_pseudonym = 'billyzabka-'.Helper::generate_hash(8);

		$era_id     = Helper::get_current_era_id();
		$validators = $db->do_select("
			SELECT public_key
			FROM all_node_data
			WHERE era_id = $era_id
			ORDER BY uptime DESC
		");

		foreach ($validators as $v) {
			$vid = $v['public_key'] ?? '';

			if (Helper::can_claim_validator_id($vid)) {
				self::$user_validator = $vid;
				break;
			}
		}

		$this->assertEquals((bool)self::$user_validator, true);
	}

	public function testRegister()
	{
		$json = Helper::self_curl(
			'post',
			'/user/register',
			array(
				"account_type" => "individual",
				"first_name"   => self::$user_fname,
				"last_name"    => self::$user_lname,
				"email"        => self::$user_email,
				"password"     => self::$user_password,
				"pseudonym"    => self::$user_pseudonym,
				"validator_id" => self::$user_validator
			),
			array(
				'Content-Type: application/json'
			)
		);

		self::$bearer_token = $json['detail']['bearer'] ?? null;

		$this->assertEquals(256, strlen(self::$bearer_token));
	}

	public function testMe()
	{
		$json = Helper::self_curl(
			'get',
			'/user/me',
			array(),
			array(
				'Content-Type: application/json',
				'Authorization: Bearer '.self::$bearer_token
			)
		);

		$detail          = (array)($json['detail'] ?? array());
		self::$user_guid = $detail['guid'] ?? '';

		$this->assertArrayHasKey('role', $detail);
	}

	public function testResendCode()
	{
		$json = Helper::self_curl(
			'post',
			'/user/resend-code',
			array(),
			array(
				'Content-Type: application/json',
				'Authorization: Bearer '.self::$bearer_token
			)
		);

		$status = $json['status'] ?? 0;

		$this->assertEquals(200, $status);
	}

	public function testConfirmRegistration()
	{
		global $db;

		// get confirmation code without email
		$guid = self::$user_guid;
		$code = $db->do_select("
			SELECT confirmation_code
			FROM users
			WHERE guid = '$guid'
		");
		$code = $code[0]['confirmation_code'] ?? '';

		$json = Helper::self_curl(
			'post',
			'/user/confirm-registration',
			array(
				"confirmation_code" => $code
			),
			array(
				'Content-Type: application/json',
				'Authorization: Bearer '.self::$bearer_token
			)
		);

		$status = $json['status'] ?? 0;

		$this->assertEquals(200, $status);
	}

	public function testNameByEmail()
	{
		$json = Helper::self_curl(
			'get',
			'/user/name-by-email',
			array(
				"email" => self::$user_email
			),
			array(
				'Content-Type: application/json',
				'Authorization: Bearer '.self::$bearer_token
			)
		);

		$detail = $json['detail'] ?? '';

		$this->assertEquals(self::$user_guid, $detail);
	}

	public function testForgotPassword()
	{
		$json = Helper::self_curl(
			'post',
			'/user/forgot-password',
			array(
				'email' => self::$user_email
			),
			array(
				'Content-Type: application/json'
			)
		);

		$status = $json['status'] ?? 0;

		$this->assertEquals(200, $status);
	}

	public function testResetPassword()
	{
		global $db;

		self::$user_password = Helper::generate_hash().'01*';
		$email               = self::$user_email;

		// fetch reset hash from scheduled email
		$hash = $db->do_select("
			SELECT link
			FROM schedule
			WHERE email = '$email'
			ORDER BY id DESC
		");

		$hash = $hash[0]['link'] ?? '';
		$hash = explode('/', $hash);
		$hash = end($hash);
		$hash = explode('?', $hash)[0];

		$json = Helper::self_curl(
			'post',
			'/user/reset-password',
			array(
				'email'        => self::$user_email,
				'hash'         => $hash,
				'new_password' => self::$user_password
			),
			array(
				'Content-Type: application/json'
			)
		);

		$status = $json['status'] ?? 0;

		$this->assertEquals(200, $status);
	}

	public function testLogin()
	{
		// add authorized_device
		Helper::add_authorized_device(
			self::$user_guid,
			'127.0.0.1',
			'',
			''
		);

		$json = Helper::self_curl(
			'post',
			'/user/login',
			array(
				"email"    => self::$user_email,
				"password" => self::$user_password
			),
			array(
				'Content-Type: application/json'
			)
		);

		$bearer = $json['detail']['bearer'] ?? '';
		$guid   = $json['detail']['guid'] ?? '';

		$this->assertEquals(256, strlen($bearer));

		self::$bearer_token = $bearer;
	}

	public function testGetEsignDoc()
	{
		$json = Helper::self_curl(
			'get',
			'/user/get-esign-doc',
			array(),
			array(
				'Content-Type: application/json',
				'Authorization: Bearer '.self::$bearer_token
			)
		);

		$detail = $json['detail'] ?? array();

		$this->assertArrayHasKey('url', $detail);
	}

	public function testPostHellosign()
	{
		$json = Helper::self_curl(
			'post',
			'/user/post-hellosign',
			array(),
			array(
				'Content-Type: application/json',
				'Authorization: Bearer '.self::$bearer_token
			)
		);

		$status = $json['status'] ?? 0;

		$this->assertEquals(200, $status);
	}

	public function testDownloadMessage()
	{
		$json = Helper::self_curl(
			'get',
			'/user/download-message',
			array(),
			array(
				'Content-Type: application/json',
				'Authorization: Bearer '.self::$bearer_token
			)
		);

		$status = $json['status'] ?? 0;

		$this->assertEquals(200, $status);
	}

	public function testUploadSignature()
	{
		$json = Helper::self_curl(
			'post',
			'/user/upload-signature',
			array(
				"signature" => "abc123"
			),
			array(
				'Content-Type: application/json',
				'Authorization: Bearer '.self::$bearer_token
			)
		);

		$status = $json['status'] ?? 0;

		$this->assertEquals(200, $status);
	}

	public function testVerifyNode()
	{
		$json = Helper::self_curl(
			'post',
			'/user/verify-node',
			array(
				"address" => self::$user_validator
			),
			array(
				'Content-Type: application/json',
				'Authorization: Bearer '.self::$bearer_token
			)
		);

		$status    = $json['status'] ?? 0;
		$message   = $json['detail'] ?? '';

		$this->assertEquals(200, $status);
		$this->assertEquals('You own this node', $message);
	}

	public function testUploadLetter()
	{
		global $db;

		$json = Helper::self_curl(
			'post',
			'/user/upload-letter',
			array(
				"letter" => "hey its me again"
			),
			array(
				'Content-Type: application/json',
				'Authorization: Bearer '.self::$bearer_token
			)
		);

		$status = $json['status'] ?? 0;

		$this->assertEquals(200, $status);

		// admit user to dashboard
		$guid = self::$user_guid;
		$db->do_query("
			UPDATE users
			SET admin_approved = 1
			WHERE guid = '$guid'
		");
	}

	public function testUploadEntityDoc()
	{
		$json = Helper::self_curl(
			'post',
			'/user/upload-entity-doc',
			array(
				"doc" => "CobraKai"
			),
			array(
				'Content-Type: application/json',
				'Authorization: Bearer '.self::$bearer_token
			)
		);

		$status = $json['status'] ?? 0;

		$this->assertEquals(200, $status);
	}

	public function testGetEntityDocs()
	{
		$json = Helper::self_curl(
			'get',
			'/user/get-entity-docs',
			array(),
			array(
				'Content-Type: application/json',
				'Authorization: Bearer '.self::$bearer_token
			)
		);

		$status = $json['status'] ?? 0;

		$this->assertEquals(200, $status);
	}

	public function testSavePii()
	{
		$json = Helper::self_curl(
			'put',
			'/user/save-pii',
			array(
				"first_name" => "william"
			),
			array(
				'Content-Type: application/json',
				'Authorization: Bearer '.self::$bearer_token
			)
		);

		$fname = Helper::get_user(self::$user_guid);
		$fname = $fname['pii_data']['first_name'] ?? '';

		$this->assertEquals('william', $fname);
	}

	public function testGetShuftiToken()
	{
		self::$reference_id = "SHUFTI_".self::$user_guid."0.1234567890123456789";

		$json = Helper::self_curl(
			'get',
			'/user/get-shufti-token',
			array(),
			array(
				'Content-Type: application/json',
				'Authorization: Bearer '.self::$bearer_token
			)
		);

		$token   = $json['detail'] ?? '';
		$decoded = base64_decode($token);

		$this->assertEquals((bool)strstr($decoded, ':'), true);
	}

	public function testValidateShuftiSignature()
	{
		$json = Helper::self_curl(
			'post',
			'/user/validate-shufti-signature',
			array(
				"signature" => "abc123",
				"response"  => ""
			),
			array(
				'Content-Type: application/json',
				'Authorization: Bearer '.self::$bearer_token
			)
		);

		$status = $json['status'] ?? 0;

		$this->assertEquals(400, $status);
	}

	public function testSaveShuftiRef()
	{
		$json = Helper::self_curl(
			'post',
			'/user/save-shufti-ref',
			array(
				"reference_id"          => self::$reference_id,
				"first_name"            => self::$user_fname,
				"last_name"             => self::$user_lname,
				"dob"                   => "1990-12-12",
				"country"               => "US",
				"account_type"          => "entity",
				"entity_name"           => "Test Entity",
				"entity_type"           => "LLC",
				"entity_reg_number"     => "R-123456",
				"entity_document_name"  => "https://example.com/document.pdf",
				"entity_document_page"  => 1
			),
			array(
				'Content-Type: application/json',
				'Authorization: Bearer '.self::$bearer_token
			)
		);

		$status = $json['status'] ?? 0;

		$this->assertEquals(200, $status);
	}

	public function testHookShuftiStatus()
	{
		global $db;

		$test_hash = hash(
			'sha256', 
			getenv('SHUFTI_CLIENT_ID').
			":".
			getenv('SHUFTI_CLIENT_SECRET')
		);

		$json = Helper::self_curl(
			'post',
			'/public/shufti-status',
			array(
				"reference" => self::$reference_id,
				"event"     => 'verification.accepted',
				"test_hash" => $test_hash
			),
			array(
				'Content-Type: application/json'
			)
		);

		$guid = self::$user_guid;

		$kyc_status = $db->do_select("
			SELECT status
			FROM shufti
			WHERE guid = '$guid'
		");

		$kyc_status = $kyc_status[0]['status'] ?? '';

		$this->assertEquals('approved', $kyc_status);
	}

	public function testSendMfa()
	{
		$json = Helper::self_curl(
			'post',
			'/user/send-mfa',
			array(),
			array(
				'Content-Type: application/json',
				'Authorization: Bearer '.self::$bearer_token
			)
		);

		$status = $json['status'] ?? 0;

		$this->assertEquals(200, $status);
	}

	public function testConfirmMfa()
	{
		global $db;

		$guid = self::$user_guid;

		// get 2fa code
		$mfa_code = $db->do_select("
			SELECT code
			FROM twofa
			WHERE guid = '$guid'
			ORDER BY id DESC
		");
		$mfa_code = $mfa_code[0]['code'] ?? '';

		$json = Helper::self_curl(
			'post',
			'/user/confirm-mfa',
			array(
				'mfa_code' => $mfa_code
			),
			array(
				'Content-Type: application/json',
				'Authorization: Bearer '.self::$bearer_token
			)
		);

		$status = $json['status'] ?? 0;

		$this->assertEquals(200, $status);
	}

	public function testUpdateEmail()
	{
		self::$user_email = 'user-'.Helper::generate_hash(10).'@dev.com';

		$json = Helper::self_curl(
			'put',
			'/user/update-email',
			array(
				"new_email" => self::$user_email,
			),
			array(
				'Content-Type: application/json',
				'Authorization: Bearer '.self::$bearer_token
			)
		);

		$status = $json['status'] ?? 0;

		$this->assertEquals(200, $status);
	}

	public function testConfirmUpdateEmail()
	{
		global $db;

		// get mfa code scheduled to be emailed to new email
		$guid = self::$user_guid;

		$mfa_code = $db->do_select("
			SELECT code
			FROM email_changes
			WHERE guid = '$guid'
		");
		$mfa_code = $mfa_code[0]['code'] ?? '';

		$json = Helper::self_curl(
			'post',
			'/user/confirm-update-email',
			array(
				"mfa_code" => $mfa_code,
			),
			array(
				'Content-Type: application/json',
				'Authorization: Bearer '.self::$bearer_token
			)
		);

		$status = $json['status'] ?? 0;

		$this->assertEquals(200, $status);
	}

	public function testUpdateMfa()
	{
		global $db;

		// send mfa
		$json = Helper::self_curl(
			'post',
			'/user/send-mfa',
			array(),
			array(
				'Content-Type: application/json',
				'Authorization: Bearer '.self::$bearer_token
			)
		);

		// verify mfa
		$guid = self::$user_guid;

		// get 2fa code
		$mfa_code = $db->do_select("
			SELECT code
			FROM twofa
			WHERE guid = '$guid'
			ORDER BY id DESC
		");
		$mfa_code = $mfa_code[0]['code'] ?? '';

		$json = Helper::self_curl(
			'post',
			'/user/confirm-mfa',
			array(
				'mfa_code' => $mfa_code
			),
			array(
				'Content-Type: application/json',
				'Authorization: Bearer '.self::$bearer_token
			)
		);

		// apply mfa to login
		$json = Helper::self_curl(
			'put',
			'/user/update-mfa',
			array(
				'active' => true
			),
			array(
				'Content-Type: application/json',
				'Authorization: Bearer '.self::$bearer_token
			)
		);

		$status = $json['status'] ?? 0;

		$this->assertEquals(200, $status);
	}

	public function testUpdatePassword()
	{
		global $db;

		// define new password
		self::$user_password = Helper::generate_hash().'01*';

		// send mfa
		$json = Helper::self_curl(
			'post',
			'/user/send-mfa',
			array(),
			array(
				'Content-Type: application/json',
				'Authorization: Bearer '.self::$bearer_token
			)
		);

		// verify mfa
		$guid = self::$user_guid;

		// get 2fa code
		$mfa_code = $db->do_select("
			SELECT code
			FROM twofa
			WHERE guid = '$guid'
			ORDER BY id DESC
		");
		$mfa_code = $mfa_code[0]['code'] ?? '';

		$json = Helper::self_curl(
			'post',
			'/user/confirm-mfa',
			array(
				'mfa_code' => $mfa_code
			),
			array(
				'Content-Type: application/json',
				'Authorization: Bearer '.self::$bearer_token
			)
		);

		// update password after mfa auth
		$json = Helper::self_curl(
			'put',
			'/user/update-password',
			array(
				'new_password' => self::$user_password
			),
			array(
				'Content-Type: application/json',
				'Authorization: Bearer '.self::$bearer_token
			)
		);

		$status = $json['status'] ?? 0;

		$this->assertEquals(200, $status);
	}

	public function testUpdateTotp()
	{
		global $db;

		// send mfa
		$json = Helper::self_curl(
			'post',
			'/user/send-mfa',
			array(),
			array(
				'Content-Type: application/json',
				'Authorization: Bearer '.self::$bearer_token
			)
		);

		// verify mfa
		$guid = self::$user_guid;

		// get 2fa code
		$mfa_code = $db->do_select("
			SELECT code
			FROM twofa
			WHERE guid = '$guid'
			ORDER BY id DESC
		");
		$mfa_code = $mfa_code[0]['code'] ?? '';

		$json = Helper::self_curl(
			'post',
			'/user/confirm-mfa',
			array(
				'mfa_code' => $mfa_code
			),
			array(
				'Content-Type: application/json',
				'Authorization: Bearer '.self::$bearer_token
			)
		);

		// update mfa to totp method
		$json = Helper::self_curl(
			'put',
			'/user/update-totp',
			array(
				'active' => true
			),
			array(
				'Content-Type: application/json',
				'Authorization: Bearer '.self::$bearer_token
			)
		);

		$provisioning_uri = $json['detail']['provisioning_uri'] ?? '';
		$provisioning_uri = explode('secret=', $provisioning_uri);
		$provisioning_uri = $provisioning_uri[1] ?? '';
		$provisioning_uri = explode('&issuer', $provisioning_uri)[0];

		$this->assertEquals(true, (bool)$provisioning_uri);

		self::$totp_key = $provisioning_uri;
	}

	public function testConfirmTotp()
	{
		$totp_code = Totp::get_totp_code(self::$user_guid);

		$json = Helper::self_curl(
			'post',
			'/user/confirm-totp',
			array(
				'totp_code' => $totp_code
			),
			array(
				'Content-Type: application/json',
				'Authorization: Bearer '.self::$bearer_token
			)
		);

		$status = $json['status'] ?? 0;

		$this->assertEquals(200, $status);
	}

	public function testLoginMfa()
	{
		// logout first
		$json = Helper::self_curl(
			'get',
			'/user/logout',
			array(),
			array(
				'Content-Type: application/json',
				'Authorization: Bearer '.self::$bearer_token
			)
		);

		// register totp login
		$json = Helper::self_curl(
			'post',
			'/user/login',
			array(
				"email"    => self::$user_email,
				"password" => self::$user_password
			),
			array(
				'Content-Type: application/json'
			)
		);

		$has_totp = (bool)($json['detail']['totp'] ?? false);

		$this->assertEquals(true, $has_totp);

		// calculate totp code
		$totp_code = Totp::get_totp_code(self::$user_guid);

		// login with totp code
		$json = Helper::self_curl(
			'post',
			'/user/login-mfa',
			array(
				'guid'     => self::$user_guid,
				'mfa_code' => $totp_code
			),
			array(
				'Content-Type: application/json'
			)
		);

		$bearer = $json['detail']['bearer'] ?? '';
		$guid   = $json['detail']['guid'] ?? '';

		$this->assertEquals(256, strlen($bearer));

		self::$bearer_token = $bearer;
	}

	public function testGetDashboard()
	{
		$json = Helper::self_curl(
			'get',
			'/user/get-dashboard',
			array(),
			array(
				'Content-Type: application/json',
				'Authorization: Bearer '.self::$bearer_token
			)
		);

		$detail = $json['detail'] ?? array();

		$this->assertArrayHasKey('verified_members', $detail);
	}

	public function testGetMembership()
	{
		$json = Helper::self_curl(
			'get',
			'/user/get-membership',
			array(),
			array(
				'Content-Type: application/json',
				'Authorization: Bearer '.self::$bearer_token
			)
		);

		$detail = $json['detail'] ?? array();

		$this->assertArrayHasKey('node_status', $detail);
	}

	public function testGetNodes()
	{
		$json = Helper::self_curl(
			'get',
			'/user/get-nodes',
			array(),
			array(
				'Content-Type: application/json',
				'Authorization: Bearer '.self::$bearer_token
			)
		);

		$detail = $json['detail'] ?? array();

		$this->assertArrayHasKey('public_keys', $detail);
	}

	public function testGetNodeData()
	{
		$json = Helper::self_curl(
			'get',
			'/user/get-node-data',
			array(
				'public_key' => self::$user_validator
			),
			array(
				'Content-Type: application/json',
				'Authorization: Bearer '.self::$bearer_token
			)
		);

		$detail = $json['detail'] ?? array();

		$this->assertArrayHasKey('rewards_data', $detail);
	}

	public function testFindAffiliatedNodes()
	{
		$json = Helper::self_curl(
			'get',
			'/user/find-affiliated-nodes',
			array(),
			array(
				'Content-Type: application/json',
				'Authorization: Bearer '.self::$bearer_token
			)
		);

		$status = $json['status'] ?? 0;

		$this->assertEquals(200, $status);
	}

	public function testAddAffiliatedNode()
	{
		$json = Helper::self_curl(
			'post',
			'/user/add-affiliated-node',
			array(
				"address" => "010000000000000000000000000000000000000000000000000000000000000000"
			),
			array(
				'Content-Type: application/json',
				'Authorization: Bearer '.self::$bearer_token
			)
		);

		$status = $json['status'] ?? 0;

		$this->assertEquals(403, $status);
	}

	public function testRemoveNode()
	{
		$json = Helper::self_curl(
			'post',
			'/user/remove-node',
			array(
				"public_key" => "010000000000000000000000000000000000000000000000000000000000000000"
			),
			array(
				'Content-Type: application/json',
				'Authorization: Bearer '.self::$bearer_token
			)
		);

		$detail = $json['detail'] ?? 0;

		$this->assertEquals("Unable to remove this node your account", $detail);
	}

	public function testGetAccountValidators()
	{
		$json = Helper::self_curl(
			'get',
			'/user/get-account-validators',
			array(),
			array(
				'Content-Type: application/json',
				'Authorization: Bearer '.self::$bearer_token
			)
		);

		$status = $json['status'] ?? 0;

		$this->assertEquals(200, $status);
	}

	public function testGetMyEras()
	{
		$json = Helper::self_curl(
			'get',
			'/user/get-my-eras',
			array(),
			array(
				'Content-Type: application/json',
				'Authorization: Bearer '.self::$bearer_token
			)
		);

		$detail = $json['detail'] ?? array();

		$this->assertArrayHasKey('eras', $detail);
	}

	public function testPostDiscussion()
	{
		$json = Helper::self_curl(
			'post',
			'/user/post-discussion',
			array(
				'draft_id'          => 0,
				'title'             => 'Test discussion', 
				'description'       => 'this is an integration test',
				'associated_ballot' => 0,
				'for_upgrade'       => 0
			),
			array(
				'Content-Type: application/json',
				'Authorization: Bearer '.self::$bearer_token
			)
		);

		$status = $json['status'] ?? 0;

		$this->assertEquals(200, $status);
	}

	public function testSaveDraftDiscussion()
	{
		$json = Helper::self_curl(
			'post',
			'/user/save-draft-discussion',
			array(
				'draft_id'          => 0,
				'title'             => 'Test discussion draft', 
				'description'       => 'this is an integration test for discussion drafts',
				'associated_ballot' => 0,
				'for_upgrade'       => 0
			),
			array(
				'Content-Type: application/json',
				'Authorization: Bearer '.self::$bearer_token
			)
		);

		$status = $json['status'] ?? 0;

		$this->assertEquals(200, $status);
	}

	public function testGetDraftDiscussions()
	{
		$json = Helper::self_curl(
			'get',
			'/user/get-draft-discussions',
			array(),
			array(
				'Content-Type: application/json',
				'Authorization: Bearer '.self::$bearer_token
			)
		);

		$discussions         = $json['detail'] ?? array();
		self::$discussion_id = (int)($discussions[0]['id'] ?? 0);

		$this->assertGreaterThan(0, self::$discussion_id);
	}

	public function testGetDraftDiscussion()
	{
		$json = Helper::self_curl(
			'get',
			'/user/get-draft-discussion',
			array(
				'draft_id' => self::$discussion_id
			),
			array(
				'Content-Type: application/json',
				'Authorization: Bearer '.self::$bearer_token
			)
		);

		$status = $json['status'] ?? 0;

		$this->assertEquals(200, $status);
	}

	public function testDiscardDraft()
	{
		$json = Helper::self_curl(
			'post',
			'/user/discard-draft',
			array(
				'draft_id' => self::$discussion_id
			),
			array(
				'Content-Type: application/json',
				'Authorization: Bearer '.self::$bearer_token
			)
		);

		$status = $json['status'] ?? 0;

		$this->assertEquals(200, $status);
	}

	public function testGetAllDiscussions()
	{
		$json = Helper::self_curl(
			'get',
			'/user/get-all-discussions',
			array(),
			array(
				'Content-Type: application/json',
				'Authorization: Bearer '.self::$bearer_token
			)
		);

		$status = $json['status'] ?? 0;

		$this->assertEquals(200, $status);
	}

	public function testGetMyDiscussions()
	{
		$json = Helper::self_curl(
			'get',
			'/user/get-my-discussions',
			array(),
			array(
				'Content-Type: application/json',
				'Authorization: Bearer '.self::$bearer_token
			)
		);

		$discussions         = $json['detail'] ?? array();
		self::$discussion_id = (int)($discussions[0]['id'] ?? 0);

		$this->assertGreaterThan(0, self::$discussion_id);
	}

	public function testGetDiscussion()
	{
		$json = Helper::self_curl(
			'get',
			'/user/get-discussion',
			array(
				'discussion_id' => self::$discussion_id
			),
			array(
				'Content-Type: application/json',
				'Authorization: Bearer '.self::$bearer_token
			)
		);

		$status = $json['status'] ?? 0;

		$this->assertEquals(200, $status);
	}

	public function testEditDiscussion()
	{
		$json = Helper::self_curl(
			'put',
			'/user/edit-discussion',
			array(
				'discussion_id' => self::$discussion_id,
				'description'   => 'Altered discussion body'
			),
			array(
				'Content-Type: application/json',
				'Authorization: Bearer '.self::$bearer_token
			)
		);

		$status = $json['status'] ?? 0;

		$this->assertEquals(200, $status);
	}

	public function testPinDiscussion()
	{
		$json = Helper::self_curl(
			'post',
			'/user/pin-discussion',
			array(
				'discussion_id' => self::$discussion_id,
			),
			array(
				'Content-Type: application/json',
				'Authorization: Bearer '.self::$bearer_token
			)
		);

		$status = $json['status'] ?? 0;

		$this->assertEquals(200, $status);
	}

	public function testLikeDiscussion()
	{
		$json = Helper::self_curl(
			'post',
			'/user/like-discussion',
			array(
				'discussion_id' => self::$discussion_id,
			),
			array(
				'Content-Type: application/json',
				'Authorization: Bearer '.self::$bearer_token
			)
		);

		$status = $json['status'] ?? 0;

		$this->assertEquals(200, $status);
	}

	public function testGetPinnedDiscussions()
	{
		$json = Helper::self_curl(
			'get',
			'/user/get-pinned-discussions',
			array(),
			array(
				'Content-Type: application/json',
				'Authorization: Bearer '.self::$bearer_token
			)
		);

		$status = $json['status'] ?? 0;

		$this->assertEquals(200, $status);
	}

	public function testPostComment()
	{
		$json = Helper::self_curl(
			'post',
			'/user/post-comment',
			array(
				'discussion_id' => self::$discussion_id,
				'content'       => 'Test comment --'
			),
			array(
				'Content-Type: application/json',
				'Authorization: Bearer '.self::$bearer_token
			)
		);

		$status = $json['status'] ?? 0;

		$this->assertEquals(200, $status);
	}

	public function testEditComment()
	{
		global $db;

		// get comment ID
		$comment_id = $db->do_select("
			SELECT id
			FROM discussion_comments
			ORDER BY id DESC
			LIMIT 1
		");
		$comment_id = (int)($comment_id[0]['id'] ?? 0);

		// edit comment
		$json = Helper::self_curl(
			'put',
			'/user/edit-comment',
			array(
				'comment_id' => $comment_id,
				'content'    => 'Test comment --'
			),
			array(
				'Content-Type: application/json',
				'Authorization: Bearer '.self::$bearer_token
			)
		);

		$status = $json['status'] ?? 0;

		$this->assertEquals(200, $status);
	}

	public function testDeleteComment()
	{
		global $db;

		// get comment ID
		$comment_id = $db->do_select("
			SELECT id
			FROM discussion_comments
			ORDER BY id DESC
			LIMIT 1
		");
		$comment_id = (int)($comment_id[0]['id'] ?? 0);

		// delete comment
		$json = Helper::self_curl(
			'post',
			'/user/delete-comment',
			array(
				'comment_id' => $comment_id
			),
			array(
				'Content-Type: application/json',
				'Authorization: Bearer '.self::$bearer_token
			)
		);

		$status = $json['status'] ?? 0;

		$this->assertEquals(200, $status);
	}

	public function testLockDiscussion()
	{
		$json = Helper::self_curl(
			'post',
			'/user/lock-discussion',
			array(
				'discussion_id' => self::$discussion_id
			),
			array(
				'Content-Type: application/json',
				'Authorization: Bearer '.self::$bearer_token
			)
		);

		$status = $json['status'] ?? 0;

		$this->assertEquals(200, $status);
	}

	public function testDeleteDiscussion()
	{
		global $db;

		// revert discussion lock
		$discussion_id = self::$discussion_id;

		$db->do_query("
			UPDATE discussions
			SET locked = 0
			WHERE id = $discussion_id
		");

		// delete discussion
		$json = Helper::self_curl(
			'post',
			'/user/delete-discussion',
			array(
				'discussion_id' => self::$discussion_id
			),
			array(
				'Content-Type: application/json',
				'Authorization: Bearer '.self::$bearer_token
			)
		);

		$status = $json['status'] ?? 0;

		$this->assertEquals(200, $status);
	}

	public function testGetActiveBallots()
	{
		$json = Helper::self_curl(
			'get',
			'/user/get-active-ballots',
			array(),
			array(
				'Content-Type: application/json',
				'Authorization: Bearer '.self::$bearer_token
			)
		);

		$status = $json['status'] ?? 0;

		$this->assertEquals(200, $status);
	}

	public function testGetFinishedBallots()
	{
		$json = Helper::self_curl(
			'get',
			'/user/get-finished-ballots',
			array(),
			array(
				'Content-Type: application/json',
				'Authorization: Bearer '.self::$bearer_token
			)
		);

		$status = $json['status'] ?? 0;

		$this->assertEquals(200, $status);
	}

	public function testGetBallot()
	{
		// create mock ballot
		global $db;

		$start_time = Helper::get_datetime();
		$end_time   = Helper::get_datetime(1200);

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
				'10000000-0000-0000-4c4c-c0cde9e672d5',
				'Ballot title',
				'Test description',
				'$start_time',
				'$end_time',
				'active',
				'',
				'$start_time',
				'$start_time'
			)
		");

		// get id of new ballot
		$ballot_id = $db->do_select("
			SELECT id
			FROM ballots
			ORDER BY ID DESC
			LIMIT 1
		");
		$ballot_id = (int)($ballot_id[0]['id'] ?? 0); 

		// fetch ballot
		$json = Helper::self_curl(
			'get',
			'/user/get-ballot',
			array(
				'ballot_id' => $ballot_id
			),
			array(
				'Content-Type: application/json',
				'Authorization: Bearer '.self::$bearer_token
			)
		);

		$status = $json['status'] ?? 0;

		$this->assertEquals(200, $status);
	}

	public function testGetMyVotes()
	{
		$json = Helper::self_curl(
			'get',
			'/user/get-my-votes',
			array(),
			array(
				'Content-Type: application/json',
				'Authorization: Bearer '.self::$bearer_token
			)
		);

		$status = $json['status'] ?? 0;

		$this->assertEquals(200, $status);
	}

	public function testGetVoteEligibility()
	{
		$json = Helper::self_curl(
			'get',
			'/user/get-vote-eligibility',
			array(),
			array(
				'Content-Type: application/json',
				'Authorization: Bearer '.self::$bearer_token
			)
		);

		$status = $json['status'] ?? 0;

		$this->assertEquals(200, $status);
	}

	public function testVote()
	{
		global $db;

		// get id of ballot
		$ballot_id = $db->do_select("
			SELECT id
			FROM ballots
			ORDER BY ID DESC
			LIMIT 1
		");
		$ballot_id = (int)($ballot_id[0]['id'] ?? 0); 

		$json = Helper::self_curl(
			'post',
			'/user/vote',
			array(
				'ballot_id' => $ballot_id,
				'direction' => 'for'
			),
			array(
				'Content-Type: application/json',
				'Authorization: Bearer '.self::$bearer_token
			)
		);

		$status = $json['status'] ?? 0;

		$this->assertEquals(200, $status);
	}

	public function testCreateGeneralAssembly()
	{
		$json = Helper::self_curl(
			'post',
			'/user/create-general-assembly',
			array(
				'topic'         => 'Test assembly',
				'description'   => 'Test description',
				'proposed_time' => '2023-01-30 00:00:00'
			),
			array(
				'Content-Type: application/json',
				'Authorization: Bearer '.self::$bearer_token
			)
		);

		$status = $json['status'] ?? 0;

		$this->assertEquals(200, $status);
	}

	public function testEditGeneralAssembly()
	{
		global $db;

		// get assembly ID
		$assembly_id = $db->do_select("
			SELECT id
			FROM general_assemblies
			ORDER BY id DESC
		");
		$assembly_id = (int)($assembly_id[0]['id'] ?? 0);

		// edit assembly
		$json = Helper::self_curl(
			'put',
			'/user/edit-general-assembly',
			array(
				'id'          => $assembly_id,
				'topic'       => 'Modified topic',
				'description' => 'Modified description'
			),
			array(
				'Content-Type: application/json',
				'Authorization: Bearer '.self::$bearer_token
			)
		);

		$status = $json['status'] ?? 0;

		$this->assertEquals(200, $status);
	}

	public function testGetGeneralAssemblies()
	{
		$json = Helper::self_curl(
			'get',
			'/user/get-general-assemblies',
			array(),
			array(
				'Content-Type: application/json',
				'Authorization: Bearer '.self::$bearer_token
			)
		);

		$status = $json['status'] ?? 0;

		$this->assertEquals(200, $status);
	}

	public function testGetGeneralAssemblyProposedTimes()
	{
		$json = Helper::self_curl(
			'get',
			'/user/get-general-assembly-proposed-times',
			array(),
			array(
				'Content-Type: application/json',
				'Authorization: Bearer '.self::$bearer_token
			)
		);

		$status = $json['status'] ?? 0;

		$this->assertEquals(200, $status);
	}

	public function testCancelGeneralAssembly()
	{
		global $db;

		// get assembly ID
		$assembly_id = $db->do_select("
			SELECT id
			FROM general_assemblies
			ORDER BY id DESC
		");
		$assembly_id = (int)($assembly_id[0]['id'] ?? 0);

		// cancel assembly
		$json = Helper::self_curl(
			'post',
			'/user/cancel-general-assembly',
			array(
				'id' => $assembly_id
			),
			array(
				'Content-Type: application/json',
				'Authorization: Bearer '.self::$bearer_token
			)
		);

		$status = $json['status'] ?? 0;

		$this->assertEquals(200, $status);
	}

	public function testGetNotifications()
	{
		$json = Helper::self_curl(
			'get',
			'/user/get-notifications',
			array(),
			array(
				'Content-Type: application/json',
				'Authorization: Bearer '.self::$bearer_token
			)
		);

		$status = $json['status'] ?? 0;

		$this->assertEquals(200, $status);
	}

	public function testDismissNotification()
	{
		global $db;

		// create mock notification
		$now  = Helper::get_datetime();
		$guid = self::$user_guid;

		$db->do_query("
			INSERT INTO notifications (
				title,
				message,
				type,
				dismissable,
				priority,
				visible,
				created_at,
				cta,
				activate_at,
				deactivate_at
			) VALUES (
				'Test title',
				'Test notification',
				'info',
				1,
				1,
				1,
				'$now',
				'',
				NULL,
				NULL
			)
		");

		// fetch notification_id
		$notification_id = $db->do_select("
			SELECT id
			FROM notifications
			ORDER BY id DESC
			LIMIT 1
		");
		$notification_id = (int)($notification_id[0]['id'] ?? 0);

		// add user to notification broadcast
		$db->do_query("
			INSERT INTO user_notifications (
				notification_id,
				guid,
				created_at
			) VALUES (
				$notification_id,
				'$guid',
				'$now'
			)
		");

		// dismiss with user
		$json = Helper::self_curl(
			'post',
			'/user/dismiss-notification',
			array(
				'notification_id' => $notification_id
			),
			array(
				'Content-Type: application/json',
				'Authorization: Bearer '.self::$bearer_token
			)
		);

		$status = $json['status'] ?? 0;

		$this->assertEquals(200, $status);
	}

	public function testGetWarnings()
	{
		$json = Helper::self_curl(
			'get',
			'/user/get-warnings',
			array(),
			array(
				'Content-Type: application/json',
				'Authorization: Bearer '.self::$bearer_token
			)
		);

		$status = $json['status'] ?? 0;

		$this->assertEquals(200, $status);
	}

	public function testGetPerks()
	{
		$json = Helper::self_curl(
			'get',
			'/user/get-perks',
			array(),
			array(
				'Content-Type: application/json',
				'Authorization: Bearer '.self::$bearer_token
			)
		);

		$status = $json['status'] ?? 0;

		$this->assertEquals(200, $status);
	}

	public function testGetPerk()
	{
		$json = Helper::self_curl(
			'get',
			'/user/get-perk',
			array(
				'perk_id' => 0
			),
			array(
				'Content-Type: application/json',
				'Authorization: Bearer '.self::$bearer_token
			)
		);

		$status = $json['status'] ?? 0;

		$this->assertEquals(200, $status);
	}

	public function testGetAvailableUpgrade()
	{
		global $db;

		// create mock upgrade
		$now  = Helper::get_datetime();
		$then = Helper::get_datetime(12000);

		$db->do_query("
			INSERT INTO upgrades (
				version,
				created_at,
				updated_at,
				visible,
				activate_at,
				activate_era,
				link,
				notes
			) VALUES (
				'99.0.0',
				'$now',
				'$now',
				1,
				'$then',
				9999,
				'',
				'Test upgrade notes'
			)
		");

		$json = Helper::self_curl(
			'get',
			'/user/get-available-upgrade',
			array(),
			array(
				'Content-Type: application/json',
				'Authorization: Bearer '.self::$bearer_token
			)
		);

		$status = $json['status'] ?? 0;

		$this->assertEquals(200, $status);
	}

	public function testCompleteUpgrade()
	{
		$json = Helper::self_curl(
			'post',
			'/user/complete-upgrade',
			array(
				'version' => '99.0.0'
			),
			array(
				'Content-Type: application/json',
				'Authorization: Bearer '.self::$bearer_token
			)
		);

		$status = $json['status'] ?? 0;

		$this->assertEquals(200, $status);
	}

	public function testGetUpgradedUsers()
	{
		$json = Helper::self_curl(
			'get',
			'/user/get-upgraded-users',
			array(
				'version' => '99.0.0'
			),
			array(
				'Content-Type: application/json',
				'Authorization: Bearer '.self::$bearer_token
			)
		);

		$status = $json['status'] ?? 0;

		$this->assertEquals(200, $status);
	}

	public function testGetUpgrades()
	{
		$json = Helper::self_curl(
			'get',
			'/user/get-upgrades',
			array(),
			array(
				'Content-Type: application/json',
				'Authorization: Bearer '.self::$bearer_token
			)
		);

		$status = $json['status'] ?? 0;

		$this->assertEquals(200, $status);
	}

	public function testGetProfile()
	{
		$json = Helper::self_curl(
			'get',
			'/user/get-profile',
			array(
				'identifier' => self::$user_guid
			),
			array(
				'Content-Type: application/json',
				'Authorization: Bearer '.self::$bearer_token
			)
		);

		$status = $json['status'] ?? 0;

		$this->assertEquals(200, $status);
	}

	public function testGetSuspensionStatus()
	{
		$json = Helper::self_curl(
			'get',
			'/user/get-suspension-status',
			array(),
			array(
				'Content-Type: application/json',
				'Authorization: Bearer '.self::$bearer_token
			)
		);

		$status = $json['status'] ?? 0;

		$this->assertEquals(200, $status);
	}

	public function testGetIplog()
	{
		$json = Helper::self_curl(
			'get',
			'/user/get-iplog',
			array(),
			array(
				'Content-Type: application/json',
				'Authorization: Bearer '.self::$bearer_token
			)
		);

		$status = $json['status'] ?? 0;

		$this->assertEquals(200, $status);
	}

	public function testGetEntityPii()
	{
		$json = Helper::self_curl(
			'get',
			'/user/get-entity-pii',
			array(),
			array(
				'Content-Type: application/json',
				'Authorization: Bearer '.self::$bearer_token
			)
		);

		$status = $json['status'] ?? 0;

		$this->assertEquals(200, $status);
	}

	public function testUpdateAvatar()
	{
		$json = Helper::self_curl(
			'post',
			'/user/update-avatar',
			array(
				'avatar' => 'avatar'
			),
			array(
				'Content-Type: application/json',
				'Authorization: Bearer '.self::$bearer_token
			)
		);

		$status = $json['status'] ?? 0;

		$this->assertEquals(200, $status);
	}

	public function testRequestReactivation()
	{
		global $db;

		// pretend suspend user
		$now  = Helper::get_datetime();
		$guid = self::$user_guid;

		$db->do_query("
			INSERT INTO suspensions (
				guid,
				created_at,
				reason,
				reinstatable
			) VALUES (
				'$guid',
				'$now',
				'uptime',
				1
			)
		");

		// request
		$json = Helper::self_curl(
			'post',
			'/user/request-reactivation',
			array(
				'letter' => 'Im sorry'
			),
			array(
				'Content-Type: application/json',
				'Authorization: Bearer '.self::$bearer_token
			)
		);

		$status = $json['status'] ?? 0;

		$this->assertEquals(200, $status);
	}

	public function testLogout()
	{
		$json = Helper::self_curl(
			'get',
			'/user/logout',
			array(
				'email' => self::$user_email
			),
			array(
				'Content-Type: application/json',
				'Authorization: Bearer '.self::$bearer_token
			)
		);

		$message = $json['detail'] ?? '';

		$this->assertEquals('Session terminated', $message);
	}

	public function testCleanUp()
	{
		global $db;

		$user_guid = self::$user_guid;

		// clean up user table
		$db->do_query("
			DELETE FROM users
			WHERE guid = '$user_guid'
		");

		// clean up user nodes table
		$db->do_query("
			DELETE FROM user_nodes
			WHERE guid = '$user_guid'
		");

		// clean up entity related items
		$entities = $db->do_select("
			SELECT entity_guid
			FROM user_entity_relations
			WHERE user_guid = '$user_guid'
		");

		if ($entities) {
			foreach($entities as $entity) {
				$entity_guid = $entity['entity_guid'] ?? '';

				// clean up generated entities
				$db->do_query("
					DELETE FROM entities
					WHERE entity_guid = '$entity_guid'
				");
			}
		}

		// clean up shufti
		$db->do_query("
			DELETE FROM shufti
			WHERE guid = '$user_guid'
		");

		// clean up sessions
		$db->do_query("
			DELETE FROM sessions
			WHERE guid = '$user_guid'
		");

		// clean up entity relations
		$db->do_query("
			DELETE FROM user_entity_relations
			WHERE user_guid = '$user_guid'
		");

		// clean up login attempts
		$db->do_query("
			DELETE FROM login_attempts
			WHERE guid = '$user_guid'
		");

		// clean up authorized device
		$db->do_query("
			DELETE FROM authorized_devices
			WHERE guid = '$user_guid'
		");

		// clean up scheduled emails
		$user_email = self::$user_email;

		$db->do_query("
			DELETE FROM schedule
			WHERE email = '$user_email'
		");

		// clean up inactive totp keys
		$db->do_query("
			DELETE FROM totp
			WHERE guid = '$user_guid'
		");

		// clean up password resets
		$db->do_query("
			DELETE FROM password_resets
			WHERE guid = '$user_guid'
		");

		// clean up mfa codes
		$db->do_query("
			DELETE FROM mfa_allowance
			WHERE guid = '$user_guid'
		");

		// clean up avatar changes
		$db->do_query("
			DELETE FROM avatar_changes
			WHERE guid = '$user_guid'
		");

		// clean up 2fa codes
		$db->do_query("
			DELETE FROM twofa
			WHERE guid = '$user_guid'
		");

		// clean up email_changes
		$db->do_query("
			DELETE FROM email_changes
			WHERE guid = '$user_guid'
		");

		// clean up discussions
		$db->do_query("
			DELETE FROM discussions
			WHERE guid = '$user_guid'
		");

		// clean up comments
		$db->do_query("
			DELETE FROM discussion_comments
			WHERE guid = '$user_guid'
		");

		// clean up likes
		$db->do_query("
			DELETE FROM discussion_likes
			WHERE guid = '$user_guid'
		");

		// clean up pins
		$db->do_query("
			DELETE FROM discussion_pins
			WHERE guid = '$user_guid'
		");

		// clean up ballots
		$db->do_query("
			DELETE FROM ballots
			WHERE guid = '10000000-0000-0000-4c4c-c0cde9e672d5'
		");

		// clean up votes
		$db->do_query("
			DELETE FROM votes
			WHERE guid = '$user_guid'
		");

		// clean up upgrades
		$db->do_query("
			DELETE FROM upgrades
			WHERE version = '99.0.0'
		");

		$db->do_query("
			DELETE FROM user_upgrades
			WHERE version = '99.0.0'
		");

		// clean up notifications
		$db->do_query("
			DELETE FROM notifications
			WHERE title = 'Test title'
		");

		$db->do_query("
			DELETE FROM user_notifications
			WHERE guid = '$user_guid'
		");

		// clean up user warnings
		$db->do_query("
			DELETE FROM warnings
			WHERE guid = '$user_guid'
		");

		// clean up suspensions
		$db->do_query("
			DELETE FROM suspensions
			WHERE guid = '$user_guid'
		");

		// verify clean up
		$result = $db->do_select("
			SELECT *
			FROM user_entity_relations
			WHERE user_guid = '$user_guid'
		");

		$this->assertEquals($result, null);
	}





	// public function testAllOtherEndpoints()
	// {
	// 	return true;
	// 	global $db;

	// 	$scan = Helper::get_dir_contents(
	// 		BASE_DIR, 
	// 		BASE_DIR.'/public/user_api'
	// 	);

	// 	$ch        = curl_init();
	// 	$headers   = array();
	// 	$headers[] = 'Content-Type: application/json';
	// 	$headers[] = 'Authorization: Bearer '.self::$bearer_token;
	// 	curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
	// 	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

	// 	$ii = 0;

	// 	foreach ($scan as $endpoint) {
	// 		// get file contents
	// 		$php      = file_get_contents(BASE_DIR.'/'.$endpoint);

	// 		// parse docblock
	// 		$docblock = explode("*/", $php)[0];
	// 		$docblock = explode("/**", $docblock);
	// 		$docblock = $docblock[1] ?? '';
	// 		$docblock = str_replace(" * ", "", $docblock);
	// 		$docblock = explode("\n", $docblock);

	// 		$url      = '';
	// 		$method   = '';
	// 		$params   = array();

	// 		foreach ($docblock as $line) {
	// 			if (strstr($line, 'GET ')) {
	// 				$method = 'GET';
	// 				$url    = explode("GET ", $line);
	// 				$url    = $url[1] ?? '';
	// 			}

	// 			if (strstr($line, 'POST ')) {
	// 				$method = 'POST';
	// 				$url    = explode("POST ", $line);
	// 				$url    = $url[1] ?? '';
	// 			}

	// 			if (strstr($line, 'PUT ')) {
	// 				$method = 'PUT';
	// 				$url    = explode("PUT ", $line);
	// 				$url    = $url[1] ?? '';
	// 			}

	// 			if (strstr($line, '@param ')) {
	// 				$param = array_values(array_filter(explode(" ", $line)));
	// 				$type  = $param[1] ?? '';
	// 				$name  = $param[2] ?? '';

	// 				$params[] = array(
	// 					"name" => $name,
	// 					"type" => $type
	// 				);
	// 			}
	// 		}

	// 		// parse inputs regex
	// 		$regexs = array();
	// 		$inputs = explode("sanitize_input(", $php) ?? array();

	// 		foreach ($inputs as $input) {
	// 			if (strstr($input, 'Regex::')) {
	// 				$block    = explode(');', $input)[0];
	// 				$block    = explode(',', $block);
	// 				$param    = trim($block[0]);
	// 				$required = trim($block[1] ?? 'false');
	// 				$min      = trim($block[2] ?? '0');
	// 				$max      = trim($block[3] ?? '255');
	// 				$pattern  = trim($block[4] ?? '');
	// 				$regexs[str_replace('$', '', $param)] = array(
	// 					"required" => eval('return '.$required.';'),
	// 					"min"      => eval('return '.$min.';'),
	// 					"max"      => eval('return '.$max.';'),
	// 					"pattern"  => eval('return '.$pattern.';')
	// 				);
	// 			}
	// 		}

	// 		// build request body
	// 		// elog($method.' '.$url);
	// 		$fields = array();

	// 		foreach ($params as $p) {
	// 			$name    = $p['name'] ?? '';
	// 			$name    = str_replace('$', '', $name);
	// 			$type    = $p['type'] ?? 'string';
	// 			$min     = (int)($regexs[$name]['min'] ?? 0);
	// 			$max     = (int)($regexs[$name]['max'] ?? 255);
	// 			$length  = rand($min, $max);
	// 			$pattern = $regexs[$name]['pattern'] ?? '';

	// 			// elog($name.' PARAM REQUIREMENTS: ');
	// 			// elog($regexs[$name]);

	// 			if ($type == 'string') {
	// 				$value = Helper::string_from_regex(
	// 					$pattern,
	// 					$length
	// 				);
	// 			} elseif ($type == 'int') {
	// 				$value = 3;
	// 			} elseif ($type == 'bool') {
	// 				$value = true;
	// 			} elseif ($type == 'file') {
	// 				$value = '/tmp/core-js-banners';
	// 			} else {
	// 				$value = '';
	// 			}

	// 			$fields[$name] = $value;
	// 		}

	// 		// fire request
	// 		if ($method == 'POST') {
	// 			curl_setopt($ch, CURLOPT_POST, 1);
	// 			curl_setopt($ch, CURLOPT_URL, CORS_SITE.$url);
	// 			curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($fields));
	// 			// elog($fields);
	// 		} else {
	// 			$url_with_args = CORS_SITE.$url.'?';

	// 			foreach ($fields as $key => $val) {
	// 				$url_with_args .= $key.'='.$val.'&';
	// 			}

	// 			curl_setopt($ch, CURLOPT_POST, 0);
	// 			curl_setopt(
	// 				$ch, 
	// 				CURLOPT_URL, 
	// 				$url_with_args
	// 			);

	// 			// elog($url_with_args);
	// 		}

	// 		$response = curl_exec($ch);
	// 		$json     = json_decode($response);

	// 		elog(
	// 			$method.' '.
	// 			$url.' STATUS '.
	// 			$json->status.
	// 			"\n"
	// 		);

	// 		$pass = (
	// 			$json->status == 200 ||
	// 			$json->status == 403
	// 		);

	// 		$this->assertEquals($pass, true);

	// 		if ($ii == 2) {
	// 			break;
	// 		}

	// 		$ii += 1;
	// 	}

	// 	curl_close($ch);
	// }
}

?>