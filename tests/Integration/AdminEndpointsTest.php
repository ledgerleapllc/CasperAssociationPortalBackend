<?php
/**
 * Admin API endpoints integration tests.
 * Test public auth'd and non-auth'd endpoints, positive and negative, to ensure router integrity
 *
 * @group  integrationtests
 *
 * @static $admin_guid      Standard GUID of test admin.
 * @static $admin_email     Standard email of test admin.
 * @static $admin_fname     Admin first name.
 * @static $admin_lname     Admin last name.
 * @static $admin_pseudonym Random pseudonym for admin.
 * @static $admin_validator Randomly picked validator ID.
 * @static $admin_password  Randomly generated test password.
 * @static $bearer_token    Saved session bearer token.
 * @static $entity_guid     Saved entity guid.
 * @static $reference_id    Saved shufti reference.
 * @static $totp_key        Saved totp key.
 * @static $discussion_id   Mock discussion ID.
 *
 * @method void testCreateTemporaryAdmin()
 * @method void testForgotPassword()
 * @method void testResetPassword()
 * @method void testLogin()
 * @method void testMe()
 * @method void testSavePii()
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
 * @method void testGetNodes()
 * @method void testGetNodeData()
 * @method void testGetEras()
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
 * @method void testCensorComment()
 * @method void testUncensorComment()
 * @method void testDeleteComment()
 * @method void testLockDiscussion()
 * @method void testDeleteDiscussion()
 * @method void testCreateBallot()
 * @method void testGetActiveBallots()
 * @method void testGetFinishedBallots()
 * @method void testGetBallot()
 * @method void testGetAllVotes()
 * @method void testGetGeneralAssemblies()
 * @method void testGetNotifications()
 * @method void testXxxx()
 * @method void testXxxx()
 * @method void testXxxx()
 * @method void testXxxx()



 * @method void testCleanUp
 *
 */
use PHPUnit\Framework\TestCase;

include_once(__DIR__.'/../../core.php');

final class AdminEndpointsTest extends TestCase
{
	private static $admin_guid      = '00000000-0000-ff00-4c4c-000000000000';
	private static $admin_email     = '';
	private static $admin_fname     = 'chuck';
	private static $admin_lname     = 'taylor';
	private static $admin_pseudonym = 'chuck-taylor-123456789';
	private static $admin_password  = '';
	private static $bearer_token    = '';
	private static $totp_key        = '';
	private static $discussion_id   = 0;

