<?php
/**
 * Database unit test. Could be considered an integration test,
 * but it mainly checks for problems in DB selection/query.
 *
 * @group  unittests
 *
 * @method void testDoQuery()
 * @method void testDoSelect()
 * @method void testDbCleanup()
 *
 */
use PHPUnit\Framework\TestCase;

include_once(__DIR__.'/../../core.php');

final class DbTest extends TestCase
{
	public function testDoQuery()
	{
		global $db;

		$test_query = "
			INSERT INTO settings (
				name,
				value
			) VALUES (
				'test-name',
				'test-value'
			)
		";

		$test_result = $db->do_query($test_query);
		$this->assertTrue($test_result);
	}

	public function testDoSelect()
	{
		global $db;

		$test_select = "
			SELECT *
			FROM settings
			WHERE name = 'test-name'
		";

		$test_result = $db->do_select($test_select);
		$test_result = $test_result[0] ?? null;
		$this->assertArrayHasKey('name', $test_result);
		$this->assertArrayHasKey('value', $test_result);
	}

	public function testDbCleanup()
	{
		global $db;

		$test_query = "
			DELETE FROM settings
			WHERE name = 'test-name'
		";

		$test_result = $db->do_query($test_query);
		$this->assertTrue($test_result);
	}
}

?>