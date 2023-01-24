<?php
/**
 * Public API endpoints integration tests.
 * Test public non-auth'd endpoints, positive and negative, to ensure router integrity
 *
 * @group  integrationtests
 *
 * @static $test_user_guid        Standard GUID of test admin.
 *
 * @method void testContactWebhook()
 * @method void testCleanUp()
 *
 */
use PHPUnit\Framework\TestCase;

include_once(__DIR__.'/../../core.php');

final class PublicEndpointsTest extends TestCase
{
	private static $test_user_guid  = '00000000-0000-0000-4c4c-000000000000';
	private static $test_user_email = '';

	public function testCleanUp()
	{
		global $db;

		$random_email = self::$test_user_email;

		// clean up users
		$query = "
			DELETE FROM users
			WHERE email = '$random_email'
		";
		$db->do_query($query);

		// clean up subscriptions
		$query = "
			DELETE FROM subscriptions
			WHERE email = '$random_email'
		";
		$db->do_query($query);

		// verify clean up
		$query = "
			SELECT *
			FROM users
			WHERE email = '$random_email'
		";
		$result = $db->do_select($query);

		$this->assertEquals($result, null);
	}
}

?>