	public function testCreateTemporaryAdmin()
	{
		global $db;

		$random_email         = 'admin-'.Helper::generate_hash(10).'@dev.com';
		$random_password      = Helper::generate_hash().'01*';
		$random_password_hash = hash('sha256', $random_password);
		$now                  = Helper::get_datetime(-100);

		$admin_guid           = self::$admin_guid;
		$admin_pseudonym      = self::$admin_pseudonym;
		self::$admin_email    = $random_email;
		self::$admin_password = $random_password;

		$db->do_query("
			INSERT INTO `users` (
				guid,
				role,
				email,
				pseudonym,
				verified,
				password,
				created_at,
				confirmation_code,
				admin_approved
			) VALUES (
				'$admin_guid',
				'admin',
				'$random_email',
				'$admin_pseudonym',
				1,
				'$random_password_hash',
				'$now',
				'TESTADMIN',
				1
			)
		");

		// check temporary admin exists
		$result = $db->do_select("
			SELECT *
			FROM users
			WHERE email = '$random_email'
		");

		$role = $result[0]['role'] ?? '';

		$this->assertEquals($role, 'admin');
	}

	public function testForgotPassword()
	{
		$json = Helper::self_curl(
			'post',
			'/admin/forgot-password',
			array(
				'email' => self::$admin_email
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

		self::$admin_password = Helper::generate_hash().'01*';
		$email                = self::$admin_email;

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
			'/admin/reset-password',
			array(
				'email'        => self::$admin_email,
				'hash'         => $hash,
				'new_password' => self::$admin_password
			),
			array(
				'Content-Type: application/json',
				'Authorization: Bearer '.self::$bearer_token
			)
		);

		$status = $json['status'] ?? 0;

		$this->assertEquals(200, $status);
	}

	public function testLogin()
	{
		// add authorized_device
		Helper::add_authorized_device(
			self::$admin_guid,
			'127.0.0.1',
			'',
			''
		);

		$json = Helper::self_curl(
			'post',
			'/admin/login',
			array(
				"email"    => self::$admin_email,
				"password" => self::$admin_password
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

	public function testMe()
	{
		$json = Helper::self_curl(
			'get',
			'/admin/me',
			array(),
			array(
				'Content-Type: application/json',
				'Authorization: Bearer '.self::$bearer_token
			)
		);

		$detail = (array)($json['detail'] ?? array());

		$this->assertArrayHasKey('role', $detail);
	}

	public function testSavePii()
	{
		$json = Helper::self_curl(
			'put',
			'/admin/save-pii',
			array(
				"first_name" => "cory"
			),
			array(
				'Content-Type: application/json',
				'Authorization: Bearer '.self::$bearer_token
			)
		);

		$fname = Helper::get_user(self::$admin_guid);
		$fname = $fname['pii_data']['first_name'] ?? '';

		$this->assertEquals('cory', $fname);
	}

	public function testSendMfa()
	{
		$json = Helper::self_curl(
			'post',
			'/admin/send-mfa',
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

		$guid = self::$admin_guid;

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
			'/admin/confirm-mfa',
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
		self::$admin_email = 'admin-'.Helper::generate_hash(10).'@dev.com';

		$json = Helper::self_curl(
			'put',
			'/admin/update-email',
			array(
				"new_email" => self::$admin_email,
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
		$guid = self::$admin_guid;

		$mfa_code = $db->do_select("
			SELECT code
			FROM email_changes
			WHERE guid = '$guid'
		");
		$mfa_code = $mfa_code[0]['code'] ?? '';

		$json = Helper::self_curl(
			'post',
			'/admin/confirm-update-email',
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
			'/admin/send-mfa',
			array(),
			array(
				'Content-Type: application/json',
				'Authorization: Bearer '.self::$bearer_token
			)
		);

		// verify mfa
		$guid = self::$admin_guid;

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
			'/admin/confirm-mfa',
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
			'/admin/update-mfa',
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
		self::$admin_password = Helper::generate_hash().'01*';

		// send mfa
		$json = Helper::self_curl(
			'post',
			'/admin/send-mfa',
			array(),
			array(
				'Content-Type: application/json',
				'Authorization: Bearer '.self::$bearer_token
			)
		);

		// verify mfa
		$guid = self::$admin_guid;

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
			'/admin/confirm-mfa',
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
			'/admin/update-password',
			array(
				'new_password' => self::$admin_password
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
			'/admin/send-mfa',
			array(),
			array(
				'Content-Type: application/json',
				'Authorization: Bearer '.self::$bearer_token
			)
		);

		// verify mfa
		$guid = self::$admin_guid;

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
			'/admin/confirm-mfa',
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
			'/admin/update-totp',
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
		$totp_code = Totp::get_totp_code(self::$admin_guid);

		$json = Helper::self_curl(
			'post',
			'/admin/confirm-totp',
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
			'/admin/logout',
			array(),
			array(
				'Content-Type: application/json',
				'Authorization: Bearer '.self::$bearer_token
			)
		);

		// register totp login
		$json = Helper::self_curl(
			'post',
			'/admin/login',
			array(
				"email"    => self::$admin_email,
				"password" => self::$admin_password
			),
			array(
				'Content-Type: application/json'
			)
		);

		$has_totp = (bool)($json['detail']['totp'] ?? false);

		$this->assertEquals(true, $has_totp);

		// calculate totp code
		$totp_code = Totp::get_totp_code(self::$admin_guid);

		// login with totp code
		$json = Helper::self_curl(
			'post',
			'/admin/login-mfa',
			array(
				'guid'     => self::$admin_guid,
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
			'/admin/get-dashboard',
			array(),
			array(
				'Content-Type: application/json',
				'Authorization: Bearer '.self::$bearer_token
			)
		);

		$detail = $json['detail'] ?? array();

		$this->assertArrayHasKey('trending_discussions', $detail);
	}

	public function testGetNodes()
	{
		$json = Helper::self_curl(
			'get',
			'/admin/get-nodes',
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
			'/admin/get-node-data',
			array(
				'public_key' => '011117189c666f81c5160cd610ee383dc9b2d0361f004934754d39752eedc64957'
			),
			array(
				'Content-Type: application/json',
				'Authorization: Bearer '.self::$bearer_token
			)
		);

		$detail = $json['detail'] ?? array();

		$this->assertArrayHasKey('rewards_data', $detail);
	}

	public function testGetEras()
	{
		$json = Helper::self_curl(
			'get',
			'/admin/get-user-eras',
			array(
				'guid' => '10000000-0000-0000-4c4c-c0cde9e672d5'
			),
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
			'/admin/post-discussion',
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
			'/admin/save-draft-discussion',
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
			'/admin/get-draft-discussions',
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
			'/admin/get-draft-discussion',
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
			'/admin/discard-draft',
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
			'/admin/get-all-discussions',
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
			'/admin/get-my-discussions',
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
			'/admin/get-discussion',
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
			'/admin/edit-discussion',
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
			'/admin/pin-discussion',
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
			'/admin/like-discussion',
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
			'/admin/get-pinned-discussions',
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
			'/admin/post-comment',
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
			'/admin/edit-comment',
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

	public function testCensorComment()
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

		// censor comment
		$json = Helper::self_curl(
			'put',
			'/admin/censor-comment',
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

	public function testUncensorComment()
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

		// censor comment
		$json = Helper::self_curl(
			'put',
			'/admin/uncensor-comment',
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
			'/admin/delete-comment',
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
			'/admin/lock-discussion',
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
			'/admin/delete-discussion',
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

	public function testCreateBallot()
	{
		$now  = Helper::get_datetime();
		$then = Helper::get_datetime(6300);

		$json = Helper::self_curl(
			'post',
			'/admin/create-ballot',
			array(
				'title'       => 'Ballot title',
				'description' => 'Test ballot content',
				'start_time'  => $now,
				'end_time'    => $then,
				'file_url'    => ''
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
			'/admin/get-active-ballots',
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
			'/admin/get-finished-ballots',
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
		global $db;

		// get ballot
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
			'/admin/get-ballot',
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

	public function testGetAllVotes()
	{
		$json = Helper::self_curl(
			'get',
			'/admin/get-all-votes',
			array(),
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
			'/admin/get-general-assemblies',
			array(),
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
			'/admin/get-notifications',
			array(),
			array(
				'Content-Type: application/json',
				'Authorization: Bearer '.self::$bearer_token
			)
		);

		$status = $json['status'] ?? 0;

		$this->assertEquals(200, $status);
	}












	public function testCleanUp()
	{
		global $db;

		$admin_guid = self::$admin_guid;

		// clean up user table
		$db->do_query("
			DELETE FROM users
			WHERE guid = '$admin_guid'
		");

		// clean up sessions
		$db->do_query("
			DELETE FROM sessions
			WHERE guid = '$admin_guid'
		");

		// clean up login attempts
		$db->do_query("
			DELETE FROM login_attempts
			WHERE guid = '$admin_guid'
		");

		// clean up authorized device
		$db->do_query("
			DELETE FROM authorized_devices
			WHERE guid = '$admin_guid'
		");

		// clean up scheduled emails
		$admin_email = self::$admin_email;

		$db->do_query("
			DELETE FROM schedule
			WHERE email = '$admin_email'
		");

		// clean up inactive totp keys
		$db->do_query("
			DELETE FROM totp
			WHERE guid = '$admin_guid'
		");

		// clean up password resets
		$db->do_query("
			DELETE FROM password_resets
			WHERE guid = '$admin_guid'
		");

		// clean up mfa codes
		$db->do_query("
			DELETE FROM mfa_allowance
			WHERE guid = '$admin_guid'
		");

		// clean up avatar changes
		$db->do_query("
			DELETE FROM avatar_changes
			WHERE guid = '$admin_guid'
		");

		// clean up 2fa codes
		$db->do_query("
			DELETE FROM twofa
			WHERE guid = '$admin_guid'
		");

		// clean up email_changes
		$db->do_query("
			DELETE FROM email_changes
			WHERE guid = '$admin_guid'
		");

		// clean up discussions
		$db->do_query("
			DELETE FROM discussions
			WHERE guid = '$admin_guid'
		");

		// clean up comments
		$db->do_query("
			DELETE FROM discussion_comments
			WHERE guid = '$admin_guid'
		");

		// clean up likes
		$db->do_query("
			DELETE FROM discussion_likes
			WHERE guid = '$admin_guid'
		");

		// clean up pins
		$db->do_query("
			DELETE FROM discussion_pins
			WHERE guid = '$admin_guid'
		");

		// clean up ballots
		$db->do_query("
			DELETE FROM ballots
			WHERE guid = '$admin_guid'
		");

		// clean up votes
		$db->do_query("
			DELETE FROM votes
			WHERE guid = '$admin_guid'
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
			WHERE guid = '$admin_guid'
		");

		// verify clean up
		$result = $db->do_select("
			SELECT *
			FROM users
			WHERE guid = '$admin_guid'
		");

		$this->assertEquals($result, null);
	}
}

?>