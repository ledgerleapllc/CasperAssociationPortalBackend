<?php
/**
 * Helper unit test.
 * Vital for functionality across the portal.
 *
 * @group  unittests
 *
 * @method void testGenerateGuid()
 * @method void testGenerateSessionToken()
 * @method void testGenerateHash()
 * @method void testCompareDatetime()
 * @method void testAesEncryptionAndDecryption()
 * @method void testInCidrRange()
 * @method void testNotInCidrRange()
 * @method void testFormatHash()
 * @method void testPublicKeyToAccountHash
 * @method void testGetTimeDelta
 *
 */
use PHPUnit\Framework\TestCase;

include_once(__DIR__.'/../../core.php');

final class HelperTest extends TestCase
{
	public function testGenerateGuid()
	{
		$test_guid = Helper::generate_guid();
		$this->assertContains(Helper::company_bytes, explode('-', $test_guid));
	}

	public function testGenerateSessionToken()
	{
		$test_session_key = Helper::generate_session_token();
		$this->assertEquals(256, strlen($test_session_key));
		$this->assertTrue(ctype_xdigit($test_session_key));
	}

	public function testGenerateHash()
	{
		$test_hash = Helper::generate_hash(22);
		$this->assertEquals(22, strlen($test_hash));
	}

	public function testGetFilingYear()
	{
		$year = Helper::get_filing_year();
		$this->assertEquals(strlen($year), 4);
	}

	public function testCompareDatetime()
	{
		$test_date1 = Helper::get_datetime();
		sleep(1);
		$test_date2 = Helper::get_datetime();
		$this->assertGreaterThan($test_date1, $test_date2);
	}

	public function testEncodeAndDecode()
	{
		$test_string = 'abc123xyz';
		$encoded     = Helper::b_encode($test_string);
		$decoded     = Helper::b_decode($encoded);
		$this->assertEquals($decoded, $test_string);
	}

	public function testAesEncryptionAndDecryption()
	{
		$test_string = "abc123xyz";
		$cypher_text = Helper::aes_encrypt($test_string);
		$plain_text  = Helper::aes_decrypt($cypher_text);
		$this->assertEquals($test_string, $plain_text);
	}

	public function testGetDirContents()
	{
		$scan = Helper::get_dir_contents(BASE_DIR, BASE_DIR.'/templates');
		$this->assertTrue(in_array('templates/user-alert.html', $scan));
	}

	public function testInCidrRange()
	{
		$in_range = Helper::in_CIDR_range(
			'192.168.15.255',
			'192.168.2.1/20'
		);
		$this->assertTrue($in_range);
	}

	public function testNotInCidrRange()
	{
		$in_range = Helper::in_CIDR_range(
			'192.168.1.3',
			'192.168.1.1/31'
		);
		$this->assertFalse($in_range);
	}

	public function testIso3166Countries()
	{
		$this->assertEquals(
			Helper::ISO3166_country('United States'),
			true
		);
	}

	public function testFormatHash()
	{
		$this->assertEquals(
			Helper::format_hash('0123456789abcdef', 10),
			'0123..cdef'
		);
	}

	public function testPublicKeyToAccountHash()
	{
		$this->assertEquals(
			Helper::public_key_to_account_hash('011117189c666f81c5160cd610ee383dc9b2d0361f004934754d39752eedc64957'),
			'f368b795b05445420064b67076bbf60b3a6ad8731b0228488c0bdcbb3004aac9'
		);
	}

	public function testGetTimeDelta()
	{
		$this->assertEquals(
			Helper::get_timedelta(110000),
			'1:06:33:20'
		);
		// 1 day, 6 hours, 33 minutes, 20 seconds
	}
}

?>
