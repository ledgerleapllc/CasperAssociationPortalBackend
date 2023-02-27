<?php
/**
 * Core unit test.
 *
 * @group  unittests
 *
 * @method void testFilter()
 * @method void testRequireMethod()
 *
 */
use PHPUnit\Framework\TestCase;

include_once(__DIR__.'/../../core.php');

final class TotpTest extends TestCase
{
	public function testTotpCreateKey()
	{
		$test_guid        = '00000000-0000-0000-4c4c-000000000000';
		$provisioning_uri = Totp::create_totp_key($test_guid);
		$this->assertTrue((bool)$provisioning_uri);
	}

	public function testTotpTestCode()
	{
		$test_guid = '00000000-0000-0000-4c4c-000000000000';
		$code      = Totp::get_totp_code($test_guid);
		$valid     = Totp::check_code(
			$test_guid,
			$code
		);
		$this->assertTrue($valid);
	}
}

?>
