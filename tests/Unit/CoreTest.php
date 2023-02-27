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

final class CoreTest extends TestCase
{
	public function testFilter()
	{
		$test_string = "abc123!@#'_+*[]{}/?|\"";
		$result      = filter($test_string);
		$this->assertEquals("abc123!@#\'_+*[]{}/?|\&quot;", $result);
	}

	public function testRequireMethod()
	{
		$result = require_method('GET');
		$this->assertTrue($result);
	}

}

?>
