<?php
/**
 * Public API endpoints integration tests.
 * Test public non-auth'd endpoints, positive and negative, to ensure router integrity
 *
 * @group  integrationtests
 *
 * @static $random_email  Random email used for testing
 *
 * @method void testCaKycHash()
 * @method void testContactUs()
 * @method void testGetCountries()
 * @method void testGetDevMode()
 * @method void testGetEsignDoc()
 * @method void testGetMerchantData()
 * @method void testGetNodeData()
 * @method void testGetProfile()
 * @method void testGetValidators()
 * @method void testGetYear()
 * @method void testHellosignHook()
 * @method void testSubscribe()
 * @method void testCleanUp()
 *
 */
use PHPUnit\Framework\TestCase;

include_once(__DIR__.'/../../core.php');

final class PublicEndpointsTest extends TestCase
{
	private static $random_email = 'thomas+testcontact@ledgerleap.com';

	public function testCaKycHash()
	{
		$json = Helper::self_curl(
			'get',
			'/public/ca-kyc-hash/abc123',
			array(),
			array(
				'Content-Type: application/json'
			)
		);

		$detail = (array)($json['detail'] ?? array());

		$this->assertArrayHasKey('proof_hash', $detail);
	}

	public function testContactUs()
	{
		$json = Helper::self_curl(
			'post',
			'/public/contact-us',
			array(
				'name'    => 'thomas',
				'email'   => self::$random_email,
				'message' => 'test message'
			),
			array(
				'Content-Type: application/json'
			)
		);

		$status = $json['status'] ?? 0;

		$this->assertEquals(200, $status);
	}

	public function testGetCountries()
	{
		$json = Helper::self_curl(
			'get',
			'/public/get-countries',
			array(),
			array(
				'Content-Type: application/json'
			)
		);

		$status = $json['status'] ?? 0;

		$this->assertEquals(200, $status);
	}

	public function testGetDevMode()
	{
		$json = Helper::self_curl(
			'get',
			'/public/get-dev-mode',
			array(),
			array(
				'Content-Type: application/json'
			)
		);

		$status = $json['status'] ?? 0;

		$this->assertEquals(200, $status);
	}

	public function testGetEsignDoc()
	{
		$json = Helper::self_curl(
			'get',
			'/public/get-esign-doc',
			array(),
			array(
				'Content-Type: application/json'
			)
		);

		$detail = (array)($json['detail'] ?? array());

		$this->assertArrayHasKey('url', $detail);
	}

	public function testGetMerchantData()
	{
		$json = Helper::self_curl(
			'get',
			'/public/get-merchant-data',
			array(),
			array(
				'Content-Type: application/json'
			)
		);

		$detail = (array)($json['detail'] ?? array());

		$this->assertArrayHasKey('merchant_name', $detail);
	}

	public function testGetNodeData()
	{
		$json = Helper::self_curl(
			'get',
			'/public/get-node-data',
			array(
				'public_key' => '011117189c666f81c5160cd610ee383dc9b2d0361f004934754d39752eedc64957'
			),
			array(
				'Content-Type: application/json'
			)
		);

		$status = $json['status'] ?? 0;

		$this->assertEquals(200, $status);
	}

	public function testGetProfile()
	{
		$json = Helper::self_curl(
			'get',
			'/public/get-profile',
			array(
				'pseudonym' => 'admin'
			),
			array(
				'Content-Type: application/json'
			)
		);

		$status = $json['status'] ?? 0;

		$this->assertEquals(200, $status);
	}

	public function testGetValidators()
	{
		$json = Helper::self_curl(
			'get',
			'/public/get-validators',
			array(
				'uptime'     => 0,
				'fee'        => 0,
				'delegators' => 0,
				'stake'      => 0
			),
			array(
				'Content-Type: application/json'
			)
		);

		$status = $json['status'] ?? 0;

		$this->assertEquals(200, $status);
	}

	public function testGetYear()
	{
		$json = Helper::self_curl(
			'get',
			'/public/get-year',
			array(),
			array(
				'Content-Type: application/json'
			)
		);

		$status = $json['status'] ?? 0;

		$this->assertEquals(200, $status);
	}

	public function testHellosignHook()
	{
		$payload = array(
			'event' => array(
				'event_type' => 'callback_test'
			)
		);

		$json = Helper::self_curl(
			'get',
			'/public/hellosign-hook',
			array(
				'json' => json_encode($payload)
			)
		);

		$this->assertEquals('Hello API Event Received', $json);
	}

	public function testSubscribe() {
		$json = Helper::self_curl(
			'post',
			'/public/subscribe',
			array(
				'email' => self::$random_email
			),
			array(
				'Content-Type: application/json'
			)
		);

		$status = $json['status'] ?? 0;

		$this->assertEquals(200, $status);
	}

	public function testCleanUp()
	{
		global $db;

		$random_email = self::$random_email;

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

		// clean up contact
		$query = "
			DELETE FROM schedule
			WHERE email = '$random_email'
		";

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